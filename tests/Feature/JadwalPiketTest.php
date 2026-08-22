<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPiketTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_jadwal_piket_updates_schedule_and_flashes_correct_message(): void
    {
        $admin = User::create([
            'nama'      => 'Admin Kurikulum',
            'username'  => 'admin_kurikulum',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $guru1 = User::create([
            'nama'      => 'Guru A',
            'username'  => 'gurua',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => true,
        ]);

        $guru2 = User::create([
            'nama'      => 'Guru B',
            'username'  => 'gurub',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => true,
        ]);

        // Existing schedule for Tuesday
        $selasaPiket = JadwalPiket::create([
            'hari'    => 'Selasa',
            'user_id' => $guru1->id,
        ]);

        // Submit sync for Senin
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari'     => 'Senin',
                'guru_ids' => [$guru1->id, $guru2->id],
            ]);

        $response->assertRedirect(route('kurikulum.jadwal-piket.index'));
        $response->assertSessionHas('success', 'Petugas piket hari Senin berhasil diperbarui.');

        // Assert database has both teachers on Senin
        $this->assertDatabaseHas('jadwal_piket', [
            'hari'    => 'Senin',
            'user_id' => $guru1->id,
        ]);
        $this->assertDatabaseHas('jadwal_piket', [
            'hari'    => 'Senin',
            'user_id' => $guru2->id,
        ]);

        // Assert Tuesday schedule is still intact
        $this->assertDatabaseHas('jadwal_piket', [
            'id'   => $selasaPiket->id,
            'hari' => 'Selasa',
        ]);
    }

    public function test_sync_jadwal_piket_fails_validation_and_does_not_delete_if_no_guru_selected(): void
    {
        $admin = User::create([
            'nama'      => 'Admin Kurikulum',
            'username'  => 'admin_kurikulum',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $guru1 = User::create([
            'nama'      => 'Guru A',
            'username'  => 'gurua',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => true,
        ]);

        // Existing schedule for Senin
        $seninPiket = JadwalPiket::create([
            'hari'    => 'Senin',
            'user_id' => $guru1->id,
        ]);

        // Submit sync without guru_ids
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari'     => 'Senin',
                'guru_ids' => [],
            ]);

        $response->assertSessionHasErrors('guru_ids');

        // Existing schedule must NOT be deleted
        $this->assertDatabaseHas('jadwal_piket', [
            'id'      => $seninPiket->id,
            'hari'    => 'Senin',
            'user_id' => $guru1->id,
        ]);
    }
}
