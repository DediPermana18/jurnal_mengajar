<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\JadwalPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur Flexible Shift Management (Master Jam Pelajaran):
 *  - CRUD Jenis Shift (Shift 1 Pagi, Shift 2 Siang, dst.)
 *  - Slot jam pelajaran diikat ke shift (shift_id), filter per shift di master.
 *  - Kelas diikat ke shift (shift_id) -> jam pelajaran & jam pulang menyesuaikan.
 *  - Plotting jadwal dieskema per shift: kelas hanya melihat Global + shift-nya.
 */
class ShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected TahunAjaran $tahun;

    protected Jurusan $jurusan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Test',
            'username' => 'adminshift',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->jurusan = Jurusan::create([
            'kode_jurusan' => 'RPL',
            'nama_jurusan' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    private function makeShift(string $nama, string $mulai = '07:00', string $selesai = '14:00', bool $active = true): ShiftPelajaran
    {
        return ShiftPelajaran::create([
            'nama_shift' => $nama,
            'keterangan' => 'Keterangan '.$nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'is_active' => $active,
        ]);
    }

    private function makeSlot(?int $shiftId, string $mulai, string $selesai, int $jamKe = 1, string $kategoriHari = 'Senin-Kamis'): JamPelajaran
    {
        return JamPelajaran::create([
            'kategori_hari' => $kategoriHari,
            'shift_id' => $shiftId,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $selesai.':00',
            'jenis' => 'kbm',
        ]);
    }

    public function test_admin_can_create_shift(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.shift-pelajaran.store'), [
                'nama_shift' => 'Shift 1 (Pagi)',
                'keterangan' => 'Sesi pagi',
                'jam_mulai' => '07:00',
                'jam_selesai' => '14:00',
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shift_pelajaran', [
            'nama_shift' => 'Shift 1 (Pagi)',
            'jam_mulai' => '07:00',
            'jam_selesai' => '14:00',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_shift(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');

        $response = $this->actingAs($this->admin)
            ->put(route('admin.shift-pelajaran.update', $shift->id), [
                'nama_shift' => 'Shift 1 (Pagi) - Revisi',
                'jam_mulai' => '06:30',
                'jam_selesai' => '14:30',
                'is_active' => 0,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_pelajaran', [
            'id' => $shift->id,
            'nama_shift' => 'Shift 1 (Pagi) - Revisi',
            'jam_mulai' => '06:30',
            'is_active' => false,
        ]);
    }

    public function test_delete_shift_reverts_slots_and_classes_to_global_and_removes_pulang_rows(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');
        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $shift->id,
        ]);
        $slot = $this->makeSlot($shift->id, '07:00', '07:45');
        JamPulang::create([
            'shift_id' => $shift->id,
            'kategori_hari' => 'Senin-Kamis',
            'tingkat' => 'X',
            'max_jam_ke' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.shift-pelajaran.destroy', $shift->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('shift_pelajaran', ['id' => $shift->id]);
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id, 'shift_id' => null]);
        $this->assertDatabaseHas('jam_pelajaran', ['id' => $slot->id, 'shift_id' => null]);
        $this->assertDatabaseMissing('jam_pulang', ['shift_id' => $shift->id]);
    }

    public function test_slot_can_be_created_with_shift_id_via_store(): void
    {
        $shift = $this->makeShift('Shift 2 (Siang)');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.store'), [
                'kategori_hari' => 'Senin-Kamis',
                'shift_id' => $shift->id,
                'jam_mulai' => '13:00',
                'jam_selesai' => '13:45',
                'jenis' => 'kbm',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'shift_id' => $shift->id,
                'jam_ke' => 1,
                'jam_mulai' => '13:00',
            ]);
        }
    }

    public function test_store_redirect_preserves_shift_mode_when_submitted_from_shift_mode(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.store'), [
                'kategori_hari' => 'Senin-Kamis',
                'shift_id' => $shift->id,
                'jam_mulai' => '13:00',
                'jam_selesai' => '13:45',
                'jenis' => 'kbm',
                'mode' => 'shift',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Redirect harus kembali ke Mode Shift pada sub-tab shift yang sama.
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('mode=shift', $location);
        $this->assertStringContainsString('shift='.$shift->id, $location);

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Senin',
            'shift_id' => $shift->id,
            'jam_ke' => 1,
            'jam_mulai' => '13:00',
        ]);
    }

    public function test_index_filters_slots_by_selected_shift(): void
    {
        $this->makeSlot(null, '07:00', '07:45');
        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $s2 = $this->makeShift('Shift 2 (Siang)');
        $this->makeSlot($s1->id, '13:00', '13:45');
        $this->makeSlot($s2->id, '17:00', '17:45');

        // Mode Global (default): hanya slot global; seluruh UI shift disembunyikan.
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']))
            ->assertOk()
            ->assertSee('Mode Global')
            ->assertSee('<strong>Global</strong>', false)
            ->assertDontSee('Pilih Shift:')
            ->assertDontSee('+ Tambah Shift')
            ->assertDontSee('id="tambahShiftId"')
            ->assertDontSee('id="editShiftId"')
            ->assertSee('mode=shift')
            ->assertSee('jam_pulang[0][Senin-Kamis][X]')
            ->assertDontSee('jam_pulang[1][Senin-Kamis][X]')
            ->assertSee('07.00 – 07.45')
            ->assertDontSee('13.00 – 13.45')
            ->assertDontSee('17.00 – 17.45');

        // Mode Shift: sub-tab [Shift 1][Shift 2], hanya slot milik shift terpilih,
        // form jam pulang hanya memuat konteks shift aktif.
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift', 'shift' => $s1->id]))
            ->assertOk()
            ->assertSee('Mode Shift: Shift 1 (Pagi)', false)
            ->assertSee('Pilih Shift:')
            ->assertSee('+ Tambah Shift')
            ->assertSee('id="tambahShiftId"', false)
            ->assertSee('id="editShiftId"', false)
            ->assertSee('default: Shift 1 (Pagi) (sub-tab aktif)')
            ->assertSee('<strong>Shift 1 (Pagi)</strong>', false)
            ->assertDontSee('<strong>Shift 2 (Siang)</strong>', false)
            ->assertSee('jam_pulang[1][Senin-Kamis][X]')
            ->assertDontSee('jam_pulang[0][Senin-Kamis][X]')
            ->assertSee('13.00 – 13.45')
            ->assertDontSee('07.00 – 07.45')
            ->assertDontSee('17.00 – 17.45');

        // Mode Shift — sub-tab Shift 2: hanya slot milik Shift 2
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift', 'shift' => $s2->id]))
            ->assertOk()
            ->assertSee('default: Shift 2 (Siang) (sub-tab aktif)')
            ->assertSee('17.00 – 17.45')
            ->assertDontSee('07.00 – 07.45')
            ->assertDontSee('13.00 – 13.45');
    }

    public function test_shift_mode_without_shifts_renders_prompt_and_no_global_slots(): void
    {
        // Satu slot global ada, tetapi belum ada shift terdaftar.
        $this->makeSlot(null, '07:00', '07:45');

        // Mode shift tanpa shift: jangan bocorkan slot global; tampilkan prompt buat shift.
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift']))
            ->assertOk()
            ->assertSee('Belum Ada Shift Pelajaran')
            ->assertSee('+ Tambah Shift')
            ->assertSee('Pilih Shift:')
            ->assertDontSee('07.00 – 07.45')
            ->assertDontSee('id="tambahShiftId"');

        // Mode Global tetap menampilkan slot global seperti biasa.
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']))
            ->assertOk()
            ->assertSee('07.00 – 07.45');
    }

    public function test_update_slot_can_move_between_shifts(): void
    {
        $slot = $this->makeSlot(null, '07:00', '07:45', 1);
        $shift = $this->makeShift('Shift 2 (Siang)', '13:00', '20:00');

        $response = $this->actingAs($this->admin)
            ->put(route('admin.jam-pelajaran.update', $slot->id), [
                'kategori_hari' => 'Senin-Kamis',
                'shift_id' => $shift->id,
                'jam_mulai' => '13:00',
                'jam_selesai' => '13:45',
                'jenis' => 'kbm',
                'auto_shift' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jam_pelajaran', [
            'id' => $slot->id,
            'shift_id' => $shift->id,
            'jam_mulai' => '13:00',
            'jam_selesai' => '13:45',
        ]);
    }

    public function test_destroy_all_is_scoped_by_shift(): void
    {
        $this->makeSlot(null, '07:00', '07:45');
        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $s2 = $this->makeShift('Shift 2 (Siang)');
        $slotS1 = $this->makeSlot($s1->id, '13:00', '13:45');
        $slotS2 = $this->makeSlot($s2->id, '17:00', '17:45');

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.jam-pelajaran.destroy-all', ['kategori_hari' => 'Senin-Kamis']), ['shift' => $s1->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('jam_pelajaran', ['id' => $slotS1->id]);
        $this->assertDatabaseHas('jam_pelajaran', ['id' => $slotS2->id]);
        $this->assertDatabaseHas('jam_pelajaran', ['hari' => 'Senin', 'shift_id' => null]);
    }

    public function test_kelas_can_be_created_with_shift_id(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');

        $response = $this->actingAs($this->admin)
            ->post(route('kelas.store'), [
                'tingkat' => 'X',
                'id_jurusan' => $this->jurusan->id,
                'shift_id' => $shift->id,
            ]);

        $response->assertRedirect(route('kelas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('kelas', [
            'nama_kelas' => 'X RPL 1',
            'shift_id' => $shift->id,
        ]);
    }

    public function test_jam_pulang_upsert_stores_per_shift_settings(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pulang.upsert'), [
                'redirect_tab' => 'Senin-Kamis',
                'redirect_shift' => $shift->id,
                'jam_pulang' => [
                    0 => [
                        'Senin-Kamis' => ['X' => 8, 'XI' => 8, 'XII' => 8],
                        'Jumat' => ['X' => 6, 'XI' => 6, 'XII' => 6],
                    ],
                    $shift->id => [
                        'Senin-Kamis' => ['X' => 5, 'XI' => 4, 'XII' => 4],
                        'Jumat' => ['X' => 3, 'XI' => 3, 'XII' => 3],
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift->id]));
        $response->assertSessionHas('success');

        // Global (shift 0)
        $this->assertDatabaseHas('jam_pulang', ['shift_id' => 0, 'kategori_hari' => 'Senin-Kamis', 'tingkat' => 'X', 'max_jam_ke' => 8]);
        // Shift 1 (sama kategori & tingkat, nilai berbeda -> tidak saling menimpa)
        $this->assertDatabaseHas('jam_pulang', ['shift_id' => $shift->id, 'kategori_hari' => 'Senin-Kamis', 'tingkat' => 'X', 'max_jam_ke' => 5]);
        $this->assertDatabaseHas('jam_pulang', ['shift_id' => $shift->id, 'kategori_hari' => 'Jumat', 'tingkat' => 'XI', 'max_jam_ke' => 3]);
        // Pastikan nilai global tidak ikut tertimpa nilai shift
        $this->assertDatabaseHas('jam_pulang', ['shift_id' => 0, 'kategori_hari' => 'Senin-Kamis', 'tingkat' => 'X', 'max_jam_ke' => 8]);
    }

    public function test_plotting_visibility_and_conflicts_are_shift_scoped(): void
    {
        $s1 = $this->makeShift('Shift 1 (Pagi)', '07:00', '14:00');
        $s2 = $this->makeShift('Shift 2 (Siang)', '14:00', '21:00');

        $kelasS1 = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'shift_id' => $s1->id]);
        $kelasS2 = Kelas::create(['nama_kelas' => 'X RPL 2', 'tingkat' => 'X', 'shift_id' => $s2->id]);

        $slotS1 = $this->makeSlot($s1->id, '07:00', '07:45');
        $slotS2 = $this->makeSlot($s2->id, '13:00', '13:45');

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $guru = User::create([
            'nama' => 'Drs. Supriyanto, M.M.',
            'nip' => '197001011995011001',
            'username' => 'gurusupri',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        // 1. Kelas shift 1 hanya melihat Global + slot shift 1 pada matriks plotting
        $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelasS1->id, 'hari' => 'Senin']))
            ->assertOk()
            ->assertSee('07.00 – 07.45')
            ->assertDontSee('13.00 – 13.45');

        // 2. Kelas shift 2 hanya melihat Global + slot shift 2
        $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelasS2->id, 'hari' => 'Senin']))
            ->assertOk()
            ->assertSee('13.00 – 13.45')
            ->assertDontSee('07.00 – 07.45');

        // 3. Plot kelas shift 1 pada jam ke-1 -> record mengacu slot shift 1 (bukan shift 2)
        $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelasS1->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $kelasS1->id,
            'hari' => 'Senin',
            'id_jam' => $slotS1->id,
        ]);
        $this->assertDatabaseMissing('jadwal_pelajaran', [
            'id_kelas' => $kelasS1->id,
            'hari' => 'Senin',
            'id_jam' => $slotS2->id,
        ]);

        // 4. Guru yang sama boleh mengajar shift 2 pada jam yang berbeda (tanpa bentrok lintas shift)
        $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelasS2->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $kelasS2->id,
            'hari' => 'Senin',
            'id_jam' => $slotS2->id,
        ]);

        // 5. Namun meng-plot ulang slot yang sama pada kelas yang sama tetap ditolak
        $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelasS1->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ])
            ->assertStatus(422);
    }

    public function test_global_class_sees_only_global_slots_and_shift_class_uses_shift_pulang(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)');
        $globalSlot = $this->makeSlot(null, '07:00', '07:45');
        $shiftSlot = $this->makeSlot($shift->id, '13:00', '13:45');

        $kelasGlobal = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $kelasShift = Kelas::create(['nama_kelas' => 'X RPL 2', 'tingkat' => 'X', 'shift_id' => $shift->id]);

        // Kelas global: hanya slot global
        $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelasGlobal->id, 'hari' => 'Senin']))
            ->assertOk()
            ->assertSee('07.00 – 07.45')
            ->assertDontSee('13.00 – 13.45');

        // Kelas shift: Global + shiftnya
        $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelasShift->id, 'hari' => 'Senin']))
            ->assertOk()
            ->assertSee('07.00 – 07.45')
            ->assertSee('13.00 – 13.45');

        // Jam pulang: batas shift kelas diambil dari setting shift-nya
        JamPulang::create([
            'shift_id' => 0,
            'kategori_hari' => 'Senin-Kamis',
            'tingkat' => 'X',
            'max_jam_ke' => 8,
        ]);

        $this->assertEquals(8, JamPulang::getMaxJamKe('Senin-Kamis', 'X', 0));
        $this->assertNull(JamPulang::getMaxJamKe('Senin-Kamis', 'X', $shift->id));
        $this->assertEquals(8, JamPulang::getMaxJamKe('Senin-Kamis', 'X', $kelasGlobal->shift_effective));
        $this->assertNull(JamPulang::getMaxJamKe('Senin-Kamis', 'X', $kelasShift->shift_effective));
    }

    public function test_sync_jam_ke_numbering_is_independent_per_shift(): void
    {
        // Global punya 2 slot KBM, shift punya 3 slot KBM pada hari yang sama
        $this->makeSlot(null, '07:00', '07:45', 1);
        $this->makeSlot(null, '07:45', '08:30', 2);

        $shift = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($shift->id, '13:00', '13:45', 9);
        $this->makeSlot($shift->id, '13:45', '14:30', 9);
        $this->makeSlot($shift->id, '14:30', '15:15', 9);

        // Panggil halaman index — di dalamnya syncJamKe dijalankan per shift (Mode Shift)
        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift', 'shift' => $shift->id]))
            ->assertOk();

        $this->assertSame(
            [1, 2],
            JamPelajaran::where('hari', 'Senin')->whereNull('shift_id')->orderBy('jam_ke')->pluck('jam_ke')->all()
        );

        $this->assertSame(
            [1, 2, 3],
            JamPelajaran::where('hari', 'Senin')->where('shift_id', $shift->id)->orderBy('jam_ke')->pluck('jam_ke')->all()
        );
    }
}