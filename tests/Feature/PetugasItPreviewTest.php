<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PetugasItPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function loginPetugasIt()
    {
        $it = User::where('email', 'it@school.id')->first();
        $this->actingAs($it);

        return $it;
    }

    private function loginQaTester()
    {
        $qa = User::where('email', 'qa@school.id')->first();
        $this->actingAs($qa);

        return $qa;
    }

    private function loginGuru(): User
    {
        $guru = User::where('role', 'guru')->first();
        if (! $guru) {
            $guru = User::create([
                'nama' => 'Guru Biasa',
                'username' => 'guru_biasa',
                'password' => bcrypt('password'),
                'role' => 'guru',
                'is_active' => true,
            ]);
        }
        $this->actingAs($guru);

        return $guru;
    }

    private function createJadwal(): JadwalPelajaran
    {
        $guru = User::where('role', 'guru')->first();
        if (! $guru) {
            $guru = User::create([
                'nama' => 'Guru Pengampu',
                'username' => 'guru_pengampu',
                'password' => bcrypt('password'),
                'role' => 'guru',
                'is_active' => true,
            ]);
        }

        $tahunAjaran = TahunAjaran::create(['tahun_ajaran' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
        $kelas = Kelas::create(['nama_kelas' => 'X Uji', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika Uji', 'kode_mapel' => 'MTK-'.Str::random(6)]);
        $jam = JamPelajaran::create(['kategori_hari' => 'Senin-Kamis', 'jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00', 'jenis' => 'kbm']);

        return JadwalPelajaran::create([
            'group_id' => (string) Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $jam->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahunAjaran->id,
        ]);
    }

    private function createJurnal(array $overrides = []): Jurnal
    {
        $jadwal = $this->createJadwal();

        return Jurnal::create(array_merge([
            'id_jadwal' => $jadwal->id,
            'id_guru' => auth()->id(),
            'status_kehadiran' => 'Hadir',
            'tanggal' => now()->toDateString(),
            'materi' => 'Materi uji',
        ], $overrides));
    }

    public function test_switch_view_sets_active_role()
    {
        $this->loginPetugasIt();

        $this->post(route('it.switch-view'), ['role' => 'waka_kurikulum'])
            ->assertRedirect(route('home'));

        $this->assertEquals('waka_kurikulum', session('active_role'));
    }

    public function test_switch_view_rejects_invalid_role()
    {
        $this->loginPetugasIt();

        $this->post(route('it.switch-view'), ['role' => 'hacker'])
            ->assertStatus(422);

        $this->assertNull(session('active_role'));
    }

    public function test_reset_view_clears_active_role()
    {
        $this->loginPetugasIt();
        session(['active_role' => 'guru_piket']);

        $this->post(route('it.reset-view'))
            ->assertRedirect(route('home'));

        $this->assertNull(session('active_role'));
    }

    public function test_old_preview_role_aliases_still_work()
    {
        $it = $this->loginPetugasIt();
        session(['active_role' => 'waka_sdm']);

        $this->assertTrue($it->hasActiveRole());
        $this->assertEquals('waka_sdm', $it->activeRole());
        $this->assertTrue($it->hasPreviewRole());
        $this->assertEquals('waka_sdm', $it->previewRole());
        $this->assertEquals('admin', $it->effectiveRole());
    }

    public function test_non_it_user_cannot_switch()
    {
        $this->loginGuru();

        $this->post(route('it.switch-view'), ['role' => 'admin_tu'])
            ->assertStatus(403);
    }

    public function test_petugas_it_account_exists_with_password()
    {
        $it = User::where('email', 'it@school.id')->first();
        $this->assertNotNull($it);
        $this->assertEquals('petugas_it', $it->role);
        $this->assertTrue(Hash::check('password', $it->password));
    }

    public function test_it_can_set_testing_view_mode()
    {
        $this->loginPetugasIt();

        $this->post(route('it.testing-view'), ['mode' => 'testing'])
            ->assertStatus(302);
        $this->assertEquals('testing', session('testing_view'));

        $this->post(route('it.testing-view'), ['mode' => 'real'])
            ->assertStatus(302);
        $this->assertEquals('real', session('testing_view'));

        $this->post(route('it.testing-view'), ['mode' => 'all'])
            ->assertStatus(302);
        $this->assertEquals('all', session('testing_view'));
    }

    public function test_it_testing_view_rejects_invalid_mode()
    {
        $this->loginPetugasIt();

        $this->post(route('it.testing-view'), ['mode' => 'hacker'])
            ->assertStatus(422);

        $this->assertNotEquals('hacker', session('testing_view'));
    }

    public function test_non_it_cannot_set_testing_view()
    {
        $this->loginGuru();

        $this->post(route('it.testing-view'), ['mode' => 'testing'])
            ->assertStatus(403);
    }

    public function test_creating_as_petugas_it_sets_is_testing_data_true()
    {
        $it = $this->loginPetugasIt();

        $jurnal = $this->createJurnal(['materi' => 'Data uji dibuat IT']);

        $this->assertTrue((bool) $jurnal->is_testing_data);
        $this->assertEquals($it->id, $jurnal->id_guru);
    }

    public function test_creating_as_petugas_it_impersonating_sets_is_testing_data_true()
    {
        $it = $this->loginPetugasIt();
        session(['active_role' => 'guru_mapel']);

        $jurnal = $this->createJurnal(['materi' => 'Impersonasi guru']);

        $this->assertTrue((bool) $jurnal->is_testing_data);
        $this->assertEquals($it->id, $jurnal->id_guru);
    }

    public function test_creating_as_non_it_keeps_is_testing_data_false()
    {
        $guru = $this->loginGuru();

        $jurnal = $this->createJurnal(['materi' => 'Data real oleh guru']);

        $this->assertFalse((bool) $jurnal->is_testing_data);
        $this->assertEquals($guru->id, $jurnal->id_guru);
    }

    public function test_non_it_only_sees_real_data()
    {
        $this->loginPetugasIt();
        $testing = $this->createJurnal(['materi' => 'Data testing IT']);

        $this->loginGuru();
        $real = $this->createJurnal(['materi' => 'Data real guru']);

        $ids = Jurnal::pluck('id')->all();
        $this->assertContains($real->id, $ids);
        $this->assertNotContains($testing->id, $ids);
    }

    public function test_it_only_sees_testing_data()
    {
        $this->loginPetugasIt();
        $testing = $this->createJurnal(['materi' => 'Data testing IT']);

        $this->loginGuru();
        $real = $this->createJurnal(['materi' => 'Data real guru']);

        // Petugas IT dipaksa hanya melihat data testing (is_testing_data = true).
        $this->loginPetugasIt();
        $ids = Jurnal::pluck('id')->all();
        $this->assertContains($testing->id, $ids);
        $this->assertNotContains($real->id, $ids);
    }

    public function test_it_impersonating_still_only_sees_testing_data()
    {
        $this->loginPetugasIt();
        session(['active_role' => 'admin_tu']);

        // Di bawah "Switch View As", isTestingUser() tetap TRUE.
        $this->assertTrue(auth()->user()->isTestingUser());
        $testing = $this->createJurnal(['materi' => 'Data testing saat impersonasi']);

        $this->loginGuru();
        $real = $this->createJurnal(['materi' => 'Data real guru']);

        $this->loginPetugasIt();
        session(['active_role' => 'admin_tu']);

        $ids = Jurnal::pluck('id')->all();
        $this->assertContains($testing->id, $ids);
        $this->assertNotContains($real->id, $ids);
    }

    public function test_guest_only_sees_real_data()
    {
        $this->loginPetugasIt();
        $testing = $this->createJurnal(['materi' => 'Data testing IT']);

        $this->loginGuru();
        $real = $this->createJurnal(['materi' => 'Data real guru']);

        auth()->logout();

        $ids = Jurnal::pluck('id')->all();
        $this->assertContains($real->id, $ids);
        $this->assertNotContains($testing->id, $ids);
    }

    public function test_qa_tester_is_petugas_it()
    {
        $qa = User::where('email', 'qa@school.id')->first();

        $this->assertNotNull($qa);
        $this->assertEquals('qa_tester', $qa->role);
        $this->assertTrue($qa->isPetugasIt());
    }

    public function test_qa_tester_can_switch_view()
    {
        $this->loginQaTester();

        $this->post(route('it.switch-view'), ['role' => 'guru_mapel'])
            ->assertRedirect(route('home'));

        $this->assertEquals('guru_mapel', session('active_role'));
        $this->assertEquals('guru', auth()->user()->effectiveRole());

        $this->post(route('it.reset-view'))
            ->assertRedirect(route('home'));
        $this->assertNull(session('active_role'));
    }

    public function test_qa_tester_creates_testing_data()
    {
        $qa = $this->loginQaTester();

        $jurnal = $this->createJurnal(['materi' => 'Data uji dibuat QA']);

        $this->assertTrue((bool) $jurnal->is_testing_data);
        $this->assertEquals($qa->id, $jurnal->id_guru);
    }

    public function test_qa_tester_only_sees_testing_data()
    {
        $this->loginQaTester();
        $testing = $this->createJurnal(['materi' => 'Data testing QA']);

        $this->loginGuru();
        $real = $this->createJurnal(['materi' => 'Data real guru']);

        // QA Tester dipaksa hanya melihat data testing (is_testing_data = true).
        $this->loginQaTester();
        $ids = Jurnal::pluck('id')->all();
        $this->assertContains($testing->id, $ids);
        $this->assertNotContains($real->id, $ids);
    }
}
