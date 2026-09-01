<?php

namespace Tests\Feature;

use App\Models\CatatanTerlambat;
use App\Models\IzinGuru;
use App\Models\Kelas;
use App\Models\PenerimaTerlambat;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 8, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, string $subRole, string $username): User
    {
        return User::create([
            'nama'      => $username,
            'username'  => $username . '_' . Str::random(4),
            'password'  => bcrypt('password123'),
            'role'      => $role,
            'sub_role'  => $subRole,
            'is_active' => true,
        ]);
    }

    public function test_izin_baru_memunculkan_notifikasi_untuk_approver(): void
    {
        $guru   = $this->makeUser('guru', 'guru_mapel', 'gurub');
        $waka   = $this->makeUser('admin', 'waka_kurikulum', 'waka');

        $this->actingAs($guru)
            ->post(route('guru.izin.store'), [
                'tanggal'     => '2026-08-11',
                'alasan'      => 'Sakit',
                'lampiran'    => null,
                'tugas_siswa' => null,
                'ttd_guru'    => null,
            ]);

        $this->assertSame(1, $waka->unreadNotifications()->count());
        $first = $waka->unreadNotifications()->first();
        $this->assertEquals('Pengajuan Izin Baru', $first->data['title'] ?? '');
    }

    public function test_notification_controller_index_unread_count_dan_mark_read(): void
    {
        $waka = $this->makeUser('admin', 'waka_kurikulum', 'waka2');
        $this->makeUser('guru', 'guru_mapel', 'guruc');

        $this->actingAs($this->makeUser('guru', 'guru_mapel', 'gurud'))
            ->post(route('guru.izin.store'), [
                'tanggal' => '2026-08-12', 'alasan' => 'Acara keluarga',
            ]);

        // Waka melihat daftar notifikasi (JSON)
        $resp = $this->actingAs($waka)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJson(['unread_count' => 1]);

        $id = $resp->json('notifications.0.id');

        // Tandai dibaca
        $this->actingAs($waka)
            ->postJson(route('notifications.read', $id))
            ->assertOk();

        $this->actingAs($waka)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);
    }

    public function test_siswa_terlambat_munculkan_notifikasi_untuk_wali_kelas(): void
    {
        $walikelas = $this->makeUser('guru', 'wali_kelas', 'walikel');
        $satpam    = $this->makeUser('admin', 'satpam', 'satpam');

        $kelas = Kelas::create([
            'nama_kelas'    => 'X IPA 1',
            'tingkat'       => 'X',
            'id_wali_kelas' => $walikelas->id,
        ]);

        $siswa = Siswa::create([
            'nisn'          => '0000000101',
            'nis'           => '23102',
            'nama'          => 'Andi',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
        ]);

        $catatan = CatatanTerlambat::create([
            'id_siswa'   => $siswa->id,
            'tanggal'    => '2026-08-10',
            'jam_masuk'  => '07:45',
            'keterangan' => 'x',
            'id_satpam'  => $satpam->id,
        ]);

        PenerimaTerlambat::create([
            'catatan_terlambat_id' => $catatan->id,
            'user_id'              => $walikelas->id,
            'peran'                => PenerimaTerlambat::PERAN_WALI_KELAS,
        ]);

        \App\Services\NotificationService::siswaTerlambat($catatan->load('penerima'));

        $this->assertSame(1, $walikelas->unreadNotifications()->count());
        $first = $walikelas->unreadNotifications()->first();
        $this->assertEquals('Siswa Terlambat', $first->data['title'] ?? '');
    }
}