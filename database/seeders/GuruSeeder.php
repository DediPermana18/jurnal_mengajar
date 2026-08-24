<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GuruSeeder extends Seeder
{
    /**
     * Seed 10 data guru uji coba.
     */
    public function run(): void
    {
        $guruData = [];

        for ($i = 1; $i <= 10; $i++) {
            $guruData[] = [
                'nama'       => 'testting' . $i,
                'nip'        => '12345678900' . $i,
                'username'   => 'tes' . $i,
                'password'   => Hash::make('password123'),
                'role'       => User::ROLE_GURU,
                'sub_role'   => 'guru_mapel',
                'is_active'  => true,
                'kelas_id'   => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($guruData as $data) {
            // Cek apakah username sudah ada
            $existing = User::withTrashed()->where('username', $data['username'])->first();

            if ($existing) {
                // Update data yang sudah ada (kecuali NIP jika konflik dengan user lain)
                $existing->fill(array_merge($data, ['password' => Hash::make('password123')]));
                $existing->save();
            } else {
                // Cek jika NIP sudah dipakai user lain, kosongkan NIP sebelum insert
                $nipTaken = User::withTrashed()->where('nip', $data['nip'])->exists();
                if ($nipTaken) {
                    $data['nip'] = null;
                }
                User::create($data);
            }
        }

        $this->command->info('✅ 10 data guru uji coba berhasil ditambahkan!');
        $this->command->table(
            ['No', 'Username', 'Nama', 'NIP', 'Role', 'Sub Role'],
            collect($guruData)->map(fn ($g, $i) => [
                $i + 1,
                $g['username'],
                $g['nama'],
                $g['nip'],
                $g['role'],
                $g['sub_role'],
            ])->toArray()
        );
    }
}
