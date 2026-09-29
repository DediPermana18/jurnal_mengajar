<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\Scopes\TestingDataScope;
use App\Models\ShiftPiket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi untuk tombol "Kosongkan" per hari pada halaman Jadwal Piket Guru.
 *
 * SKENARIO
 * --------
 * Admin/waka kurikulum perlu mengosongkan seluruh penugasan piket satu hari
 * (mis. Rabu) tanpa menghapus jadwal hari lain maupun minggu lain. Tombol ini
 * injectable: satu form di header card hari, mengarahkan DELETE ke endpoint
 * `/kurikulum/jadwal-piket/clear-day/{hari}`.
 *
 * YANG DIUJI
 * ----------
 *  A. Endpoint menghapus SELURUH baris jadwal_piket pada (hari, minggu_ke) —
 *     termasuk baris Waka Piket / Koordinator Pagi & Siang / Petugas Pagi & Siang
 *     (format SK maupun format shift dinamis), bukan cuma kolom `user_id`.
 *  B. Jadwal hari lain & minggu lain TIDAK ikut terhapus.
 *  C. Otorisasi: hanya role pengelola (admin/waka kurikulum/admin_tu) yang boleh;
 *     Petugas IT mode langsung (hanya bisa lihat) tetap 403.
 *  D. Guard data testing: partisi testing hanya boleh dikosongkan Petugas IT
 *     (lewat impersonasi waka_kurikulum), dan tetap utuh untuk non-IT.
 *  E. Setelah redirect, halaman menampilkan card hari tersebut sebagai kosong
 *     ("Belum ada penugasan piket hari X") + flash sukses.
 */
class JadwalPiketClearDayTest extends TestCase
{
    use RefreshDatabase;

    protected User $pengelola;

    protected User $guruA;

    protected User $guruB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengelola = User::create([
            'nama' => 'Waka Kurikulum',
            'username' => 'waka'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
        ]);

