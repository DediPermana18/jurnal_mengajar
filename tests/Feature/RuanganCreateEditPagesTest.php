<?php

namespace Tests\Feature;

use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuanganCreateEditPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_menampilkan_form_lengkap_dengan_picker_pengurus(): void
    {
        $admin = $this->createUser('admin_tu', 'admin_tu_ruangan_create');
        $this->createUser('guru', 'guru_ruangan_create');

        $response = $this->actingAs($admin)
            ->get(route('ruangan.create'))
            ->assertOk();

        $response
            // Halaman dedicated (bukan modal) dengan layout utama.
            ->assertSee('Tambah Ruangan')
            ->assertSee('Informasi Ruangan')
            ->assertSee('Pengurus Ruangan')
            // Section 1: identitas ruangan.
            ->assertSee('name="kode_ruangan"', false)
            ->assertSee('name="nama_ruangan"', false)
            ->assertSee('name="lokasi"', false)
            // Section 2: picker pengurus (search + badge + grid checkbox card).
            ->assertSee('pengurus-picker')
            ->assertSee('name="pengurus[]"', false)
            ->assertSee('Cari nama atau NIP pengurus')
            ->assertSee('Klik kartu untuk memilih')
            // Aksi simpan untuk route store.
            ->assertSee(route('ruangan.store'));
    }

    public function test_edit_page_menampilkan_nilai_ruangan_dan_pengurus_terpilih(): void
    {
        $admin = $this->createUser('admin_tu', 'admin_tu_ruangan_edit');
        $guru = $this->createUser('guru', 'guru_ruangan_edit');

        $ruangan = Ruangan::create([
            'kode_ruangan' => 'R-EDIT-1',
            'nama_ruangan' => 'Ruang Multimedia',
            'lokasi' => 'Gedung B Lantai 2',
        ]);
        $ruangan->pengurus()->sync([$guru->id]);

        $this->actingAs($admin)
            ->get(route('ruangan.edit', $ruangan->id))
            ->assertOk()
            ->assertSee('Edit Ruangan')
            // Nilai ruangan ter-prefill dari database.
            ->assertSee('value="R-EDIT-1"', false)
            ->assertSee('value="Ruang Multimedia"', false)
            ->assertSee('value="Gedung B Lantai 2"', false)
            // Pengurus yang sudah ditugaskan tercentang di picker.
            ->assertSee('name="pengurus[]"', false)
            ->assertSee('value="'.$guru->id.'"', false)
            ->assertSee('checked')
            // Aksi simpan memakai method PUT ke route update.
            ->assertSee(route('ruangan.update', $ruangan->id))
            ->assertSee('name="_method"', false);
    }

    public function test_halaman_form_hanya_bisa_diakses_petugas_tu_admin(): void
    {
        $admin = $this->createUser('admin_tu', 'admin_tu_ruangan_akses');
        $guru = $this->createUser('guru', 'guru_ruangan_akses');

        $ruangan = Ruangan::create([
            'kode_ruangan' => 'R-AKSES-1',
            'nama_ruangan' => 'Ruang Akses',
        ]);

        // Akses diperbolehkan untuk Petugas TU / Admin.
        $this->actingAs($admin)->get(route('ruangan.create'))->assertOk();
        $this->actingAs($admin)->get(route('ruangan.edit', $ruangan->id))->assertOk();

        // Ditolak untuk guru biasa.
        $this->actingAs($guru)->get(route('ruangan.create'))->assertForbidden();
        $this->actingAs($guru)->get(route('ruangan.edit', $ruangan->id))->assertForbidden();
    }

    private function createUser(string $role, string $username): User
    {
        return User::create([
            'nama' => ucfirst($role).' Test',
            'username' => $username,
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}