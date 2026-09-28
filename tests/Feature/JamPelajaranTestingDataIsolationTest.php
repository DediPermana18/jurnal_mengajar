<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\Scopes\ActiveTahunAjaranScope;
use App\Models\Scopes\TestingDataScope;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Isolasi `is_testing_data` pada Master Jam Pelajaran.
 *
 * Sub-sistem penjadwalan (JamPelajaran) menandai TestingDataContextAware:
 *  - Baca:  difilter dengan WHERE is_testing_data = User::currentTestingStatus()
 *           (TA aktif ber-label testing ATAU user Petugas IT / QA / impersonasi).
 *  - Tulis: flag di-set OTOMATIS oleh trait HasTestingData pada event `creating`
 *           mengikuti User::currentTestingStatus() — tidak ada mismatch antara
 *           partisi tulis dan partisi baca.
 *
 * Verifikasi eksplisit pada jalur `store()` (tambah 1 slot), `generatePreset()`
 * (generate massal), dan `copyFromPrevious()` (flag menyalin dari sumber).
 */
class JamPelajaranTestingDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $subRole = null, bool $testing = false): User
    {
        return User::create([
            'nama' => "User {$role}",
            'username' => 'u_'.Str::random(8),
            'email' => null,
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
            'is_testing_data' => $testing,
        ]);
    }

    private function makePetugasIt(): User
    {
        return $this->makeUser(User::ROLE_PETUGAS_IT);
    }

    private function makeAdminTuReal(): User
    {
        return $this->makeUser(User::ROLE_ADMIN, 'admin_tu');
    }

    private function makeTahunAjaran(string $tahun, string $semester, bool $active = false, bool $testing = false): TahunAjaran
    {
        return TahunAjaran::withoutGlobalScope(TestingDataScope::class)->create([
            'tahun_ajaran' => $tahun,
            'semester' => $semester,
            'is_active' => $active,
            'is_testing_data' => $testing,
        ]);
    }

    private function storePayload(array $extra = []): array
    {
        return array_merge([
            'kategori_hari' => 'Senin-Kamis',
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:45',
            'jenis' => 'kbm',
        ], $extra);
    }

    public function test_petugas_it_menulis_slot_partisi_testing(): void
    {
        // TA aktif PRODUKSI — tetapi user Petugas IT tetap memaksa konteks testing.
        $this->makeTahunAjaran('2026/2027', 'Ganjil', true);

        $it = $this->makePetugasIt();
        $this->assertTrue($it->isTestingUser());

        $this->actingAs($it);
        $this->assertTrue(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.store'), $this->storePayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Slot yang baru dibuat masuk partisi testing (is_testing_data = 1).
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'jam_mulai' => '07:00',
                'jenis' => 'kbm',
                'is_testing_data' => 1,
            ]);
        }

        // Dan partisi produksi tetap kosong.
        $this->assertSame(0, JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', false)->count());
    }

    public function test_petugas_it_saat_impersonasi_waka_kurikulum_tetap_partisi_testing(): void
    {
        $this->makeTahunAjaran('2026/2027', 'Ganjil', true);

        $it = $this->makePetugasIt();
        // Mode "Switch View As" (impersonasi view) — role aktif Waka Kurikulum.
        $this->actingAs($it)->withSession(['active_role' => 'waka_kurikulum']);

        $this->assertTrue($it->hasActiveRole());
        $this->assertTrue(User::currentTestingStatus(), 'Impersonasi view harus tetap berstatus testing.');

        $response = $this->post(route('admin.jam-pelajaran.store'), $this->storePayload());
        $response->assertRedirect();

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jenis' => 'kbm',
            'is_testing_data' => 1,
        ]);
    }

    public function test_admin_tu_real_menulis_slot_partisi_produksi(): void
    {
        $this->makeTahunAjaran('2026/2027', 'Ganjil', true); // TA aktif PRODUKSI

        $tu = $this->makeAdminTuReal();
        $this->assertFalse($tu->isTestingUser());

        $this->actingAs($tu);
        $this->assertFalse(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.store'), $this->storePayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'jam_mulai' => '07:00',
                'jenis' => 'kbm',
                'is_testing_data' => 0,
            ]);
        }

        $this->assertSame(0, JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', true)->count());
    }

    public function test_tahun_ajaran_testing_aktif_memaksa_konteks_testing_bagi_user_real(): void
    {
        // TA aktif ber-label TESTING → konteks lingkungan aktif = testing, apa
        // pun peran user (termasuk Admin TU asli yang menulis data produksi).
        $this->makeTahunAjaran('2026/2027', 'Genap', true, testing: true);

        $tu = $this->makeAdminTuReal();
        $this->assertFalse($tu->isTestingUser());

        $this->actingAs($tu);
        $this->assertTrue(User::currentTestingStatus(), 'TA aktif testing harus memaksa konteks testing walau user bukan IT.');

        $response = $this->post(route('admin.jam-pelajaran.store'), $this->storePayload());

        $response->assertRedirect();

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jenis' => 'kbm',
            'is_testing_data' => 1,
        ]);
    }

    public function test_generate_preset_petugas_it_menulis_partisi_testing(): void
    {
        $this->makeTahunAjaran('2026/2027', 'Ganjil', true);

        $it = $this->makePetugasIt();

        $this->actingAs($it);
        $this->assertTrue(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.generate'), [
            'kategori_hari' => 'Senin-Kamis',
            'jumlah_jp' => 3,
            'durasi_jp' => 40,
            'jam_mulai' => '07:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'jam_ke' => 1,
                'jenis' => 'kbm',
                'is_testing_data' => 1,
            ]);
        }
    }

    public function test_generate_preset_admin_tu_real_menulis_partisi_produksi(): void
    {
        $this->makeTahunAjaran('2026/2027', 'Ganjil', true);

        $tu = $this->makeAdminTuReal();

        $this->actingAs($tu);
        $this->assertFalse(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.generate'), [
            'kategori_hari' => 'Senin-Kamis',
            'jumlah_jp' => 3,
            'durasi_jp' => 40,
            'jam_mulai' => '07:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'jam_ke' => 1,
                'jenis' => 'kbm',
                'is_testing_data' => 0,
            ]);
        }
    }

    public function test_copy_from_previous_menyalin_flag_partisi_sumber(): void
    {
        $lama = $this->makeTahunAjaran('2025/2026', 'Ganjil');          // partisi real
        $baru = $this->makeTahunAjaran('2026/2027', 'Ganjil', true);    // partisi real aktif

        // Slot sumber produksi (is_testing_data = 0).
        JamPelajaran::withoutGlobalScope(TestingDataScope::class)->create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:45',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $lama->id,
            'is_testing_data' => false,
        ]);

        $tu = $this->makeAdminTuReal();

        $this->actingAs($tu);
        $this->assertFalse(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.copy-from', ['ta' => $baru->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(1, JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('tahun_ajaran_id', $baru->id)
            ->where('is_testing_data', false)
            ->count(), 'Salin dari semester lalu harus menjaga partisi produksi sumber.');
    }

    public function test_copy_from_previous_menyalin_flag_testing_dari_sumber_testing(): void
    {
        $lama = $this->makeTahunAjaran('2025/2026', 'Ganjil', testing: true);          // partisi testing
        $baru = $this->makeTahunAjaran('2026/2027', 'Ganjil', true, testing: true);    // partisi testing aktif

        JamPelajaran::withoutGlobalScope(TestingDataScope::class)->create([
            'hari' => 'Jumat',
            'kategori_hari' => 'Jumat',
            'jam_ke' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:45',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $lama->id,
            'is_testing_data' => true,
        ]);

        $it = $this->makePetugasIt();

        $this->actingAs($it);
        $this->assertTrue(User::currentTestingStatus());

        $response = $this->post(route('admin.jam-pelajaran.copy-from', ['ta' => $baru->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(1, JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('tahun_ajaran_id', $baru->id)
            ->where('is_testing_data', true)
            ->count(), 'Salin dari semester lalu harus menyalin partisi testing (is_testing_data = 1).');
    }
}