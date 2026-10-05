<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tabel "Monitoring Status KBM & Guru Hari Ini" pada Dashboard Waka SDM
 * sebelumnya merender SELURUH sesi (ratusan baris) sekaligus sehingga merusak
 * layout halaman. Perbaikan: 10 baris per halaman + link pagination.
 */
class WakaSdmDashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    /** ID guru yang dipakai oleh sesi hasil seedSesi(). */
    private ?int $guruId = null;

    private function wakaSdm(): User
    {
        return User::create([
            'nama' => 'Waka SDM Uji',
            'username' => 'waka_sdm_paginasi',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_sdm',
            'is_active' => true,
        ]);
    }

    private function guru(): User
    {
        return User::create([
            'nama' => 'Guru Paginasi',
            'username' => 'guru_paginasi',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
        ]);
    }

    private function hariIni(): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[Carbon::now()->format('l')] ?? 'Senin';
    }

    public function test_dashboard_memakai_visual_stat_card_dan_empty_state_modern(): void
    {
        $html = $this->actingAs($this->wakaSdm())
            ->get(route('waka-sdm.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bg-slate-50/70', $html);
        $this->assertSame(4, substr_count($html, 'shadow-sm hover:shadow-md transition-all p-4 sm:p-5'));
        $this->assertStringContainsString('bg-emerald-100 text-emerald-600', $html);
        $this->assertStringContainsString('bg-amber-100 text-amber-600', $html);
        $this->assertStringContainsString('bg-rose-100 text-rose-600', $html);
        $this->assertStringContainsString('bg-blue-100 text-blue-600', $html);
        $this->assertStringContainsString('bg-slate-200/60 p-1', $html);
        $this->assertStringContainsString('bg-white px-4 py-2 text-sm font-semibold text-blue-600 shadow-sm', $html);
        $this->assertStringContainsString('bg-emerald-50 text-emerald-700 border border-emerald-200', $html);
        $this->assertStringContainsString('bi-calendar2-check text-2xl', $html);
    }

    /**
     * Seed 25 sesi KBM pada hari ini.
     */
    private function seedSesi(int $jumlah = 25): void
    {
        $hari = $this->hariIni();
        $jurusan = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $guru = $this->guru();
        $this->guruId = $guru->id;

        foreach (range(1, $jumlah) as $i) {
            $jam = JamPelajaran::create([
                'hari' => $hari,
                'kategori_hari' => in_array($hari, ['Sabtu', 'Minggu'], false) ? $hari : 'Senin-Kamis',
                'jam_ke' => $i,
                'jam_mulai' => sprintf('%02d:%02d:00', 6 + intdiv($i, 4), ($i % 4) * 15),
                'jam_selesai' => sprintf('%02d:%02d:00', 6 + intdiv($i + 1, 4), (($i + 1) % 4) * 15),
                'jenis' => 'kbm',
            ]);

            $kelas = Kelas::create([
                'nama_kelas' => 'Kelas '.$i,
                'tingkat' => 'X',
                'id_jurusan' => $jurusan->id,
            ]);

            $mapel = MataPelajaran::create([
                'nama_mapel' => 'Mapel '.$i,
                'kode_mapel' => 'MP'.$i,
            ]);

            JadwalPelajaran::create([
                'group_id' => (string) Str::uuid(),
                'hari' => $hari,
                'id_jam' => $jam->id,
                'id_kelas' => $kelas->id,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
                'id_tahun_ajaran' => $tahun->id,
            ]);
        }
    }

    public function test_tabel_monitoring_dipotong_10_baris_per_halaman(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $response = $this->actingAs($waka)->get(route('waka-sdm.dashboard'));

        $response->assertOk();

        $mapelTampil = $this->mapelTerlihat($response->getContent());

        $this->assertCount(10, $mapelTampil, 'Halaman pertama harus tepat 10 baris monitoring.');
        $this->assertSame(range(1, 10), $mapelTampil);

        // Badge total tetap menampilkan JUMLAH SESI keseluruhan, bukan 10.
        $response->assertSee('Total: 25 Sesi');

        // Link pagination dirender.
        $response->assertSee('monitoring_page=2', false);
    }

    public function test_halaman_kedua_menampilkan_baris_berikutnya_dengan_nomor_lanjut(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $response = $this->actingAs($waka)
            ->get(route('waka-sdm.dashboard', ['monitoring_page' => 2]));

        $response->assertOk();

        $mapelTampil = $this->mapelTerlihat($response->getContent());

        $this->assertSame(range(11, 20), $mapelTampil, 'Halaman 2 harus_slice baris ke-11 s/d ke-20.');

        // Penomoran baris ikut berlanjut (firstItem + index), bukan selalu mulai 1.
        $this->assertMatchesRegularExpression(
            '/Menampilkan\s+11&ndash;20\s+dari\s+25\s+sesi/',
            $response->getContent()
        );
    }

    public function test_paginasi_tidak_mengganggu_paginasi_kartu_kelas_kosong(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        // Kartu "Kelas Kosong" memakai query param `page`, monitoring memakai
        // `monitoring_page`; keduanya harus bisa hidup berdampingan.
        $response = $this->actingAs($waka)->get(route('waka-sdm.dashboard', [
            'page' => 1,
            'monitoring_page' => 2,
        ]));

        $response->assertOk();
        $this->assertSame(range(11, 20), $this->mapelTerlihat($response->getContent()));
    }

    public function test_halaman_melebihi_jumlah_halaman_kosong_tidak_melempar(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        // `monitoring_page` di luar rentang harus tetap 200 (menampilkan state
        // kosong), tidak melempar error — perilaku ini sama dengan
        // LengthAwarePaginator bawaan Laravel.
        $response = $this->actingAs($waka)
            ->get(route('waka-sdm.dashboard', ['monitoring_page' => 99]));

        $response->assertOk()
            ->assertSee('Tidak ada jadwal KBM yang aktif untuk hari ini');

        $this->assertSame([], $this->mapelTerlihat($response->getContent()));
    }

    /**
     * Card "Pantau Kelas Kosong" harus TANPA pembatas tinggi & tanpa scrollbar
     * internal: `h-auto`, tidak ada max-h-*, dan tabelnya lega (text-sm /
     * px-4 py-2.5) karena card sudah full-width.
     */
    public function test_kartu_kelas_kosong_tanpa_pembatas_tinggi_dan_scroll(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($waka)->get(route('waka-sdm.dashboard'))->getContent()
        );

        // Wrapper card: tinggi otomatis, tidak dikunci.
        $this->assertStringContainsString(
            'rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col h-auto"',
            $kartu
        );

        // Tidak ada lagi pembatas tinggi maupun scroll VERTIKAL di card.
        $this->assertStringNotContainsString('h-[380px]', $kartu);
        $this->assertStringNotContainsString('max-h-', $kartu);
        $this->assertStringNotContainsString('overflow-y-auto', $kartu);
        $this->assertStringNotContainsString('min-h-0', $kartu);

        // Full-width: kolom lega, teks tetap satu baris.
        $this->assertStringContainsString('<table class="w-full text-sm">', $kartu);
        $this->assertStringContainsString('px-4 py-2.5', $kartu);
        $this->assertStringContainsString('text-xs text-slate-500 whitespace-nowrap"', $kartu);
        $this->assertStringContainsString('text-sm font-semibold text-slate-700 whitespace-nowrap"', $kartu);
    }

    /**
     * Layout grid harus STACKED full-width 1 kolom: "Guru Tidak Hadir" di atas,
     * "Pantau Kelas Kosong" di bawahnya, keduanya w-full.
     */
    public function test_grid_layout_stacked_satu_kolom_full_width(): void
    {
        $this->seedSesi(25);

        $html = $this->actingAs($this->wakaSdm())
            ->get(route('waka-sdm.dashboard'))->getContent();

        // Wrapper 1 kolom penuh, tanpa breakpoint 2 kolom.
        $this->assertStringContainsString('grid grid-cols-1 w-full gap-6 mb-6', $html);
        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('items-stretch', $html);

        // Kedua card full-width.
        $this->assertSame(
            2,
            substr_count($html, 'w-full bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col h-auto"')
        );

        // Urutan vertikal: Guru Tidak Hadir (atas) sebelum Pantauan Kelas Kosong (bawah).
        $guru = strpos($html, 'Guru Tidak Hadir / Izin Hari Ini');
        $kelasKosong = strpos($html, 'Pantauan Kelas Kosong (Jam Ini)');

        $this->assertNotFalse($guru);
        $this->assertNotFalse($kelasKosong);
        $this->assertLessThan($kelasKosong, $guru, 'Card Guru Tidak Hadir harus berada di ATAS.');
    }

    /**
     * Full-width membuat tabel lega: header kolom kembali ke label lengkap
     * dan badge jam kembali ke format "Jam Ke-N".
     */
    public function test_tabel_kelas_kosong_lega_di_full_width(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        foreach (['Jam / Sesi', 'Kelas &amp; Mapel', 'Guru Pengajar', 'Aksi Cepat'] as $header) {
            $this->assertStringContainsString('>'.$header.'<', $kartu, "Header kolom {$header} harus tampil.");
        }

        $this->assertStringContainsString('Jam Ke-1', $kartu);
    }

    /**
     * Paginasi 5 data per halaman: halaman 1 menampilkan sesi 1..5 tepat,
     * sesi ke-6 TIDAK ikut dirender (tidak ada baris tersembunyi).
     */
    public function test_paginasi_lima_data_per_halaman_tanpa_baris_ke_enam(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        // 5 baris saja di halaman pertama: sesi 1..5.
        $this->assertSame(range(1, 5), $this->barisKelasKosong($kartu));

        // Sesi ke-6 belum dirender — tidak ada yang "ngumpet" di scrollbar.
        $this->assertStringNotContainsString('Jam Ke-6', $kartu);

        // Indikator header: 25 sesi / 5 per halaman = 5 halaman.
        $this->assertMatchesRegularExpression('/>\s*1\/5\s*</', $kartu);
    }

    /**
     * Link pagination Laravel penuh harus dihapus dari card, dan digantikan
     * mini arrow prev/next di header.
     */
    public function test_pagination_full_dihapus_diganti_mini_arrow_di_header(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($waka)->get(route('waka-sdm.dashboard'))->getContent()
        );

        // Tidak ada lagi blok link pagination Laravel di dalam card.
        $this->assertStringNotContainsString('rel="next"', $kartu, 'Link pagination Laravel masih ada di card.');
        $this->assertStringNotContainsString('aria-label="Go to page 2"', $kartu);

        // Mini navigasi tetap ada: chevron kiri/kanan + indikator halaman.
        $this->assertStringContainsString('bi-chevron-left', $kartu);
        $this->assertStringContainsString('bi-chevron-right', $kartu);
        $this->assertMatchesRegularExpression('/>\s*1\/5\s*</', $kartu, 'Indikator halaman harus tampil di header.');

        // Tombol next membawa query param `page`.
        $this->assertMatchesRegularExpression(
            '/href="[^"]*[?&]page=2"/',
            $kartu,
            'Mini arrow harus menunjuk ke query param page=2.'
        );

        // Di halaman pertama, prev nonaktif; di halaman terakhir, next nonaktif.
        $this->assertStringContainsString('Sudah di halaman pertama', $kartu);
    }

    /**
     * Halaman terakhir: next nonaktif, prev aktif, dan tabelnya terpotong 5 baris.
     */
    public function test_halaman_terakhir_menonaktifkan_arrow_berikutnya(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($waka)->get(route('waka-sdm.dashboard', ['page' => 5]))->getContent()
        );

        $this->assertMatchesRegularExpression('/>\s*5\/5\s*</', $kartu);
        $this->assertStringContainsString('Sudah di halaman terakhir', $kartu);
        // 25 sesi / 5 per halaman = 5 halaman; baris pada halaman terakhir = 21..25.
        $this->assertCount(5, $this->barisKelasKosong($kartu));
    }

    /**
     * Cabang tombol ikon "Hubungi Piket" (guru tanpa no_hp) berupa icon button.
     */
    public function test_tombol_aksi_jadi_icon_button_ringkas(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        // Tanpa nomor HP -> tombol telepon ("Hubungi Piket") berupa icon button.
        $this->assertStringContainsString('w-7 h-7 rounded-lg bg-white border border-slate-200 text-amber-500', $kartu);
        $this->assertStringContainsString('bi-telephone-fill', $kartu);
        // Label panjang tidak lagi dirender sebagai teks tombol.
        $this->assertStringNotContainsString('>Hubungi Piket<', $kartu);
    }

    /**
     * Cabang tombol ikon WhatsApp (guru punya no_hp).
     */
    public function test_tombol_aksi_whatsapp_jadi_icon_button(): void
    {
        $this->seedSesi(25);
        User::findOrFail($this->guruId)->forceFill(['no_hp' => '081234567890'])->save();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        $this->assertStringContainsString('w-7 h-7 rounded-lg bg-emerald-500', $kartu);
        $this->assertStringContainsString('bi-whatsapp', $kartu);
        // Tooltip lewat atribut title.
        $this->assertMatchesRegularExpression('/title="Ingatkan [^"]+ via WhatsApp"/', $kartu);
        $this->assertStringNotContainsString('>Ingatkan</', $kartu);
    }

    /**
     * Pencarian berdasarkan NAMA GURU: semua 25 sesi punya guru yang sama,
     * jadi hasilnya tetap 25 sesi / 5 halaman.
     */
    public function test_search_menyaring_berdasarkan_nama_guru(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => 'Paginasi']))->getContent()
        );

        $this->assertSame(range(1, 5), $this->barisKelasKosong($kartu), 'Halaman 1 harus 5 sesi pertama.');
        $this->assertSame('1/5', $this->kartuAngkaHalaman($kartu));
        $this->assertStringContainsString('25 dari 25 Sesi Belum Diisi', $kartu);
    }

    /**
     * Pencarian berdasarkan NAMA MAPEL: "Mapel 3" hanya cocok dengan sesi 3.
     */
    public function test_search_menyaring_berdasarkan_nama_mapel(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => 'Mapel 3']))->getContent()
        );

        $this->assertSame([3], $this->barisKelasKosong($kartu));
        $this->assertSame(['Mapel 3'], $this->mapelKelasKosong($kartu));
        $this->assertStringContainsString('1 dari 25 Sesi Belum Diisi', $kartu);
    }

    /**
     * Pencarian kelas harus cocok dengan label yang BENAR-BENAR tampil di
     * tabel, yaitu `nama_kelas_lengkap` ("X Kelas 3"). `nama_kelas` sendiri
     * hanya berisi "3", jadi tanpa ini user tidak bisa mencari teks yang dia lihat.
     */
    public function test_search_kelas_cocok_dengan_label_lengkap_yang_ditampilkan(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => 'X Kelas 3']))->getContent()
        );

        $this->assertSame([3], $this->barisKelasKosong($kartu));
    }

    /**
     * Pencarian bersifat case-insensitive.
     */
    public function test_search_bersifat_case_insensitive(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => 'mApEl 3']))
                ->getContent()
        );

        $this->assertSame([3], $this->barisKelasKosong($kartu));
    }

    /**
     * Kata kunci kosong / hanya spasi dianggap "tidak mencari" — semua data
     * tampil dan badge tidak menampilkan format "X dari Y".
     */
    public function test_search_kosong_dianggap_tanpa_filter(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => '   ']))->getContent()
        );

        $this->assertSame(range(1, 5), $this->barisKelasKosong($kartu));
        $this->assertStringContainsString('25 Sesi Belum Diisi', $kartu);
        $this->assertStringNotContainsString('dari 25 Sesi Belum Diisi', $kartu);
    }

    /**
     * Pagination harus dihitung dari HASIL FILTER, bukan dari total data.
     *
     * "Kelas 1" cocok dengan sesi 1 dan 10..19 karena pencarian substring,
     * jadi 11 sesi = 3 halaman (5 + 5 + 1) — bukan 5 halaman seperti saat
     * tanpa filter.
     */
    public function test_paginasi_presisi_mengikuti_hasil_search(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();
        $url = route('waka-sdm.dashboard', ['q' => 'Kelas 1']);

        $halaman1 = $this->kartuKelasKosong($this->actingAs($waka)->get($url)->getContent());
        $halaman2 = $this->kartuKelasKosong(
            $this->actingAs($waka)->get($url.'&page=2')->getContent()
        );
        $halaman3 = $this->kartuKelasKosong(
            $this->actingAs($waka)->get($url.'&page=3')->getContent()
        );

        $this->assertSame([1, 10, 11, 12, 13], $this->barisKelasKosong($halaman1));
        $this->assertSame([14, 15, 16, 17, 18], $this->barisKelasKosong($halaman2));
        $this->assertSame([19], $this->barisKelasKosong($halaman3));

        $this->assertStringContainsString('11 dari 25 Sesi Belum Diisi', $halaman1);
        $this->assertStringContainsString('1/3', $this->kartuAngkaHalaman($halaman1));
        $this->assertStringContainsString('2/3', $this->kartuAngkaHalaman($halaman2));
        $this->assertStringContainsString('3/3', $this->kartuAngkaHalaman($halaman3));
    }

    /**
     * Pencarian hanya berlaku untuk card "Pantau Kelas Kosong"; tabel
     * monitoring di bawahnya harus tetap utuh (10 baris/halaman, dan
     * `monitoring_page` tidak ikut ter-reset oleh keyword pencarian).
     */
    public function test_search_tidak_mempengaruhi_paginasi_tabel_monitoring(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $html = $this->actingAs($waka)
            ->get(route('waka-sdm.dashboard', ['q' => 'Mapel 3', 'monitoring_page' => 2]))
            ->assertOk()
            ->getContent();

        // Card kelas kosong: tersaring jadi 1 sesi.
        $this->assertSame([3], $this->barisKelasKosong($this->kartuKelasKosong($html)));

        // Tabel monitoring: tidak terpengaruh filter, tetap 10 baris,
        // dan tetap di halaman 2 (sesi 11..20).
        $this->assertSame(range(11, 20), $this->mapelTerlihat($html));
    }

    /**
     * Hasil pencarian kosong harus punya empty state KHASUS. Kalau tidak,
     * user diberi tahu "semua sesi sudah terisi" padahal ada sesi kosong
     * yang hanya tidak cocok dengan kata kuncinya.
     */
    public function test_tanpa_hasil_search_menampilkan_empty_state_khusus(): void
    {
        $this->seedSesi(25);
        $waka = $this->wakaSdm();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($waka)
                ->get(route('waka-sdm.dashboard', ['q' => 'ZzzzTidakAda']))->getContent()
        );

        $this->assertStringContainsString('Tidak ada sesi yang cocok', $kartu);
        $this->assertStringContainsString('ZzzzTidakAda', $kartu);
        $this->assertStringContainsString('Dari 25 sesi belum diisi hari ini', $kartu);

        // PESAN "SEMUA SESI TERISI" TIDAK BOLEH muncul saat sedang mencari.
        $this->assertStringNotContainsString('Semua sesi KBM hari ini sudah terisi dengan baik', $kartu);

        // Halaman di luar jangkauan hasil filter tidak boleh melempar.
        $this->actingAs($waka)
            ->get(route('waka-sdm.dashboard', ['q' => 'ZzzzTidakAda', 'page' => 2]))
            ->assertOk();
    }

    /**
     * Form search sengaja TIDAK mengirim `page` supaya setiap pencarian baru
     * kembali ke halaman 1 — kalau tidak, user bisa mendarat di halaman 5
     * dari hasil lama dan melihat tabel kosong. Sebaliknya, link prev/next
     * dari mini arrow WAJIB membawa `q`.
     */
    public function test_form_search_tidak_membawa_param_page(): void
    {
        $this->seedSesi(25);

        $html = $this->actingAs($this->wakaSdm())
            ->get(route('waka-sdm.dashboard', ['q' => 'Kelas 1']))->getContent();

        $this->assertStringContainsString('action="'.route('waka-sdm.dashboard').'"', $html);
        $this->assertStringNotContainsString('name="page"', $html);

        // Link navigasi ikut membawa kata kunci.
        $kartu = $this->kartuKelasKosong($html);
        $this->assertStringContainsString('q=Kelas', $kartu);
    }

    /**
     * Kata kunci dari user masuk ke atribut `value` dan ke empty state,
     * jadi wajib ter-escape oleh Blade.
     */
    public function test_kata_kunci_search_terescap(): void
    {
        $this->seedSesi(25);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => '<img src=x onerror=alert(1)>']))
                ->getContent()
        );

        $this->assertStringNotContainsString('<img src=x', $kartu);
        $this->assertStringContainsString('&lt;img src=x', $kartu);
    }

    /**
     * Seed jadwal yang bisa di-grouping: tiap grup memakai kelas + mapel
     * sendiri dan menempati N sesi BERUNTUN tanpa jeda.
     *
     * Jeda antar grup dijamin oleh kelas yang berbeda tiap grup, sehingga
     * jumlah baris tabel = $jumlahGrup sedangkan jumlah sesi = hasil kali.
     * Jam sesi berurutan 40 menit mulai 07:00 (jam 1 = 07:00-07:40).
     */
    private function seedJadwalGroupable(int $jumlahGrup = 3, int $sesiPerGrup = 2): void
    {
        $hari = $this->hariIni();
        $jurusan = Jurusan::create(['nama_jurusan' => 'RPL', 'kode_jurusan' => 'RPL']);
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $guru = $this->guru();
        $acuan = Carbon::parse(Carbon::now()->format('Y-m-d').' 07:00:00');
        $jamKe = 0;

        for ($g = 1; $g <= $jumlahGrup; $g++) {
            $kelas = Kelas::create([
                'nama_kelas' => $g.'A',
                'tingkat' => 'XI',
                'id_jurusan' => $jurusan->id,
            ]);
            $mapel = MataPelajaran::create([
                'nama_mapel' => 'Mapel '.$g,
                'kode_mapel' => 'MP'.$g,
            ]);

            for ($s = 0; $s < $sesiPerGrup; $s++) {
                $jamKe++;

                $jam = JamPelajaran::create([
                    'hari' => $hari,
                    'kategori_hari' => in_array($hari, ['Sabtu', 'Minggu'], false) ? $hari : 'Senin-Kamis',
                    'jam_ke' => $jamKe,
                    'jam_mulai' => $acuan->copy()->addMinutes(40 * ($jamKe - 1))->format('H:i:s'),
                    'jam_selesai' => $acuan->copy()->addMinutes(40 * $jamKe)->format('H:i:s'),
                    'jenis' => 'kbm',
                ]);

                JadwalPelajaran::create([
                    'group_id' => (string) Str::uuid(),
                    'hari' => $hari,
                    'id_jam' => $jam->id,
                    'id_kelas' => $kelas->id,
                    'id_mapel' => $mapel->id,
                    'id_guru' => $guru->id,
                    'id_tahun_ajaran' => $tahun->id,
                ]);
            }
        }
    }

    /**
     * Seed dengan pola "terpisah": guru + kelas + mapel yang sama muncul dua
     * kali, DIPISAH oleh satu sesi milik guru lain.
     *
     *   jam 1  Guru A / Kelas 1 / Mapel 1  ┐ blok 1
     *   jam 2  Guru A / Kelas 1 / Mapel 1  ┘
     *   jam 3  Guru B / Kelas 2 / Mapel 2  <- pembatas
     *   jam 4  Guru A / Kelas 1 / Mapel 1  ┐ blok 2
     *   jam 5  Guru A / Kelas 1 / Mapel 1  ┘
     *
     * Kalau grouping hanya membandingkan kunci tanpa mempertahankan urutan,
     * blok 1 dan blok 2 akan salah menyatu jadi "Jam Ke-1 - 5" padahal
     * jam 3 milik guru lain.
     */
    private function seedJadwalTerpisah(): void
    {
        $hari = $this->hariIni();
        $jurusan = Jurusan::create(['nama_jurusan' => 'RPL', 'kode_jurusan' => 'RPL']);
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $guruA = $this->guru();
        $this->guruId = $guruA->id;
        $guruB = User::create([
            'nama' => 'Guru Kedua',
            'username' => 'guru_kedua',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
        ]);

        $kelasA = Kelas::create(['nama_kelas' => '1', 'tingkat' => 'XI', 'id_jurusan' => $jurusan->id]);
        $kelasB = Kelas::create(['nama_kelas' => '2', 'tingkat' => 'XI', 'id_jurusan' => $jurusan->id]);
        $mapelA = MataPelajaran::create(['nama_mapel' => 'KKPI', 'kode_mapel' => 'KKPI']);
        $mapelB = MataPelajaran::create(['nama_mapel' => 'Informatika', 'kode_mapel' => 'INF']);

        $rencana = [
            [1, $guruA, $kelasA, $mapelA],
            [2, $guruA, $kelasA, $mapelA],
            [3, $guruB, $kelasB, $mapelB],
            [4, $guruA, $kelasA, $mapelA],
            [5, $guruA, $kelasA, $mapelA],
        ];

        $acuan = Carbon::parse(Carbon::now()->format('Y-m-d').' 07:00:00');

        foreach ($rencana as [$jamKe, $guru, $kelas, $mapel]) {
            $jam = JamPelajaran::create([
                'hari' => $hari,
                'kategori_hari' => in_array($hari, ['Sabtu', 'Minggu'], false) ? $hari : 'Senin-Kamis',
                'jam_ke' => $jamKe,
                'jam_mulai' => $acuan->copy()->addMinutes(40 * ($jamKe - 1))->format('H:i:s'),
                'jam_selesai' => $acuan->copy()->addMinutes(40 * $jamKe)->format('H:i:s'),
                'jenis' => 'kbm',
            ]);

            JadwalPelajaran::create([
                'group_id' => (string) Str::uuid(),
                'hari' => $hari,
                'id_jam' => $jam->id,
                'id_kelas' => $kelas->id,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
                'id_tahun_ajaran' => $tahun->id,
            ]);
        }
    }

    /**
     * Sesi beruntun dengan guru + kelas + mapel sama harus menjadi SATU baris.
     * 6 sesi pada 3 grup -> 3 baris, bukan 6.
     */
    public function test_sesi_beruntun_digabung_jadi_satu_baris(): void
    {
        $this->seedJadwalGroupable(3, 2);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        $this->assertSame(['1 - 2', '3 - 4', '5 - 6'], $this->labelJamKelasKosong($kartu));
        $this->assertSame([1, 3, 5], $this->barisKelasKosong($kartu));

        // Info jumlah sesi yang digabung.
        $this->assertSame(3, substr_count($kartu, 'sesi digabung'));
    }

    /**
     * Kolom waktu menjumlahkan sesi: mulai paling awal + selesai paling akhir.
     * jam 1-2 = 07:00-08:20, jam 3-4 = 08:20-09:40, jam 5-6 = 09:40-11:00.
     */
    public function test_kolom_waktu_menjumlah_mulai_terawal_sampai_selesai_terakhir(): void
    {
        $this->seedJadwalGroupable(3, 2);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        $this->assertStringContainsString('07:00 - 08:20', $kartu);
        $this->assertStringContainsString('08:20 - 09:40', $kartu);
        $this->assertStringContainsString('09:40 - 11:00', $kartu);
    }

    /**
     * Ini inti aturan "berurutan": blok yang dipisah sesi milik guru lain
     * TIDAK boleh menyatu, walau kunci (guru/kelas/mapel)-nya sama.
     */
    public function test_sesi_yang_tidak_beruntun_tidak_digabung(): void
    {
        $this->seedJadwalTerpisah();

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        $this->assertSame(['1 - 2', '3', '4 - 5'], $this->labelJamKelasKosong($kartu));

        // Yang salah kalau urutan diabaikan: dua blok menyatu jadi "1 - 5".
        $this->assertStringNotContainsString('Jam Ke-1 - 5', $kartu);
    }

    /**
     * Badge total harus menghitung SESI (JP), bukan jumlah baris setelah
     * grouping. 24 sesi diringkas jadi 12 baris, badge tetap 24.
     */
    public function test_badge_menghitung_total_sesi_bukan_jumlah_baris(): void
    {
        $this->seedJadwalGroupable(12, 2);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        );

        $this->assertStringContainsString('24 Sesi Belum Diisi', $kartu);
        $this->assertStringNotContainsString('12 Sesi Belum Diisi', $kartu);

        // Ringkasan di subtitle memakai angka sesi DAN jumlah baris.
        $this->assertStringContainsString('24 sesi diringkas jadi 12 baris', $kartu);
    }

    /**
     * Paginasi 5 baris/halaman berlaku atas baris SUDAH digabung.
     * 12 baris -> 3 halaman (5 + 5 + 2).
     */
    public function test_paginasi_berlaku_atas_baris_yang_sudah_digabung(): void
    {
        $this->seedJadwalGroupable(12, 2);
        $waka = $this->wakaSdm();
        $url = route('waka-sdm.dashboard');

        $halaman1 = $this->kartuKelasKosong($this->actingAs($waka)->get($url)->getContent());
        $halaman2 = $this->kartuKelasKosong($this->actingAs($waka)->get($url.'?page=2')->getContent());
        $halaman3 = $this->kartuKelasKosong($this->actingAs($waka)->get($url.'?page=3')->getContent());

        // Grup ke-g menempati sesi (2g-1, 2g) -> label "2g-1 - 2g".
        $this->assertSame(['1 - 2', '3 - 4', '5 - 6', '7 - 8', '9 - 10'], $this->labelJamKelasKosong($halaman1));
        $this->assertSame(['11 - 12', '13 - 14', '15 - 16', '17 - 18', '19 - 20'], $this->labelJamKelasKosong($halaman2));
        $this->assertSame(['21 - 22', '23 - 24'], $this->labelJamKelasKosong($halaman3));

        $this->assertSame('1/3', $this->kartuAngkaHalaman($halaman1));
        $this->assertSame('3/3', $this->kartuAngkaHalaman($halaman3));
    }

    /**
     * Grouping hanya berlaku di card "Pantau Kelas Kosong". Tabel monitoring
     * harus tetap menampilkan seluruh sesi tanpa digabung.
     */
    public function test_grouping_tidak_mempengaruhi_tabel_monitoring(): void
    {
        $this->seedJadwalGroupable(3, 2);

        $html = $this->actingAs($this->wakaSdm())
            ->get(route('waka-sdm.dashboard'))->assertOk()->getContent();

        $this->assertSame(3, count($this->labelJamKelasKosong($this->kartuKelasKosong($html))));

        // Monitoring: 6 sesi utuh, TIDAK diringkas jadi 3.
        $this->assertSame(
            [1, 1, 2, 2, 3, 3],
            $this->mapelTerlihat($html)
        );
    }

    /**
     * Pencarian tetap jalan di atas baris yang sudah digabung: "Mapel 2"
     * menyeleksi grup kedua utuh (2 sesi), bukan separuh barisnya.
     */
    public function test_search_berjalan_di_atas_baris_yang_sudah_digabung(): void
    {
        $this->seedJadwalGroupable(3, 2);

        $kartu = $this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())
                ->get(route('waka-sdm.dashboard', ['q' => 'Mapel 2']))->getContent()
        );

        $this->assertSame(['3 - 4'], $this->labelJamKelasKosong($kartu));
        $this->assertStringContainsString('2 dari 6 Sesi Belum Diisi', $kartu);
    }

    /**
     * Pesan WhatsApp harus menyebut rentang jam yang digabung ("Jam ke-1-2"),
     * dan baris sesi tunggal tidak boleh jadi "Jam ke-3-3".
     */
    public function test_pesan_whatsapp_menyebut_rentang_jam_yang_digabung(): void
    {
        $this->seedJadwalTerpisah();

        User::where('username', 'guru_paginasi')->update(['no_hp' => '081234567890']);
        User::where('username', 'guru_kedua')->update(['no_hp' => '081298765432']);

        $kartu = urldecode($this->kartuKelasKosong(
            $this->actingAs($this->wakaSdm())->get(route('waka-sdm.dashboard'))->getContent()
        ));

        $this->assertStringContainsString('(Jam ke-1-2)', $kartu);
        $this->assertStringContainsString('(Jam ke-4-5)', $kartu);
        // Sesi tunggal tetap polos, bukan "3-3".
        $this->assertStringContainsString('(Jam ke-3)', $kartu);
        $this->assertStringNotContainsString('3-3', $kartu);
    }

    /**
     * Ambil indikator "halaman saat ini / total halaman" dari mini arrow,
    /**
     * Ambil indikator "halaman saat ini / total halaman" dari mini arrow,
     * mis. "1/5", supaya assertion tidak ikut cocok dengan angka lain.
     */
    private function kartuAngkaHalaman(string $kartu): string
    {
        preg_match('/tabular-nums px-1 whitespace-nowrap">\s*(\d+\/\d+)\s*</', $kartu, $matches);

        return $matches[1] ?? '';
    }

    /**
     * Ambil nama mapel yang tampil di card kelas kosong, urut baris.
     * Diambil dari atribut `title` sel mapel.
     */
    private function mapelKelasKosong(string $kartu): array
    {
        preg_match_all('/title="(Mapel \d+)"/', $kartu, $matches);

        return $matches[1];
    }

    /**
     * Potong HTML card "Pantau Kelas Kosong" saja — mulai dari tag pembuka
     * wrapper card (supaya assertion pada class wrapper ikut tercover) sampai
     * sebelum section tabel monitoring, supaya tidak ikut cocok dengan card
     * lain seperti tabel monitoring di bawahnya.
     */
    private function kartuKelasKosong(string $html): string
    {
        $judul = strpos($html, 'Pantauan Kelas Kosong (Jam Ini)');
        $this->assertNotFalse($judul, 'Judul card kelas kosong tidak ditemukan.');

        // Wrapper card = kemunculan terakhir class card ini sebelum judul.
        $wrapper = 'rounded-xl shadow-sm overflow-hidden flex flex-col';
        $mulai = strrpos(substr($html, 0, $judul), $wrapper);
        $this->assertNotFalse($mulai, 'Wrapper card kelas kosong tidak ditemukan.');

        $akhir = strpos($html, 'id="tabelMonitoringLengkap"', $judul);
        $this->assertNotFalse($akhir, 'Anchor tabel monitoring tidak ditemukan.');

        return substr($html, $mulai, $akhir - $mulai);
    }

    /**
     * Ambil nomor jam sesi yang tampil di card kelas kosong, urut.
     */
    private function barisKelasKosong(string $kartu): array
    {
        preg_match_all('/text-xs font-bold">\s*Jam Ke-(\d+)/', $kartu, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * Ambil label jam PERSIS seperti yang dirender, mis. "1 - 4" untuk baris
     * hasil grouping dan "3" untuk baris sesi tunggal.
     */
    private function labelJamKelasKosong(string $kartu): array
    {
        preg_match_all('/text-xs font-bold">\s*Jam Ke-(\d+(?:\s*-\s*\d+)?)/', $kartu, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * Ambil nomor mapel yang tampil DI DALAM tabel monitoring saja, urut.
     *
     * Card "Pantau Kelas Kosong" di atas juga menampilkan nama mapel, jadi
     * seluruh HTML tidak boleh dipakai: potong dari anchor tabel monitoring
     * sampai penanda tabel berikutnya ("Pengajuan Izin").
     */
    private function mapelTerlihat(string $html): array
    {
        $mulai = strpos($html, 'id="tabelMonitoringLengkap"');
        $this->assertNotFalse($mulai, 'Anchor tabel monitoring tidak ditemukan di HTML.');

        $akhir = strpos($html, 'Pengajuan Izin', $mulai);
        $bagian = $akhir === false
            ? substr($html, $mulai)
            : substr($html, $mulai, $akhir - $mulai);

        preg_match_all('/>\s*Mapel (\d+)\s*</', $bagian, $matches);

        return array_map('intval', $matches[1]);
    }
}
