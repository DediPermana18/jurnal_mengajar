<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KepsekModalSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function makeKepsek(): User
    {
        return User::create([
            'nama' => 'Dr. H. Ahmad Dahlan, M.Pd.',
            'username' => 'kepsek_test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'kepsek',
            'kode_aktivasi' => 'KPS-SIGN-2026',
            'is_active' => true,
        ]);
    }

    protected function makeGuru(): User
    {
        return User::create([
            'nama' => 'Bambang Hermanto',
            'username' => 'bambang',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);
    }

    public function test_signature_approval_fails_without_signature_data(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'Demam tinggi',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
        ]);

        $response = $this->actingAs($kepsek)->post(route('kepsek.izin.approve-signature', $izin->id), [
            'ttd_kepsek' => '',
        ]);

        $response->assertSessionHas('error', 'Tanda tangan Kepala Sekolah wajib diisi.');
        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_KEPSEK, $izin->status);
    }

    public function test_signature_approval_succeeds_with_canvas_data(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'Demam tinggi',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
        ]);

        $base64Ttd = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($kepsek)->post(route('kepsek.izin.approve-signature', $izin->id), [
            'ttd_kepsek' => $base64Ttd,
            'approved_by_kepsek_id' => $kepsek->id,
        ]);

        $response->assertRedirect();
        $izin->refresh();

        $this->assertEquals(IzinGuru::STATUS_DISETUJUI, $izin->status);
        $this->assertEquals($kepsek->id, $izin->approved_by_kepsek);
        $this->assertNotNull($izin->ttd_kepsek);
    }

    public function test_ajax_signature_approval_returns_json_response(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'Demam tinggi',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
        ]);

        $base64Ttd = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($kepsek)->postJson(route('kepsek.izin.approve-signature', $izin->id), [
            'ttd_kepsek' => $base64Ttd,
            'approved_by_kepsek_id' => $kepsek->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_DISETUJUI, $izin->status);
    }
}
