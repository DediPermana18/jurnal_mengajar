<?php

namespace Tests\Feature;

use App\Models\LaporanKendala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur Helpdesk / Bug Report System:
 * - Pengguna (guru/waka/piket dst.) dapat melaporkan kendala melalui Pusat Bantuan.
 * - Dashboard IT (/it/dashboard) menampilkan statistik & tabel laporan kendala.
 * - Petugas IT mengubah status pendangan (pending/proses/selesai).
 * - IT melihat SELURUH laporan (real + testing) karena laporan inilah yang
 *   harus ditindaklanjuti (global scope TestingDataScope sengaja dilewati).
 */
class LaporanKendalaTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $extra));
    }

    protected function makeKendala(User $pelapor, array $extra = []): LaporanKendala
    {
        return LaporanKendala::create(array_merge([
            'user_id' => $pelapor->id,
            'judul' => 'Kendala '.Str::random(6),
            'deskripsi' => 'Saat klik simpan muncul error 500.',
            'status' => LaporanKendala::STATUS_PENDING,
            'prioritas' => LaporanKendala::PRIORITAS_MEDIUM,
        ], $extra));
    }

    public function test_guest_tidak_bisa_melaporkan_kendala(): void
    {
        $this->post(route('bantuan.kendala.store'), [
            'judul' => 'Test',
            'deskripsi' => 'Test deskripsi',
        ])->assertStatus(403);

        $this->assertDatabaseCount('laporan_kendala', 0);
    }

    public function test_guru_dapat_melaporkan_kendala(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)->post(route('bantuan.kendala.store'), [
            'judul' => 'Tombol Simpan Jurnal tidak merespon',
            'deskripsi' => 'Klik simpan berkali-kali tapi halaman tetap loading.',
            'prioritas' => 'high',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('laporan_kendala', [
            'user_id' => $guru->id,
            'judul' => 'Tombol Simpan Jurnal tidak merespon',
            'deskripsi' => 'Klik simpan berkali-kali tapi halaman tetap loading.',
            'status' => LaporanKendala::STATUS_PENDING,
            'prioritas' => 'high',
            'is_testing_data' => false,
        ]);
    }

    public function test_prioritas_default_sedang_saat_tidak_dipilih(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)->post(route('bantuan.kendala.store'), [
            'judul' => 'Laporan tanpa prioritas',
            'deskripsi' => 'Isi deskripsi.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('laporan_kendala', [
            'judul' => 'Laporan tanpa prioritas',
            'prioritas' => LaporanKendala::PRIORITAS_MEDIUM,
        ]);
    }

    public function test_validasi_judul_dan_deskripsi_wajib_diisi(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)->from(route('bantuan.index'))->post(route('bantuan.kendala.store'), [
            'judul' => '',
            'deskripsi' => '',
        ])->assertSessionHasErrors(['judul', 'deskripsi']);

        $this->assertDatabaseCount('laporan_kendala', 0);
    }

    public function test_foto_bukti_disimpan_di_disk_public(): void
    {
        Storage::fake('public');

        $guru = $this->makeUser('guru');

        // PNG valid 1x1 (base64) agar lolos validasi 'image' tanpa ekstensi GD.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = tempnam(sys_get_temp_dir(), 'kendala').'.png';
        file_put_contents($path, $png);
        $upload = new UploadedFile($path, 'bug.png', 'image/png', null, true);

        $this->actingAs($guru)->post(route('bantuan.kendala.store'), [
            'judul' => 'Kendala dengan screenshot',
            'deskripsi' => 'Ada lampiran gambar.',
            'foto_bukti' => $upload,
        ])->assertSessionHas('success');

        $kendala = LaporanKendala::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull($kendala->foto_bukti);
        Storage::disk('public')->assertExists($kendala->foto_bukti);
    }

    public function test_bantuan_page_menampilkan_tombol_dan_modal_lapor_kendala_untuk_user_login(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)->get(route('bantuan.index'))
            ->assertOk()
            ->assertSee('Laporkan Kendala')
            ->assertSee('modalLaporKendala')
            ->assertSee('Upload Screenshot');
    }

    public function test_bantuan_page_menampilkan_laporan_kendala_saya(): void
    {
        $guru = $this->makeUser('guru');
        $guruKendala = $this->makeKendala($guru, ['judul' => 'Laporan milik saya']);

        $orangLain = $this->makeUser('guru');
        $this->makeKendala($orangLain, ['judul' => 'Laporan milik orang lain']);

        $this->actingAs($guru)->get(route('bantuan.index'))
            ->assertOk()
            ->assertSee('Laporan Kendala Saya')
            ->assertSee('Laporan milik saya')
            ->assertDontSee('Laporan milik orang lain');

        $this->assertTrue($guruKendala->exists);
    }

    public function test_non_it_tidak_bisa_membuka_dashboard_it(): void
    {
        foreach (['admin', 'guru', 'waka_kesiswaan'] as $attempt) {
            $this->actingAs($this->makeUser($attempt))
                ->get(route('it.dashboard'))
                ->assertStatus(403);
        }
    }

    public function test_petugas_it_dapat_membuka_dashboard_it(): void
    {
        $it = $this->makeUser('petugas_it');
        $guru = $this->makeUser('guru');
        $this->makeKendala($guru, ['judul' => 'Dashboard ingin melihat ini']);

        $this->actingAs($it)->get(route('it.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard IT &amp; Helpdesk', false)
            ->assertSee('Tiket Kendala Pending')
            ->assertSee('Bug Teratasi')
            ->assertSee('Status Server')
            ->assertSee('Laporan Kendala Terbaru')
            ->assertSee('Dashboard ingin melihat ini');
    }

    public function test_dashboard_it_menampilkan_laporan_real_dan_testing(): void
    {
        $guru = $this->makeUser('guru');
        $this->makeKendala($guru, ['judul' => 'Laporan real dari guru']);

        $it = $this->makeUser('petugas_it');
        $this->makeKendala($it, [
            'judul' => 'Laporan testing dari IT',
            'is_testing_data' => true,
        ]);

        $this->actingAs($it)->get(route('it.dashboard'))
            ->assertOk()
            ->assertSee('Laporan real dari guru')
            ->assertSee('Laporan testing dari IT');
    }

    public function test_stat_counter_pending_dan_selesai(): void
    {
        $guru = $this->makeUser('guru');
        $this->makeKendala($guru);
        $this->makeKendala($guru);
        $selesai = $this->makeKendala($guru);
        $selesai->update(['status' => LaporanKendala::STATUS_SELESAI]);

        $it = $this->makeUser('petugas_it');

        $this->actingAs($it)->get(route('it.dashboard'))
            ->assertOk()
            ->assertSee('Tiket Kendala Pending')
            ->assertSee('Bug Teratasi');
    }

    public function test_petugas_it_dapat_mengubah_status_kendala(): void
    {
        $it = $this->makeUser('petugas_it');
        $guru = $this->makeUser('guru');
        $kendala = $this->makeKendala($guru, ['judul' => 'Kendala yang harus diselesaikan']);
        $this->assertSame(LaporanKendala::STATUS_PENDING, $kendala->status);

        $this->actingAs($it)->from(route('it.dashboard'))
            ->post(route('it.kendala.status', $kendala->id), ['status' => LaporanKendala::STATUS_PROSES])
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('laporan_kendala', [
            'id' => $kendala->id,
            'status' => LaporanKendala::STATUS_PROSES,
        ]);
    }

    public function test_petugas_it_dapat_mengubah_status_laporan_real(): void
    {
        // Laporan dari user non-IT (is_testing_data = false) tetap bisa diproses IT.
        $it = $this->makeUser('petugas_it');
        $guru = $this->makeUser('guru');
        $kendala = $this->makeKendala($guru, [
            'judul' => 'Laporan real yang harus selesai',
            'is_testing_data' => false,
        ]);

        $this->actingAs($it)
            ->post(route('it.kendala.status', $kendala->id), ['status' => LaporanKendala::STATUS_SELESAI])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('laporan_kendala', [
            'id' => $kendala->id,
            'status' => LaporanKendala::STATUS_SELESAI,
        ]);
    }

    public function test_non_it_tidak_bisa_mengubah_status_kendala(): void
    {
        $guru = $this->makeUser('guru');
        $kendala = $this->makeKendala($guru);

        $this->actingAs($this->makeUser('guru'))
            ->post(route('it.kendala.status', $kendala->id), ['status' => LaporanKendala::STATUS_SELESAI])
            ->assertStatus(403);

        $this->assertDatabaseHas('laporan_kendala', [
            'id' => $kendala->id,
            'status' => LaporanKendala::STATUS_PENDING,
        ]);
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $it = $this->makeUser('petugas_it');
        $guru = $this->makeUser('guru');
        $kendala = $this->makeKendala($guru);

        $this->actingAs($it)
            ->post(route('it.kendala.status', $kendala->id), ['status' => 'menghilang'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('laporan_kendala', [
            'id' => $kendala->id,
            'status' => LaporanKendala::STATUS_PENDING,
        ]);
    }
}
