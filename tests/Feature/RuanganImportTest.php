<?php

namespace Tests\Feature;

use App\Imports\RuanganImport;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RuanganImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminTU(): User
    {
        return User::create([
            'nama' => 'Admin TU Test',
            'username' => 'admintu_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'admin_tu',
            'is_active' => true,
        ]);
    }

    public function test_generate_kode_ruangan_helper_converts_correctly(): void
    {
        $this->assertEquals('LAB-KI-1', RuanganImport::generateKodeRuangan('Lab. KI 1'));
        $this->assertEquals('R-1', RuanganImport::generateKodeRuangan('R 1'));
        $this->assertEquals('LAB-TKJ-2-FO', RuanganImport::generateKodeRuangan('Lab. TKJ 2 (FO)'));
        $this->assertEquals('LAP', RuanganImport::generateKodeRuangan('LAP'));
        $this->assertEquals('R-102', RuanganImport::generateKodeRuangan('  R. 102  '));
        $this->assertEquals('LAB-KIMIA-DASAR', RuanganImport::generateKodeRuangan('Lab_Kimia / Dasar'));
    }

    public function test_import_ruangan_from_data_jadwal_csv(): void
    {
        $admin = $this->makeAdminTU();

        // Simulasi mentahan Data Jadwal.csv dengan kolom 'Ruang'
        $csvContent = "Hari,Jam Ke,Kelas,Mata Pelajaran,Guru,Ruang\n"
            ."Senin,1,X TKJ 1,Matematika,Budi,Lab. KI 1\n"
            ."Senin,2,X TKJ 1,Matematika,Budi,Lab. KI 1\n"
            ."Senin,3,X RPL 1,Basis Data,Agus,R 1\n"
            ."Selasa,1,XI TKJ 2,Jaringan,Dewi,Lab. TKJ 2 (FO)\n"
            ."Rabu,1,XII AKL 1,Penjas,Rudi,LAP\n"
            ."Kamis,1,X MP 1,Bahasa Indonesia,Siti,-\n"
            ."Jumat,1,XI DKV 1,Desain,Eko,   \n";

        $file = UploadedFile::fake()->createWithContent('Data Jadwal.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('ruangan.import'), [
            'file_ruangan' => $file,
        ]);

        $response->assertRedirect(route('ruangan.index'));
        $response->assertSessionHas('success');

        // Pastikan hanya 4 ruangan unik yang dibuat
        $this->assertEquals(4, Ruangan::count());

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'LAB-KI-1',
            'nama_ruangan' => 'Lab. KI 1',
            'lokasi' => 'Gedung Sekolah',
        ]);

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'R-1',
            'nama_ruangan' => 'R 1',
            'lokasi' => 'Gedung Sekolah',
        ]);

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'LAB-TKJ-2-FO',
            'nama_ruangan' => 'Lab. TKJ 2 (FO)',
            'lokasi' => 'Gedung Sekolah',
        ]);

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'LAP',
            'nama_ruangan' => 'LAP',
            'lokasi' => 'Gedung Sekolah',
        ]);
    }

    public function test_import_ruangan_from_data_import_controller_route(): void
    {
        $admin = $this->makeAdminTU();

        $csvContent = "NO,NAMA RUANGAN,LOKASI\n"
            ."1,Lab. KI 1,Gedung A Lantai 2\n"
            ."2,R 1,Gedung B\n";

        $file = UploadedFile::fake()->createWithContent('Master_Ruangan.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('import.ruangan'), [
            'file_ruangan' => $file,
        ]);

        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'LAB-KI-1',
            'nama_ruangan' => 'Lab. KI 1',
            'lokasi' => 'Gedung A Lantai 2',
        ]);

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'R-1',
            'nama_ruangan' => 'R 1',
            'lokasi' => 'Gedung B',
        ]);
    }

    public function test_import_updates_existing_ruangan_without_duplicates(): void
    {
        $admin = $this->makeAdminTU();

        Ruangan::create([
            'kode_ruangan' => 'LAB-KI-1',
            'nama_ruangan' => 'Lab KI Lama',
            'lokasi' => 'Gedung Lama',
        ]);

        $csvContent = "Ruang\n"
            ."Lab. KI 1\n"
            ."Lab. KI 1\n";

        $file = UploadedFile::fake()->createWithContent('Data Jadwal.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('ruangan.import'), [
            'file_ruangan' => $file,
        ]);

        $response->assertRedirect(route('ruangan.index'));
        $this->assertEquals(1, Ruangan::where('kode_ruangan', 'LAB-KI-1')->count());

        $this->assertDatabaseHas('ruangans', [
            'kode_ruangan' => 'LAB-KI-1',
            'nama_ruangan' => 'Lab. KI 1',
        ]);
    }
}
