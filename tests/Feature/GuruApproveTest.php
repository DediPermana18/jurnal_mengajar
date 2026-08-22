<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_guru_activates_user_and_does_not_delete_senin_schedule(): void
    {
        // Setup admin
        $admin = User::create([
            'nama'      => 'Admin User',
            'username'  => 'admin',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // Setup guru to approve (inactive)
        $guru = User::create([
            'nama'      => 'Guru Budi',
            'username'  => 'gurubudi',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => false,
        ]);

        // Setup existing piket schedule on Senin
        $jadwalSenin = JadwalPiket::create([
            'user_id' => $admin->id,
            'hari'    => 'Senin',
        ]);

        // Act: perform approve request
        $response = $this->actingAs($admin)
            ->post(route('guru.approve', $guru->id));

        // Assert: redirected to guru.index with exact flash message
        $response->assertRedirect(route('guru.index'));
        $response->assertSessionHas('success', 'Data guru berhasil disetujui dan diaktifkan.');

        // Assert: user is active
        $this->assertTrue($guru->fresh()->is_active);

        // Assert: Jadwal piket Senin is intact and NOT deleted!
        $this->assertDatabaseHas('jadwal_piket', [
            'id'   => $jadwalSenin->id,
            'hari' => 'Senin',
        ]);
    }

    public function test_toggle_status_to_active_flashes_correct_approval_message(): void
    {
        $admin = User::create([
            'nama'      => 'Admin User',
            'username'  => 'admin',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create([
            'nama'      => 'Guru Siti',
            'username'  => 'gurusiti',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('guru.toggle-status', $guru->id));

        $response->assertRedirect(route('guru.index'));
        $response->assertSessionHas('success', 'Data guru berhasil disetujui dan diaktifkan.');
        $this->assertTrue($guru->fresh()->is_active);
    }

    public function test_update_status_endpoint_updates_status_safely(): void
    {
        $admin = User::create([
            'nama'      => 'Admin User',
            'username'  => 'admin',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create([
            'nama'      => 'Guru Joko',
            'username'  => 'gurujoko',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => false,
        ]);

        $jadwalSenin = JadwalPiket::create([
            'user_id' => $admin->id,
            'hari'    => 'Senin',
        ]);

        $jadwalSelasa = JadwalPiket::create([
            'user_id' => $admin->id,
            'hari'    => 'Selasa',
        ]);

        // Act: call update-status to active
        $response = $this->actingAs($admin)
            ->post(route('guru.update-status', $guru->id), ['is_active' => true]);

        $response->assertRedirect(route('guru.index'));
        $response->assertSessionHas('success', 'Data guru berhasil disetujui dan diaktifkan.');
        $this->assertTrue($guru->fresh()->is_active);

        // Assert: all schedules remain intact
        $this->assertDatabaseHas('jadwal_piket', ['id' => $jadwalSenin->id, 'hari' => 'Senin']);
        $this->assertDatabaseHas('jadwal_piket', ['id' => $jadwalSelasa->id, 'hari' => 'Selasa']);
    }
}
