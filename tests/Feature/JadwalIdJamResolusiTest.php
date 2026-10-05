<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\JamSlotResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi untuk bug slot jam `id_jam`.
 *
 * SKENARIO BUG
 * ------------
 * `jadwal_pelajaran.id_jam` adalah FK auto-increment ke `jam_pelajaran.id`.
 * Ketika master jam dihapus lalu dibuat ulang, ID barunya meloncat (91, 93, 97,
 * 100, ...) sehingga SEMUA jadwal lama menunjuk slot yang sudah tidak ada lagi.
 * Akibatnya:
 *   - Matriks Plotting kelas: slot tampil "Belum di-plot" (jadwal hilang).
 *   - Dashboard Guru: jadwal tidak terpetakan karena `jamPelajaran` null.
 *
 * YANG DIUJI
 * ----------
 *  A. Penyimpanan jadwal me-resolve `id_jam` secara DINAMIS dari
 *     (hari, jam_ke, shift) — tidak pernah memakai ID kiriman client.
 *  B. Query jadwal me-JOIN/lookup ke master jam dan mengurutkan berdasarkan
 *     `jam_ke`, bukan `id_jam`; jadwal "gantung" tidak boleh bikin error.
 *  C. Auto-heal menulari `id_jam` usang kembali ke slot aktif yang benar.
 */
class JadwalIdJamResolusiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected TahunAjaran $tahun;

    protected Kelas $kelas;

    protected MataPelajaran $mapel;

    protected User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'adm'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $jurusan = Jurusan::create([
            'kode_jurusan' => 'RPL',
            'nama_jurusan' => 'Rekayasa Perangkat Lunak',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $jurusan->id,
        ]);

        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Matematika',
            'kode_mapel' => 'MTK',
        ]);

        $this->guru = User::create([
            'nama' => 'Pak Budi',
            'username' => 'guru'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
    }

    public function test_filter_hari_jumat_dipertahankan_dan_disediakan_untuk_handler_pilih_kelas(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', [
                'id_kelas' => $this->kelas->id,
                'hari' => 'Jumat',
            ]))
            ->assertOk()
            ->assertViewHas('selectedHari', 'Jumat');

        $response->assertSee(
            "const currentHari = url.searchParams.get('hari') || \"Jumat\";",
            false
        );
        $response->assertSee(
            "url.searchParams.set('hari', currentHari);",
            false
        );
    }

    /**
     * Buat satu baris jadwal (group_id wajib NOT NULL).
     */
    private function buatJadwal(?int $idJam, string $hari = 'Senin', string $groupId = 'grp-1'): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'group_id' => $groupId,
            'id_kelas' => $this->kelas->id,
            'hari' => $hari,
            'id_jam' => $idJam,
            'id_mapel' => $this->mapel->id,
            'id_guru' => $this->guru->id,
            'id_tahun_ajaran' => $this->tahun->id,
        ]);
    }

    /**
     * Buat master slot jam aktif.
     */
    private function buatSlot(string $hari, int $jamKe, string $mulai, ?int $shiftId = null): JamPelajaran
    {
        return JamPelajaran::create([
            'hari' => $hari,
            'kategori_hari' => $hari === 'Jumat' ? 'Jumat' : 'Senin-Kamis',
            'shift_id' => $shiftId,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $mulai.':00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahun->id,
        ]);
    }

    /**
     * Slot non-KBM (istirahat): `jam_ke` SENGAJA NULL karena bukan jam
     * pelajaran uttered, tetapi slotnya tetap punya posisi waktu sendiri.
     */
    private function buatSlotIstirahat(string $mulai, string $selesai, string $hari = 'Senin'): JamPelajaran
    {
        return JamPelajaran::create([
            'hari' => $hari,
            'kategori_hari' => $hari === 'Jumat' ? 'Jumat' : 'Senin-Kamis',
            'shift_id' => null,
            'jam_ke' => null,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $selesai.':00',
            'jenis' => 'istirahat',
            'tahun_ajaran_id' => $this->tahun->id,
        ]);
    }

    /**
     * Simulasikan master jam dihapus & dibuat ulang: slot lama di-soft-delete,
     * slot baru dibuat dengan ID jauh lebih besar.
     */
    private function recreateMasterJam(): void
    {
        JamPelajaran::query()->delete(); // soft delete semua slot lama

        // "B_padding" agar auto-increment meloncat, meniru gejala ID 91/93/97/100.
        for ($i = 0; $i < 40; $i++) {
            JamPelajaran::create([
                'hari' => 'Senin',
                'kategori_hari' => 'Senin-Kamis',
                'jam_ke' => null,
                'jam_mulai' => '23:59:00',
                'jam_selesai' => '23:59:00',
                'jenis' => 'istirahat',
                'tahun_ajaran_id' => $this->tahun->id,
            ]);
        }

        $this->buatSlot('Senin', 1, '07:00');
        $this->buatSlot('Senin', 2, '07:45');
        $this->buatSlot('Senin', 3, '08:30');
    }

    /**
     * A + C: Plotting jadwal setelah master jam di-recreate tetap memetakan
     * jadwal ke slot AKTIF berdasarkan jam_ke, dan `id_jam` di-heal.
     */
    public function test_plotting_menampilkan_jadwal_lama_di_kolom_yang_benar_setelah_master_jam_direcreate(): void
    {
        $slotLama = $this->buatSlot('Senin', 2, '07:45');

        $jadwal = $this->buatJadwal($slotLama->id);

        $idJamLama = $jadwal->id_jam;
        $this->recreateMasterJam();

        // Kondisi awal: jadwal menunjuk slot yang sudah tidak aktif.
        $this->assertTrue(
            JamPelajaran::withTrashed()->findOrFail($idJamLama)->trashed(),
            'Slot lama harus berstatus soft-deleted (ID tidak lagi berlaku).'
        );
        $this->assertNull($jadwal->fresh()->jamPelajaran, 'Slot lama harus terbaca null lewat relasi.');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $this->kelas->id, 'hari' => 'Senin']));

        $response->assertOk();

        // Auto-heal: id_jam menunjuk slot AKTIF dengan jam_ke yang sama.
        $slotBaruAktif = JamPelajaran::where('hari', 'Senin')
            ->where('jam_ke', 2)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->assertNotEquals($idJamLama, $slotBaruAktif->id, 'ID slot baru harus berbeda dari ID lama.');
        $this->assertEquals($slotBaruAktif->id, $jadwal->fresh()->id_jam, 'id_jam harus di-heal ke slot aktif.');
        $this->assertEquals(2, $jadwal->fresh()->jamPelajaran->jam_ke);

        // Jadwal benar-benar tampil di matriks (bukan "Belum di-plot").
        $response->assertSee('Matematika');
        $response->assertSee('Pak Budi');

        // Banner "jadwal gantung" tidak boleh muncul karena semua jadwal ter-heal.
        $response->assertDontSee('tidak tertaut ke slot jam master');
    }

    /**
     * B: Matriks plotting diurutkan KRONOLOGIS (jam_mulai), bukan id_jam.
     * Slot Istirahat (jam_ke NULL) harus tetap berada di TENGAH jam KBM —
     * bukan terseret ke baris paling bawah.
     */
    public function test_matriks_plotting_mengurutkan_istirahat_di_tengah_berdasarkan_waktu_mulai(): void
    {
        // Slot KBM sengaja dibuat dengan urutan ID terbalik agar test benar-benar
        // menguji sorting, bukan kebetulan auto-increment.
        $this->buatSlot('Senin', 4, '10:00');
        $istirahat1 = $this->buatSlotIstirahat('09:40', '10:00');
        $this->buatSlot('Senin', 3, '09:00');
        $istirahat2 = $this->buatSlotIstirahat('12:00', '13:00');
        $this->buatSlot('Senin', 2, '08:20');
        $this->buatSlot('Senin', 1, '07:00');
        $this->buatSlot('Senin', 5, '13:00');

        $slots = JamPelajaran::slotPerHari('Senin', null, $this->tahun->id, true);

        $this->assertSame(
            ['07:00:00', '08:20:00', '09:00:00', '09:40:00', '10:00:00', '12:00:00', '13:00:00'],
            $slots->pluck('jam_mulai')->all(),
            'Slot harus urut KRONOLOGIS menurut jam_mulai, termasuk slot istirahat.'
        );

        $this->assertSame(
            [1, 2, 3, null, 4, null, 5],
            $slots->pluck('jam_ke')->map(fn ($v) => $v === null ? null : (int) $v)->all(),
            'Istirahat harus berada di tengah matriks, bukan di baris paling bawah.'
        );

        // Posisi relatif terhadap slot KBM di sekitarnya.
        $ids = $slots->pluck('id')->all();
        $this->assertSame(
            [$istirahat1->id, $istirahat2->id],
            [$ids[3], $ids[5]],
            'Slot istirahat harus tepat berada pada kolom waktunya.'
        );
    }

    /**
     * B: Halaman Plotting benar-benar merender slot istirahat di tengah tabel
     * (regresi symptom: "Istirahat 1 & 2 muncul paling bawah setelah Jam 10").
     */
    public function test_halaman_plotting_merender_slot_istirahat_di_tengah_kolom(): void
    {
        $this->buatSlot('Senin', 1, '07:00');
        $this->buatSlot('Senin', 2, '07:45');
        $istirahat1 = $this->buatSlotIstirahat('08:30', '08:50');
        $this->buatSlot('Senin', 3, '08:50');
        $this->buatSlot('Senin', 4, '09:35');
        $this->buatSlot('Senin', 5, '10:20');
        $istirahat2 = $this->buatSlotIstirahat('11:05', '12:05');
        $this->buatSlot('Senin', 6, '12:05');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $this->kelas->id, 'hari' => 'Senin']));

        $response->assertOk();

        /** @var Collection<int, JamPelajaran> $list */
        $list = $response->viewData('jamPelajaranList');
        $ids = $list->pluck('id')->all();

        $this->assertSame(
            [1, 2, null, 3, 4, 5, null, 6],
            $list->pluck('jam_ke')->map(fn ($v) => $v === null ? null : (int) $v)->all(),
            'Kolom matriks harus urut waktu: Istirahat 1 & 2 di tengah, bukan di bawah.'
        );

        // Posisi relatif: istirahat 1 tepat setelah jam ke-2, istirahat 2
        // tepat setelah jam ke-5.
        $this->assertSame(2, array_search($istirahat1->id, $ids, true), 'Istirahat 1 harus di kolom ke-3.');
        $this->assertSame(6, array_search($istirahat2->id, $ids, true), 'Istirahat 2 harus di kolom ke-7.');

        // Nama slot istirahat tetap dirender berurutan (Istirahat 1, Istirahat 2).
        $response->assertSee('Istirahat 1');
        $response->assertSee('Istirahat 2');
    }

    /**
     * B: Dashboard guru & query jadwal harian juga urut KRONOLOGIS.
     */
    public function test_jadwal_harian_guru_terurut_kronologis_bukan_id_jam(): void
    {
        $hari = $this->hariIni();

        // Dibuat terbalik: slot terakhir dibuat paling dulu (ID terkecil).
        $slot5 = $this->buatSlot($hari, 5, '10:20', null);
        $slot1 = $this->buatSlot($hari, 1, '07:00', null);
        $slot3 = $this->buatSlot($hari, 3, '08:50', null);
        $slot2 = $this->buatSlot($hari, 2, '07:45', null);
        $slot4 = $this->buatSlot($hari, 4, '09:35', null);

        $jadwal5 = $this->buatJadwal($slot5->id, $hari, 'grp-5');
        $jadwal1 = $this->buatJadwal($slot1->id, $hari, 'grp-1');
        $jadwal3 = $this->buatJadwal($slot3->id, $hari, 'grp-3');
        $jadwal2 = $this->buatJadwal($slot2->id, $hari, 'grp-2');
        $jadwal4 = $this->buatJadwal($slot4->id, $hari, 'grp-4');

        $response = $this->actingAs($this->guru)
            ->get(route('guru.dashboard'));

        $response->assertOk();

        $jadwalTampilan = $response->viewData('jadwalHariIni');
        $this->assertCount(1, $jadwalTampilan, 'Slot kelas dan mata pelajaran yang berurutan menjadi satu baris.');
        $this->assertSame('1 - 5', $jadwalTampilan->first()->jam_ke_tampilan);
        $this->assertSame('07:00 - 10:20', $jadwalTampilan->first()->waktu_tampilan);
        $this->assertSame(1, substr_count($response->getContent(), 'Isi Jurnal</a>'));
    }

    /**
     * B: Dashboard guru mengurutkan & memetakan jadwal lewat jam_ke.
     * Jadwal "gantung" (id_jam tak tertaut master aktif) tetap tampil, tidak error.
     */
    public function test_dashboard_guru_mengurutkan_by_jam_ke_dan_tetap_menampilkan_jadwal_gantung(): void
    {
        $hari = $this->hariIni();

        $slotLama2 = $this->buatSlot($hari, 2, '07:45');
        $slotLama1 = $this->buatSlot($hari, 1, '07:00');

        $jadwal2 = $this->buatJadwal($slotLama2->id, $hari, 'grp-a');
        $jadwal1 = $this->buatJadwal($slotLama1->id, $hari, 'grp-b');

        // Master jam dihapus total -> kedua jadwal jadi "gantung".
        JamPelajaran::query()->delete();

        $response = $this->actingAs($this->guru)
            ->get(route('guru.dashboard'));

        $response->assertOk();

        // Jadwal gantung tetap tampil dan slot berurutan tetap dikelompokkan.
        $result = $response->viewData('jadwalHariIni');
        $this->assertCount(1, $result, 'Slot gantung yang berurutan menjadi satu baris.');
        $this->assertSame('1 - 2', $result[0]->jam_ke_tampilan);
        $this->assertSame('-', $result[0]->waktu_tampilan);
        $this->assertSame(0, (int) $result[0]->jam_valid);
    }

    /**
     * Nama hari "hari ini" — sama dengan yang dipakai GuruPortalController.
     */
    private function hariIni(): string
    {
        $map = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[Carbon::now()->format('l')] ?? 'Senin';
    }

    /**
     * A: `id_jam` hasil resolve dinamis mengikuti (hari, jam_ke) — tidak hardcode.
     */
    public function test_resolve_slot_mencari_master_aktif_secara_dinamis(): void
    {
        $this->recreateMasterJam();

        $slotSenin2 = JamPelajaran::resolveSlot(2, 'Senin', null, $this->tahun->id, true);
        $this->assertNotNull($slotSenin2);
        $this->assertSame(2, $slotSenin2->jam_ke);
        $this->assertSame('Senin', $slotSenin2->hari);
        $this->assertNull($slotSenin2->deleted_at);

        // Slot yang tidak ada (jam_ke di luar master) → null, bukan ID tebakan.
        $this->assertNull(JamPelajaran::resolveSlot(99, 'Senin', null, $this->tahun->id, true));

        // Hari berbeda → ID berbeda walau jam_ke sama.
        $slotSelasa2 = $this->buatSlot('Selasa', 2, '07:45');
        $slotSenin2Lain = JamPelajaran::resolveSlot(2, 'Senin', null, $this->tahun->id, true);
        $this->assertNotEquals($slotSelasa2->id, $slotSenin2Lain->id);
    }

    /**
     * A: Penyimpanan plotting baru memakai ID slot aktif hasil resolusi dinamis.
     */
    public function test_store_plotting_memakai_id_slot_aktif_hasil_resolve(): void
    {
        $this->recreateMasterJam();

        $slotAktif2 = JamPelajaran::where('hari', 'Senin')->where('jam_ke', 2)
            ->whereNull('deleted_at')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelas->id,
                'hari' => 'Senin',
                'id_mapel' => $this->mapel->id,
                'id_guru' => $this->guru->id,
                'jam_ke_mulai' => 2,
                'jam_ke_selesai' => 2,
            ])
            ->assertRedirect();

        $tersimpan = JadwalPelajaran::where('id_kelas', $this->kelas->id)
            ->where('hari', 'Senin')->firstOrFail();

        $this->assertEquals($slotAktif2->id, $tersimpan->id_jam);
        $this->assertNotNull(JamPelajaran::find($tersimpan->id_jam)->jam_ke);
    }

    /**
     * A: Update jadwal dengan `id_jam` basi (client kirim ID lama) tetap
     * menyimpan ke slot AKTIF yang benar berdasarkan jam_ke.
     */
    public function test_update_dengan_id_jam_basi_tetap_menyimpan_ke_slot_aktif(): void
    {
        $slotAktif1 = $this->buatSlot('Senin', 1, '07:00');
        $this->buatSlot('Senin', 2, '07:45');

        $jadwal = $this->buatJadwal($slotAktif1->id);

        // Master jam dihapus & dibuat ulang -> ID slot berubah total.
        $this->recreateMasterJam();

        $idJamBasi = $slotAktif1->id;

        // Client mengirim ID LAMA (form basi) + jam_ke target.
        $this->actingAs($this->admin)
            ->put(route('admin.jadwal.update', $jadwal->id), [
                'id_kelas' => $this->kelas->id,
                'hari' => 'Senin',
                'id_jam' => $idJamBasi,
                'jam_ke' => 2,
                'id_mapel' => $this->mapel->id,
                'id_guru' => $this->guru->id,
            ])
            ->assertSessionHasNoErrors();

        $tersimpan = $jadwal->fresh();

        $this->assertEquals(2, $tersimpan->jamPelajaran->jam_ke);
        $this->assertEquals(
            JamPelajaran::where('hari', 'Senin')->where('jam_ke', 2)->whereNull('deleted_at')->firstOrFail()->id,
            $tersimpan->id_jam,
            'Update harus menyimpan ID slot AKTIF hasil resolusi, bukan ID kiriman client.'
        );
        $this->assertNotEquals($idJamBasi, $tersimpan->id_jam);
        $this->assertNotNull($tersimpan->jamPelajaran);
    }

    /**
     * C: Resolver menandai jadwal yang benar-benar tidak punya slot sebagai "gantung"
     * tanpa membuang datanya.
     */
    public function test_jadwal_tanpa_padanan_slot_dipertahankan_sebagai_gantung(): void
    {
        // Slot jam ke-5 (hanya ada di master LAMA).
        $slotYatim = $this->buatSlot('Senin', 5, '10:00');
        $jadwal = $this->buatJadwal($slotYatim->id);

        // Master di-recreate menjadi hanya jam_ke 1..3 -> jam_ke 5 tak punya padanan.
        $this->recreateMasterJam();

        $hasil = JamSlotResolver::petakanJadwal(
            JadwalPelajaran::where('id_kelas', $this->kelas->id)->get(),
            'Senin',
            null,
            $this->tahun->id,
            true
        );

        $this->assertCount(1, $hasil['orphans'], 'Jadwal tanpa padanan slot harus ditandai gantung.');
        $this->assertCount(1, $hasil['mapped'], 'Jadwal gantung tetap dikembalikan agar tidak hilang dari tampilan.');

        // Data TIDAK dihapus — hanya ditandai supaya operator bisa plot ulang.
        $this->assertTrue($jadwal->fresh()->exists);
        $this->assertNull($jadwal->fresh()->deleted_at);
    }
}
