<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BantuanTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'nama'      => 'Admin IT',
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
        ], $extra));
    }

    public function test_bantuan_pages_render_with_guides_and_contact(): void
    {
        $this->makeUser('admin', ['no_hp' => '081234567890']);
        $guru = $this->makeUser('guru');

        foreach (['/bantuan', '/admin/bantuan'] as $url) {
            $this->actingAs($guru)
                ->get($url)
                ->assertOk()
                ->assertSee('Pusat Bantuan & Panduan Penggunaan WebJournal', false)
                ->assertSee('Panduan Guru (Guru Mapel)')
                ->assertSee('Panduan Guru Piket')
                ->assertSee('Panduan Waka Kesiswaan / Kepala Sekolah')
                ->assertSee('Bantuan Teknis')
                ->assertSee('Jam Operasional')
                ->assertSee('Chat WhatsApp')
                ->assertSee('6281234567890');
        }
    }

    public function test_bantuan_contact_hidden_when_no_whatsapp_configured(): void
    {
        $this->makeUser('admin'); // tanpa no_hp

        $this->actingAs($this->makeUser('guru'))
            ->get(route('bantuan.index'))
            ->assertOk()
            ->assertDontSee('Chat WhatsApp')
            ->assertSee('Nomor WhatsApp layanan belum diatur');
    }
}