        $this->guruA = $this->buatGuru('Pak Adi');
        $this->guruB = $this->buatGuru('Bu Sinta');
    }

    private function buatGuru(string $nama): User
    {
        return User::create([
            'nama' => $nama,
            'username' => 'guru'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
    }

    /**
     * Akun Petugas IT / QA Tester.
     */
    private function buatPetugasIt(): User
    {
        return User::create([
            'nama' => 'Petugas IT',
            'username' => 'it'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'petugas_it',
            'is_active' => true,
        ]);
    }

    /**
     * Query jadwal_piket(partisi testing) untuk_siapa saja yang sedang login —
     * dipakai agar assertion tidak ikut tersaring TestingDataScope.
     */
    private function jadwalRabu(): Builder
    {
        return JadwalPiket::withoutGlobalScope(TestingDataScope::class)
            ->where('hari', 'Rabu');
    }

    /**
     * Shift piket master (Pagi/Siang) — wajib ada karena `jadwal_piket.shift_id`
     * punya FK ke `shift_piket`.
     */
    private function buatShiftPiket(string $nama, string $mulai, string $selesai, int $urutan): ShiftPiket
    {
        return ShiftPiket::create([
            'nama' => $nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'maksimal_petugas' => 4,
            'urutan' => $urutan,
            'is_active' => true,
        ]);
    }

    /**
     * Isi satu hari dengan campuran seluruh format baris jadwal piket.
     *
     * @return array<int, JadwalPiket>
     */
    private function isiJadwalRabu(int $mingguKe = 1): array
    {
        $baris = [];

        $shiftPagi = $this->buatShiftPiket('Pagi', '07:00', '10:00', 1);
        $shiftSiang = $this->buatShiftPiket('Siang', '10:00', '14:00', 2);

        // Format shift dinamis: petugas Pagi & Siang ber-shift_id.
        $baris[] = JadwalPiket::create([
            'hari' => 'Rabu',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'shift_id' => $shiftPagi->id,
            'user_id' => $this->guruA->id,
        ]);
        $baris[] = JadwalPiket::create([
            'hari' => 'Rabu',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'shift_id' => $shiftSiang->id,
            'user_id' => $this->guruB->id,
        ]);

        // Format SK legacy: Waka + Koordinator Pagi/Siang + Petugas Pagi/Siang
        // disimpan sebagai baris terpisah (kolom peran terisi, shift_id NULL).
        $baris[] = JadwalPiket::create([
            'hari' => 'Rabu',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'user_id' => $this->guruA->id,
            'waka_user_id' => $this->guruB->id,
        ]);
        $baris[] = JadwalPiket::create([
            'hari' => 'Rabu',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'user_id' => $this->guruB->id,
            'koordinator_pagi_user_id' => $this->guruA->id,
        ]);
        $baris[] = JadwalPiket::create([
            'hari' => 'Rabu',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'user_id' => $this->guruA->id,
            'koordinator_siang_user_id' => $this->guruB->id,
        ]);

        return $baris;
    }

    private function isiJadwalSenin(int $mingguKe = 1): void
    {
        JadwalPiket::create([
            'hari' => 'Senin',
            'minggu_ke' => $mingguKe,
            'bulan' => 9,
            'tahun' => 2026,
            'user_id' => $this->guruA->id,
        ]);
    }

    /**
     * A + B: Menghapus satu hari menghapus SELURUH baris format hari itu
     * (shift dinamis, waka, koordinator, petugas) tanpa menyentuh hari lain
     * maupun minggu lain.
     */
    public function test_clear_day_menghapus_seluruh_jadwal_hari_tersebut_saja(): void
    {
        $barisRabu = $this->isiJadwalRabu(mingguKe: 1);
        $this->isiJadwalSenin(mingguKe: 1);
        $this->isiJadwalRabu(mingguKe: 2);

        $response = $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]));

        $response->assertRedirect(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]));
        $response->assertSessionHas('success');

        // Seluruh baris Rabu minggu ke-1 hilang — termasuk baris peran SK.
        foreach ($barisRabu as $baris) {
            $this->assertDatabaseMissing('jadwal_piket', [
                'id' => $baris->id,
                'hari' => 'Rabu',
                'minggu_ke' => 1,
            ]);
        }

        $this->assertSame(0, JadwalPiket::where('hari', 'Rabu')->where('minggu_ke', 1)->count());

        // Hari lain pada minggu yang sama TIDAK boleh ikut terhapus.
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1]);

        // Hari yang sama pada minggu LAIN juga tidak boleh ikut terhapus.
        $this->assertSame(5, JadwalPiket::where('hari', 'Rabu')->where('minggu_ke', 2)->count());
    }

    /**
     * A: Waka / Koordinator / Petugas Pagi & Siang ikut terhapus — bukan hanya
     * baris yang mengisi `user_id` sebagai petugas shift.
     */
    public function test_clear_day_menghapus_waka_koordinator_dan_petugas_sk(): void
    {
        $this->isiJadwalRabu(mingguKe: 1);

        $this->assertSame(1, JadwalPiket::whereNotNull('waka_user_id')->count());
        $this->assertSame(1, JadwalPiket::whereNotNull('koordinator_pagi_user_id')->count());
        $this->assertSame(1, JadwalPiket::whereNotNull('koordinator_siang_user_id')->count());
        $this->assertSame(2, JadwalPiket::whereNotNull('shift_id')->count());

        $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]))
            ->assertRedirect();

        $this->assertSame(0, JadwalPiket::where('hari', 'Rabu')->count(), 'Semua baris hari Rabu harus terhapus.');
        $this->assertSame(0, JadwalPiket::whereNotNull('waka_user_id')->count());
        $this->assertSame(0, JadwalPiket::whereNotNull('koordinator_pagi_user_id')->count());
        $this->assertSame(0, JadwalPiket::whereNotNull('koordinator_siang_user_id')->count());
        $this->assertSame(0, JadwalPiket::whereNotNull('shift_id')->count());
    }

    /**
     * C:Role tanpa izin managing (guru biasa) ditolak 403 dan data utuh.
     */
    public function test_clear_day_menolak_guru_biasa(): void
    {
        $this->isiJadwalRabu(mingguKe: 1);

        $this->actingAs($this->guruA)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]))
            ->assertForbidden();

        $this->assertSame(5, JadwalPiket::where('hari', 'Rabu')->count(), 'Data tidak boleh terhapus oleh non-pengelola.');
    }

    /**
     * C: Petugas IT mode langsung (hanya bisa melihat) tetap ditolak, konsisten
     * dengan authorizeManage() yang memakai effectiveRole().
     */
    public function test_clear_day_menolak_petugas_it_mode_lengkap(): void
    {
        $it = $this->buatPetugasIt();

        $this->isiJadwalRabu(mingguKe: 1);

        $this->actingAs($it)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]))
            ->assertForbidden();

        $this->assertSame(5, $this->jadwalRabu()->count());
    }

    /**
     * D: Jadwal piket partisi testing tidak dapat disentuh pengguna non-IT.
     *
     * Partisi testing tidak terlihat oleh waka/administrasi (TestingDataScope),
     * sehingga "mengosongkan" tidak menemukan apa pun dan data testing tetap
     * utuh. Yang diuji di sini adalah hasil teramannya, bukan kode status 403
     * secara spesifik — penjaga TestingDataScope adalah lapisan pertama.
     */
    public function test_clear_day_tidak_merusak_jadwal_testing_oleh_non_it(): void
    {
        $this->isiJadwalRabu(mingguKe: 1);
        JadwalPiket::query()->update(['is_testing_data' => true]);

        $response = $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]));

        $this->assertContains($response->getStatusCode(), [302, 403], 'Respons harus redirect atau ditolak, bukan sukses hapus.');

        // Pesan sukses TIDAK boleh muncul karena tidak ada yang terhapus.
        if ($response->getStatusCode() === 302) {
            $response->assertSessionMissing('success');
            $response->assertSessionHas('info');
        }

        $this->assertSame(5, $this->jadwalRabu()->count(), 'Jadwal testing harus tetap utuh.');
    }

    /**
     * D: Petugas IT yang ber-impersonasi waka_kurikulum BOLEH mengosongkan
     * jadwal testing (authorizeManage memakai effectiveRole, jadi mode IT
     * langsung tetap read-only seperti dijamin test di atas).
     */
    public function test_clear_day_petugas_it_impersonasi_boleh_mengosongkan_jadwal_testing(): void
    {
        $it = $this->buatPetugasIt();

        $this->isiJadwalRabu(mingguKe: 1);
        JadwalPiket::query()->update(['is_testing_data' => true]);

        $this->actingAs($it)
            ->withSession(['active_role' => 'waka_kurikulum'])
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]))
            ->assertRedirect();

        $this->assertSame(0, $this->jadwalRabu()->count());
    }

    /**
     * E: Setelah redirect, halaman menampilkan card hari yang dikosongkan sebagai
     * state kosong "Belum ada penugasan piket hari X".
     */
    public function test_setelah_kosong_halaman_menampilkan_state_kosong(): void
    {
        $this->isiJadwalRabu(mingguKe: 1);

        // Sebelum dihapus: card menampilkan nama petugas.
        $sebelum = $this->actingAs($this->pengelola)
            ->get(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]));
        $sebelum->assertOk();
        $sebelum->assertSee('Pak Adi');
        $sebelum->assertSee('Bu Sinta');
        $sebelum->assertSee('data-clear-day-form', false);

        $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]));

        $sesudah = $this->actingAs($this->pengelola)
            ->get(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]));
        $sesudah->assertOk();
        $sesudah->assertSee('Belum ada penugasan piket hari Rabu');
        $sesudah->assertSee('berhasil dikosongkan');
    }

    /**
     * E: Tombol "Kosongkan" tidak muncul pada hari yang sudah kosong.
     */
    public function test_tombol_kosongkan_tidak_muncul_pada_hari_kosong(): void
    {
        $this->isiJadwalSenin(mingguKe: 1); // Senin terisi, Rabu kosong.

        $response = $this->actingAs($this->pengelola)
            ->get(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]));

        $response->assertOk();
        $response->assertSee('Belum ada penugasan piket hari Rabu');

        // Hanya 1 form kosongkan (untuk Senin), tidak untuk Rabu. `data-jumlah`
        // dipakai sebagai penanda karena string ini hanya ada pada form kosongkan
        // (tidak muncul lagi di selector JS).
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-jumlah='), 'Form Kosongkan hanya untuk hari yang terisi.');
    }

    /**
     * Melakukan clear day pada hari yang sudah kosong tidak error, hanya info.
     */
    public function test_clear_day_hari_kosong_hanya_flash_info(): void
    {
        $response = $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Jumat', 'minggu_ke' => 1]));

        $response->assertRedirect(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]));
        $response->assertSessionHas('info');
        $response->assertSessionMissing('success');
    }

    /**
     * Hari di luar daftar resmi (Senin-Jumat) ditolak 404.
     */
    public function test_clear_day_menolak_hari_tidak_valid(): void
    {
        $this->isiJadwalSenin(mingguKe: 1);

        $this->actingAs($this->pengelola)
            ->delete('/kurikulum/jadwal-piket/clear-day/Sabtu')
            ->assertNotFound();

        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin']);
    }

    /**
     * Respons JSON tersedia untuk klien non-browser.
     */
    public function test_clear_day_mengembalikan_json_saat_diminta(): void
    {
        $this->isiJadwalRabu(mingguKe: 1);

        $response = $this->actingAs($this->pengelola)
            ->deleteJson(route('kurikulum.jadwal-piket.clear-day', ['hari' => 'Rabu', 'minggu_ke' => 1]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('hari', 'Rabu');
        $response->assertJsonPath('minggu_ke', 1);
        $response->assertJsonPath('deleted', 5);
        $this->assertSame(0, JadwalPiket::where('hari', 'Rabu')->count());
    }

    /**
     * Route per-guru `destroy` tidak tertangkap oleh route `clear-day`
     * (route ordering dijaga: clear-day/{hari} dua segmen, destroy/{id} satu).
     */
    public function test_route_destroy_per_guru_tetap_berfungsi_normal(): void
    {
        $baris = $this->isiJadwalRabu(mingguKe: 1);

        $this->actingAs($this->pengelola)
            ->delete(route('kurikulum.jadwal-piket.destroy', $baris[0]->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('jadwal_piket', ['id' => $baris[0]->id]);
        $this->assertSame(4, JadwalPiket::where('hari', 'Rabu')->count(), 'Hapus per-guru hanya menghapus 1 baris.');
    }
}
