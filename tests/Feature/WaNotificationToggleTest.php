<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur Feature Switch "Status Layanan Notifikasi" (WA Gateway):
 * - Petugas IT / QA Tester dapat mematikan / menyalakan pengiriman notifikasi
 *   WA secara GLOBAL lewat POST /it/settings/wa/toggle.
 * - Status tersimpan di tabel `app_settings` dengan key
 *   `wa_notification_enabled` ('1'/'0').
 * - Saat nonaktif, FonnteService::sendNotification() membatalkan pengiriman,
 *   mencatat info ke log, dan mengembalikan false TANPA melempar error.
 */
class WaNotificationToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $extra));
    }

    public function test_default_notifikasi_wa_aktif(): void
    {
        $this->assertTrue(FonnteService::notificationsEnabled());
    }

    public function test_toggle_hanya_petugas_it(): void
    {
        // Admin TU tidak boleh mengubah status.
        $this->actingAs($this->makeUser('admin', ['sub_role' => 'petugas_tu']))
            ->post(route('it.settings.wa.toggle'), ['wa_notification_enabled' => 1])
            ->assertStatus(403);

        // Guru biasa juga tidak boleh.
        $this->actingAs($this->makeUser('guru'))
            ->post(route('it.settings.wa.toggle'), ['wa_notification_enabled' => 0])
            ->assertStatus(403);

        // Status tetap default aktif.
        $this->assertTrue(FonnteService::notificationsEnabled());
    }

    public function test_it_dapat_mematikan_dan_menyalakan_notifikasi_wa(): void
    {
        $it = $this->makeUser('petugas_it');

        $this->assertTrue(FonnteService::notificationsEnabled());

        // Matikan notifikasi.
        $this->actingAs($it)
            ->from(route('it.settings.wa'))
            ->post(route('it.settings.wa.toggle'), ['wa_notification_enabled' => 0])
            ->assertRedirect(route('it.settings.wa'))
            ->assertSessionHas('success');

        $this->assertFalse(FonnteService::notificationsEnabled());
        $this->assertSame('0', \App\Models\AppSetting::get(FonnteService::NOTIFICATION_ENABLED_KEY));

        // Nyalakan kembali.
        $this->actingAs($it)
            ->from(route('it.settings.wa'))
            ->post(route('it.settings.wa.toggle'), ['wa_notification_enabled' => 1])
            ->assertRedirect(route('it.settings.wa'))
            ->assertSessionHas('success');

        $this->assertTrue(FonnteService::notificationsEnabled());
        $this->assertSame('1', \App\Models\AppSetting::get(FonnteService::NOTIFICATION_ENABLED_KEY));
    }

    public function test_send_notification_dibatalkan_tanpa_error_saat_nonaktif(): void
    {
        FonnteService::setNotificationsEnabled(false);

        Log::spy();

        $sent = FonnteService::sendNotification('6281234567890', 'Pesan uji');

        $this->assertFalse($sent);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => str_contains((string) $message, 'notifikasi WA dinonaktifkan oleh admin'));
    }

    public function test_halaman_pengaturan_menampilkan_switch_dan_badge_status(): void
    {
        $it = $this->makeUser('petugas_it');

        // Saat NONAKTIF.
        FonnteService::setNotificationsEnabled(false);

        $this->actingAs($it)
            ->get(route('it.settings.wa'))
            ->assertOk()
            ->assertSee('Status Layanan Notifikasi')
            ->assertSee('Nonaktif (Notifikasi Dimatikan)')
            ->assertDontSee('Aktif (Notifikasi Terkirim)');

        // Saat AKTIF.
        FonnteService::setNotificationsEnabled(true);

        $this->actingAs($it)
            ->get(route('it.settings.wa'))
            ->assertOk()
            ->assertSee('Aktif (Notifikasi Terkirim)')
            ->assertDontSee('Nonaktif (Notifikasi Dimatikan)');
    }
}