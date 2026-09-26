<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\Scopes\ActiveTahunAjaranScope;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur Konteks Tahun Ajaran & Semester pada Master Jam Pelajaran:
 *  - Selector "Tahun Ajaran & Semester" (default: Tahun Ajaran aktif).
 *  - Setiap slot jam (Global maupun Shift) terikat ke tahun_ajaran_id.
 *  - Tabel master difilter sesuai Tahun Ajaran terpilih (arsip tersimpan, tidak tertimpa).
 *  - Slot legacy (sebelum fitur TA) tampil di TA aktif & konsumen runtime,
 *    tersembunyi saat melihat TA arsip.
 *  - "Salin dari Semester Lalu" (bonus): mengisi TA kosong dari TA sebelumnya.
 */
class JamPelajaranTahunAjaranTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin TA',
            'username' => 'adminta',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function makeTahunAjaran(string $tahun, string $semester, bool $active = false): TahunAjaran
    {
        return TahunAjaran::create([
            'tahun_ajaran' => $tahun,
            'semester' => $semester,
            'is_active' => $active,
        ]);
    }

    private function makeSlot(string $hari = 'Senin', string $mulai = '07:00', string $selesai = '07:45', array $extra = []): JamPelajaran
    {
        $kategori = in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';

        return JamPelajaran::create(array_merge([
            'hari' => $hari,
            'kategori_hari' => $kategori,
            'jam_ke' => 1,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'kbm',
        ], $extra));
    }

    public function test_index_defaults_to_selected_active_tahun_ajaran(): void
    {
        $lama = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $aktif = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index'));

        $response->assertOk();

        // Dropdown menampilkan seluruh Tahun Ajaran dengan label tahun – semester.
        $response->assertSee('2024/2025 – Ganjil', false);
        $response->assertSee('2025/2026 – Genap (Aktif)', false);

        // Opsi TA aktif terpilih (attribute selected) sebagai default.
        $content = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="[^"]*ta='.$aktif->id.'"[^>]*selected/',
            $content,
            'Tahun Ajaran aktif harus terpilih sebagai default pada dropdown.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="[^"]*ta='.$lama->id.'"[^>]*selected/',
            $content,
            'Tahun Ajaran non-aktif tidak boleh menjadi default.'
        );

        // Badge konteks TA pada header kartu.
        $response->assertSee('2025/2026 – Genap (Aktif)', false);
    }

    public function test_store_tags_slot_with_selected_tahun_ajaran(): void
    {
        $arsip = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.store'), [
                'kategori_hari' => 'Senin-Kamis',
                'jam_mulai' => '07:00',
                'jam_selesai' => '07:45',
                'jenis' => 'kbm',
                'ta' => $arsip->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            $this->assertDatabaseHas('jam_pelajaran', [
                'hari' => $hari,
                'tahun_ajaran_id' => $arsip->id,
                'jam_mulai' => '07:00',
            ]);
        }
    }

    public function test_store_defaults_to_active_tahun_ajaran_when_ta_not_provided(): void
    {
        $aktif = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.store'), [
                'kategori_hari' => 'Jumat',
                'jam_mulai' => '07:00',
                'jam_selesai' => '07:45',
                'jenis' => 'kbm',
            ]);

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Jumat',
            'tahun_ajaran_id' => $aktif->id,
        ]);
    }

    public function test_index_filters_slots_by_selected_tahun_ajaran(): void
    {
        $arsip = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $aktif = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        // Slot legacy (tanpa TA), slot TA aktif, dan slot TA arsip.
        $this->makeSlot('Senin', '07:00', '07:45');                                     // legacy
        $this->makeSlot('Senin', '07:00', '07:45', ['tahun_ajaran_id' => $aktif->id]);  // TA aktif
        $this->makeSlot('Senin', '09:00', '09:45', ['tahun_ajaran_id' => $arsip->id]);  // TA arsip

        // Konteks TA arsip: hanya slot arsip yang tampil — legacy & TA aktif disembunyikan.
        $r = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['ta' => $arsip->id]));
        $r->assertOk();
        $r->assertSee('09.00 – 09.45');
        $r->assertDontSee('07.00 – 07.45');

        // Konteks TA aktif: slot TA aktif + legacy tampil; slot arsip tidak.
        $r2 = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['ta' => $aktif->id]));
        $r2->assertOk();
        $r2->assertSee('07.00 – 07.45');
        $r2->assertDontSee('09.00 – 09.45');
    }

    public function test_runtime_global_scope_hides_archived_slots_and_keeps_active_and_legacy(): void
    {
        $arsip = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $aktif = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $legacy = $this->makeSlot('Senin', '07:00', '07:45');
        $activeSlot = $this->makeSlot('Senin', '08:00', '08:45', ['tahun_ajaran_id' => $aktif->id]);
        $archivedSlot = $this->makeSlot('Senin', '09:00', '09:45', ['tahun_ajaran_id' => $arsip->id]);

        // Query model (global scope aktif): hanya slot TA aktif + legacy.
        $visible = JamPelajaran::where('hari', 'Senin')->pluck('id')->all();

        $this->assertContains($legacy->id, $visible);
        $this->assertContains($activeSlot->id, $visible);
        $this->assertNotContains($archivedSlot->id, $visible);

        // Tanpa global scope, seluruh slot terlihat (termasuk arsip).
        $all = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('hari', 'Senin')
            ->pluck('id')
            ->all();
        $this->assertContains($archivedSlot->id, $all);
    }

    public function test_copy_from_previous_populates_empty_tahun_ajaran(): void
    {
        $lama = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $baru = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $this->makeSlot('Senin', '07:00', '07:45', ['tahun_ajaran_id' => $lama->id]);
        $this->makeSlot('Jumat', '07:00', '07:45', ['tahun_ajaran_id' => $lama->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.copy-from', ['ta' => $baru->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Senin',
            'tahun_ajaran_id' => $baru->id,
            'jam_mulai' => '07:00',
            'jenis' => 'kbm',
        ]);
        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Jumat',
            'tahun_ajaran_id' => $baru->id,
            'jam_mulai' => '07:00',
            'jenis' => 'kbm',
        ]);
    }

    public function test_copy_from_refuses_when_target_not_empty(): void
    {
        $lama = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $baru = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $this->makeSlot('Senin', '07:00', '07:45', ['tahun_ajaran_id' => $lama->id]);
        $this->makeSlot('Senin', '07:00', '07:45', ['tahun_ajaran_id' => $baru->id]);
        $this->makeSlot('Jumat', '07:00', '07:45', ['tahun_ajaran_id' => $baru->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.copy-from', ['ta' => $baru->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Tidak ada slot baru yang ditambahkan ke TA tujuan.
        $this->assertSame(
            3,
            JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)->count()
        );
    }

    public function test_copy_from_falls_back_to_legacy_slots_when_previous_tahun_ajaran_empty(): void
    {
        $lama = $this->makeTahunAjaran('2024/2025', 'Ganjil'); // kosong
        $baru = $this->makeTahunAjaran('2025/2026', 'Genap', false); // target arsip, kosong

        // Slot legacy (era sebelum fitur TA).
        $this->makeSlot('Senin', '07:00', '07:45');
        $this->makeSlot('Jumat', '07:30', '08:15');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.copy-from', ['ta' => $baru->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Senin',
            'tahun_ajaran_id' => $baru->id,
            'jam_mulai' => '07:00',
        ]);
        $this->assertDatabaseHas('jam_pelajaran', [
            'hari' => 'Jumat',
            'tahun_ajaran_id' => $baru->id,
            'jam_mulai' => '07:30',
        ]);
    }

    public function test_copy_button_visible_only_when_selected_tahun_ajaran_is_empty(): void
    {
        $lama = $this->makeTahunAjaran('2024/2025', 'Ganjil');
        $baru = $this->makeTahunAjaran('2025/2026', 'Genap', true);

        $this->makeSlot('Senin', '07:00', '07:45', ['tahun_ajaran_id' => $lama->id]);

        // TA tujuan masih kosong -> tombol "Salin dari Semester Lalu" tampil.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['ta' => $baru->id]));
        $response->assertOk();
        $response->assertSee('Salin dari Semester Lalu');

        // Setelah TA tujuan berisi slot, tombol disembunyikan.
        $this->makeSlot('Senin', '08:00', '08:45', ['tahun_ajaran_id' => $baru->id]);

        $response2 = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['ta' => $baru->id]));
        $response2->assertOk();
        $response2->assertDontSee('Salin dari Semester Lalu');
    }
}