<?php

namespace Tests\Feature;

use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DispensasiKolektifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 14)); // Senin 00:00
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    protected function buatKelasDanSiswa(int $jumlah = 2): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'XI IPA 1', 'tingkat' => 'XI']);

        $siswa = collect();
        foreach (range(1, $jumlah) as $i) {
            $siswa->push(Siswa::create([
                'nama' => "Siswa Kolektif {$i}",
                'nisn' => '99887700'.sprintf('%02d', $i),
                'nis' => '9988'.sprintf('%02d', $i),
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelas->id,
                'status_siswa' => 'Aktif',
            ]));
        }

        return [$kelas, $siswa];
    }

    public function test_store_kolektif_menyimpan_parent_dan_anak_dengan_ttd_per_siswa(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id, $siswa[1]->id],
                'jam_ke' => ['3', '4'],
                'jam_keluar_jp' => '3',
                'alasan' => 'Mengikuti lomba Paskibraka tingkat kabupaten',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => ['data:image/png;base64,TTD_SISWA_1', 'data:image/png;base64,TTD_SISWA_2'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('piket.dispensasi.kolektif.surat', DispensasiKolektif::first()->id))
            ->assertSessionHas('success');

        $this->assertSame(1, DispensasiKolektif::count());
        $kolektif = DispensasiKolektif::first();

        $this->assertSame($piket->id, $kolektif->id_guru_piket);
        $this->assertSame('2026-09-14', $kolektif->tanggal->toDateString());
        $this->assertSame('disetujui', $kolektif->status);
        $this->assertSame('data:image/png;base64,GURU_PIKET', $kolektif->ttd_guru);
        $this->assertNotNull($kolektif->approval_token);

        $this->assertSame(2, DispensasiSiswa::count());
        $anak = DispensasiSiswa::orderBy('id')->get();
        $this->assertSame($kolektif->id, (int) $anak[0]->dispensasi_kolektif_id);
        $this->assertSame($kolektif->id, (int) $anak[1]->dispensasi_kolektif_id);
        $this->assertTrue($anak[0]->is_kolektif);
        $this->assertTrue($anak[1]->is_kolektif);

        $this->assertSame($siswa[0]->id, $anak[0]->id_siswa);
        $this->assertSame('data:image/png;base64,TTD_SISWA_1', $anak[0]->ttd_siswa);
        $this->assertSame($siswa[1]->id, $anak[1]->id_siswa);
        $this->assertSame('data:image/png;base64,TTD_SISWA_2', $anak[1]->ttd_siswa);
    }

    public function test_surat_kolektif_menampilkan_semua_siswa_beserta_ttd(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $kolektif = DispensasiKolektif::create([
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3,4',
            'jam_keluar_jp' => 3,
            'tidak_kembali_hari_ini' => true,
            'alasan' => 'Mengikuti kegiatan ekstrakurikuler',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $piket->id,
            'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            'approval_token' => 'token-'.Str::uuid(),
        ]);

        foreach ($siswa as $i => $s) {
            DispensasiSiswa::create([
                'dispensasi_kolektif_id' => $kolektif->id,
                'id_siswa' => $s->id,
                'id_guru_piket' => $piket->id,
                'tanggal' => now()->toDateString(),
                'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
                'jam_ke' => '3,4',
                'jam_keluar_jp' => 3,
                'tidak_kembali_hari_ini' => true,
                'alasan' => 'Mengikuti kegiatan ekstrakurikuler',
                'status' => DispensasiSiswa::STATUS_DISETUJUI,
                'approved_at' => now(),
                'approved_by' => $piket->id,
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => 'data:image/png;base64,TTS_SISWA_'.($i + 1),
                'approval_token' => 'token-'.Str::uuid(),
            ]);
        }

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.kolektif.surat', $kolektif->id))
            ->assertOk()
            ->assertSee('Siswa Kolektif 1')
            ->assertSee('Siswa Kolektif 2')
            ->assertSee('XI IPA 1')
            ->assertSee('TTS_SISWA_1')
            ->assertSee('TTS_SISWA_2');
    }

    public function test_store_kolektif_wajib_ttd_digital_setiap_siswa(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id, $siswa[1]->id],
                'jam_ke' => ['3'],
                'jam_keluar_jp' => '3',
                'alasan' => 'Tanpa TTD salah satu siswa',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => ['data:image/png;base64,TTD_SISWA_1', 'bukan_data_url'],
            ])
            ->assertSessionHasErrors('ttd_siswa');

        $this->assertSame(0, DispensasiKolektif::count());
        $this->assertSame(0, DispensasiSiswa::count());
    }

    public function test_wajib_pilih_minimal_satu_siswa(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [],
                'jam_ke' => ['3'],
                'jam_keluar_jp' => '3',
                'alasan' => 'Tanpa siswa',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => [],
            ])
            ->assertSessionHasErrors('id_siswa');

        $this->assertSame(0, DispensasiKolektif::count());
        $this->assertSame(0, DispensasiSiswa::count());
    }

    public function test_satu_siswa_tetap_alur_tunggal_tanpa_parent_kolektif(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id],
                'jam_ke' => ['3'],
                'jam_keluar_jp' => '3',
                'alasan' => 'Keperluan keluarga',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('piket.dispensasi.ttd', DispensasiSiswa::first()->id));

        $this->assertSame(0, DispensasiKolektif::count());
        $this->assertSame(1, DispensasiSiswa::count());
        $this->assertNull(DispensasiSiswa::first()->dispensasi_kolektif_id);
        $this->assertFalse(DispensasiSiswa::first()->is_kolektif);
    }

    public function test_halaman_create_merender_form_multi_siswa_kolektif(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.create'))
            ->assertOk()
            ->assertSee('Tambah Siswa')
            ->assertSee('templateSiswaRow')
            ->assertSee('ttdWizardModal')
            ->assertSee('canvasTtdWizard')
            ->assertSee('ttd_siswa[]');
    }

    public function test_surat_kolektif_data_testing_terbuka_untuk_petugas_it_tidak_404(): void
    {
        $it = $this->makeUser('petugas_it');

        // Auth IT sejak awal agar kelas/siswa ikut ditandai sebagai data testing
        // (konsisten dengan bucket isTestingUser di TestingDataScope).
        $this->actingAs($it);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);
        $this->assertTrue((bool) $siswa[0]->is_testing_data);

        // Auth IT + HasTestingData -> is_testing_data otomatis true.
        $kolektif = $this->createKolektif($it, $siswa[0]->id, ['siswa_ids' => [$siswa[0]->id, $siswa[1]->id]]);
        $this->assertTrue((bool) $kolektif->is_testing_data);

        // Masalah yang dilaporkan: IT/QA membuka surat kolektif data testing malah 404.
        $this->actingAs($it)
            ->get(route('piket.dispensasi.kolektif.surat', $kolektif->id))
            ->assertOk()
            ->assertSee('Siswa Kolektif 1')
            ->assertSee('Siswa Kolektif 2')
            ->assertSee('XI IPA 1');
    }

    public function test_halaman_index_menampilkan_daftar_kolektif_dan_link_surat_rombongan(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(2);

        $kolektif = $this->createKolektif($piket, $siswa[0]->id, ['siswa_ids' => [$siswa[0]->id, $siswa[1]->id]]);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('Daftar Dispensasi')
            ->assertDontSee('Dispensasi Rombongan (Kolektif)')
            ->assertSee('Kolektif')
            ->assertSee('2 Siswa')
            ->assertSee(route('piket.dispensasi.kolektif.surat', $kolektif->id));
    }

    public function test_index_menyatukan_individu_dan_kolektif_dalam_satu_tabel_terurut(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(3);

        // Kolektif dibuat lebih dulu (00:00), lalu individu menyusul (00:01).
        $kolektif = $this->createKolektif($piket, $siswa[0]->id, ['siswa_ids' => [$siswa[0]->id, $siswa[1]->id, $siswa[2]->id]]);

        $this->travelTo(Carbon::create(2026, 9, 14, 0, 1));
        $individu = DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_keluar_jp' => 3,
            'alasan' => 'Urusan keluarga',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,TEST',
            'approval_token' => 'token-'.Str::uuid(),
        ]);

        $response = $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('Daftar Dispensasi')
            ->assertDontSee('Dispensasi Rombongan (Kolektif)')
            ->assertSee('Individu')
            ->assertSee('Kolektif')
            ->assertSee('3 Siswa')
            ->assertSee('XI IPA 1')
            ->assertSee(route('piket.dispensasi.surat', $individu->id))
            ->assertSee(route('piket.dispensasi.kolektif.surat', $kolektif->id));

        // Modal QR Approval: SVG QR ter-render inline lengkap & tombol salin link.
        $response
            ->assertSee('qr-svg')
            ->assertSee('width="200" height="200"', false)
            ->assertSee('data-copy-url=', false);

        // Individu (lebih baru) tampil lebih dulu sebelum baris kolektif pada 1 tabel.
        $response->assertSeeInOrder(['Siswa Kolektif 1', '3 Siswa']);
    }

    protected function createKolektif($user, $siswaId, array $extra = [])
    {
        $kolektif = DispensasiKolektif::create(array_merge([
            'id_guru_piket' => $user->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'jam_keluar_jp' => 3,
            'tidak_kembali_hari_ini' => true,
            'alasan' => 'Mengikuti kegiatan ekstrakurikuler',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            'approval_token' => 'token-'.Str::uuid(),
        ], $extra['parent'] ?? []));

        $siswaIds = $extra['siswa_ids'] ?? [$siswaId];
        foreach ($siswaIds as $i => $idSiswa) {
            $kolektif->siswaItems()->create([
                'id_siswa' => $idSiswa,
                'id_guru_piket' => $user->id,
                'tanggal' => now()->toDateString(),
                'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
                'jam_ke' => '3',
                'jam_keluar_jp' => 3,
                'tidak_kembali_hari_ini' => true,
                'alasan' => 'Mengikuti kegiatan ekstrakurikuler',
                'status' => DispensasiSiswa::STATUS_DISETUJUI,
                'approved_at' => now(),
                'approved_by' => $user->id,
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => 'data:image/png;base64,TTS_SISWA_'.($i + 1),
                'approval_token' => 'token-'.Str::uuid(),
            ]);
        }

        return $kolektif;
    }
}
