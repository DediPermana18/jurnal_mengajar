<?php

namespace Tests\Feature;

use App\Models\ResetRequest;
use App\Models\User;
use App\Notifications\NewResetRequestNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pengajuan reset kredensial (lupa sandi / lupa kode aktivasi).
 *
 *  - Publik: formulir /lupa-sandi + tanda tangan digital canvas (pending).
 *  - Admin TU: panel /admin/pengajuan-reset — setujui (token unik 24 jam)
 *    / tolak (dengan alasan).
 *  - Publik: /reset-credentials/{token} — form adaptif (password/kode baru),
 *    token di-invalidate setelah pemakaian.
 *  - Isolasi data testing: akun & token data testing tidak terlihat oleh
 *    guest / non-IT; hanya Petugas IT/QA yang dapat mengelolanya.
 */
class ResetRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, ?string $subRole = null, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'no_hp' => '085123456789',
            'is_active' => true,
        ], $extra));
    }

    protected function ttd(): string
    {
        return 'data:image/png;base64,'.base64_encode('signature-bytes');
    }

    protected function buatPengajuan(User $user, string $jenis = 'lupa_sandi'): ResetRequest
    {
        return ResetRequest::create([
            'user_id' => $user->id,
            'jenis_pengajuan' => $jenis,
            'tanda_tangan' => $this->ttd(),
            'status' => ResetRequest::STATUS_PENDING,
        ]);
    }

    // ================= Halaman & Form Publik =================

    public function test_halaman_lupa_sandi_terbuka_untuk_guest(): void
    {
        $this->get(route('reset-request.create'))
            ->assertOk()
            ->assertSee('Lupa Sandi / Kode Aktivasi')
            ->assertSee('USERNAME / NIP')
            ->assertSee('JENIS PENGAJUAN')
            ->assertSee('TANDA TANGAN DIGITAL')
            ->assertSee('Lupa Sandi')
            ->assertSee('Lupa Kode Aktivasi')
            // Komponen Alpine cek akun + hint akun tanpa kode aktivasi.
            ->assertSee('resetRequestForm()')
            ->assertSee('Akun ini tidak memiliki Kode Aktivasi');
    }

    public function test_halaman_login_menautkan_ke_pengajuan_reset(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('reset-request.create'))
            ->assertSee('lupa sandi/kode aktivasi?');
    }

    // ================= Pengajuan (Store) =================

    public function test_pengajuan_lupa_sandi_tersimpan_status_pending(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel', ['username' => 'budi_lupa_sandi']);

        $this->post(route('reset-request.store'), [
            'login_id' => 'budi_lupa_sandi',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
        ])
            ->assertRedirect(route('reset-request.create'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $record = ResetRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('lupa_sandi', $record->jenis_pengajuan);
        $this->assertSame(ResetRequest::STATUS_PENDING, $record->status);
        $this->assertSame($this->ttd(), $record->tanda_tangan);
        $this->assertNull($record->reset_token);
        // Pengajuan oleh guest adalah data real.
        $this->assertFalse((bool) $record->is_testing_data);
    }

    public function test_pengajuan_dapat_menggunakan_nip_sebagai_identitas(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel', [
            'nip' => '198001012010011001',
            'kode_aktivasi' => 'KODE-NIP-001',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => '198001012010011001',
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'tanda_tangan' => $this->ttd(),
        ])->assertRedirect(route('reset-request.create'));

        $this->assertDatabaseHas('reset_requests', [
            'user_id' => $user->id,
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'status' => ResetRequest::STATUS_PENDING,
        ]);
    }

    public function test_pengajuan_lupa_kode_aktivasi_ditolak_bila_akun_tanpa_kode(): void
    {
        // Akun terdaftar, TAPI tidak memiliki kode aktivasi sama sekali.
        $user = $this->makeUser('guru', 'guru_mapel', ['username' => 'tanpa_kode_akun']);

        $this->post(route('reset-request.store'), [
            'login_id' => 'tanpa_kode_akun',
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'tanda_tangan' => $this->ttd(),
        ])
            ->assertSessionHasErrors('jenis_pengajuan')
            ->assertSessionHasErrors([
                'jenis_pengajuan' => 'Akun ini tidak menggunakan fitur Kode Aktivasi. Silakan pilih Lupa Sandi.',
            ]);

        // Pengajuan TIDAK tersimpan.
        $this->assertDatabaseMissing('reset_requests', ['user_id' => $user->id]);
    }

    public function test_pengajuan_lupa_kode_aktivasi_diterima_bila_akun_memiliki_kode(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel', [
            'username' => 'punya_kode_akun',
            'kode_aktivasi' => 'AKT-12345',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => 'punya_kode_akun',
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'tanda_tangan' => $this->ttd(),
        ])
            ->assertRedirect(route('reset-request.create'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reset_requests', [
            'user_id' => $user->id,
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'status' => ResetRequest::STATUS_PENDING,
        ]);
    }

    // ================= Cek Akun (AJAX /lupa-sandi/cek-akun) =================

    public function test_cek_akun_menemukan_akun_dengan_kode_aktivasi(): void
    {
        $this->makeUser('guru', 'guru_mapel', [
            'username' => 'cek_kode_ok',
            'kode_aktivasi' => 'AKT-999',
        ]);

        $this->getJson(route('reset-request.check-account', ['login_id' => 'cek_kode_ok']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'has_kode_aktivasi' => true,
            ]);
    }

    public function test_cek_akun_menemukan_akun_tanpa_kode_aktivasi(): void
    {
        $this->makeUser('guru', 'guru_mapel', ['username' => 'cek_kode_kosong']);

        $this->getJson(route('reset-request.check-account', ['login_id' => 'cek_kode_kosong']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'has_kode_aktivasi' => false,
            ]);
    }

    public function test_cek_akun_mengenal_nip_dan_menolak_akun_tidak_ada(): void
    {
        $this->makeUser('guru', 'guru_mapel', [
            'nip' => '199001012010011002',
            'kode_aktivasi' => 'AKT-NIP',
        ]);

        // NIP dikenali.
        $this->getJson(route('reset-request.check-account', ['login_id' => '199001012010011002']))
            ->assertOk()
            ->assertJson(['found' => true, 'has_kode_aktivasi' => true]);

        // Akun tidak dikenal.
        $this->getJson(route('reset-request.check-account', ['login_id' => 'tidak_ada_zzz']))
            ->assertOk()
            ->assertJson(['found' => false, 'has_kode_aktivasi' => false]);
    }

    public function test_cek_akun_mengabaikan_data_testing_untuk_guest(): void
    {
        $this->makeUser('guru', 'guru_mapel', [
            'username' => 'cek_data_testing',
            'kode_aktivasi' => 'AKT-TEST',
            'is_testing_data' => true,
        ]);

        // Guest: akun data testing tidak terlihat (isolasi data testing).
        $this->getJson(route('reset-request.check-account', ['login_id' => 'cek_data_testing']))
            ->assertOk()
            ->assertJson(['found' => false, 'has_kode_aktivasi' => false]);
    }

    public function test_pengajuan_menolak_akun_tidak_ditemukan(): void
    {
        $this->post(route('reset-request.store'), [
            'login_id' => 'akun_tidak_ada_zzz',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
        ])
            ->assertSessionHasErrors('login_id');

        $this->assertDatabaseCount('reset_requests', 0);
    }

    public function test_pengajuan_menolak_tanpa_tanda_tangan(): void
    {
        $this->makeUser('guru', 'guru_mapel', ['username' => 'tanpa_ttd_user']);

        $this->post(route('reset-request.store'), [
            'login_id' => 'tanpa_ttd_user',
            'jenis_pengajuan' => 'lupa_sandi',
        ])->assertSessionHasErrors('tanda_tangan');

        $this->assertDatabaseCount('reset_requests', 0);
    }

    public function test_pengajuan_menolak_tanda_tangan_bukan_data_uri(): void
    {
        $this->makeUser('guru', 'guru_mapel', ['username' => 'ttd_invalid_user']);

        $this->post(route('reset-request.store'), [
            'login_id' => 'ttd_invalid_user',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => 'bukan-gambar',
        ])->assertSessionHasErrors('tanda_tangan');

        $this->assertDatabaseCount('reset_requests', 0);
    }

    // ================= Notifikasi Database (Lonceng Header) =================

    public function test_pengajuan_mengirim_notifikasi_database_ke_admin_tu(): void
    {
        $tu        = $this->makeUser('admin', 'petugas_tu');
        $adminBare = $this->makeUser('admin'); // role admin, sub_role null
        $waka      = $this->makeUser('admin', 'waka_kesiswaan');
        $guru      = $this->makeUser('guru', 'guru_mapel');
        $this->makeUser('guru', 'guru_mapel', [
            'nama' => 'Budi Santoso',
            'username' => 'budi_notif',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => 'budi_notif',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
        ])->assertRedirect(route('reset-request.create'));

        // Admin TU / Petugas TU menerima notifikasi database dengan payload
        // title / message / url / type sesuai ketentuan.
        foreach ([$tu, $adminBare] as $adminUser) {
            $this->assertSame(1, $adminUser->unreadNotifications()->count());

            $notif = $adminUser->unreadNotifications()->first();
            $this->assertSame(NewResetRequestNotification::class, $notif->type);
            $this->assertSame('Pengajuan Reset Kredensial', $notif->data['title']);
            $this->assertSame(
                'User Budi Santoso mengajukan reset Lupa Sandi',
                $notif->data['message']
            );
            $this->assertSame(route('admin.reset-requests.index'), $notif->data['url']);
            $this->assertSame('lupa_sandi', $notif->data['type']);
        }

        // User non-TU (waka & guru) tidak menerima notifikasi.
        $this->assertSame(0, $waka->notifications()->count());
        $this->assertSame(0, $guru->notifications()->count());
    }

    public function test_notifikasi_type_mengikuti_jenis_pengajuan(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $this->makeUser('guru', 'guru_mapel', [
            'username' => 'pemohon_kode_notif',
            'kode_aktivasi' => 'AKT-NOTIF',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => 'pemohon_kode_notif',
            'jenis_pengajuan' => 'lupa_kode_aktivasi',
            'tanda_tangan' => $this->ttd(),
        ]);

        $this->assertSame(1, $tu->unreadNotifications()->count());
        $this->assertSame(
            'lupa_kode_aktivasi',
            $tu->unreadNotifications()->first()->data['type']
        );
    }

    public function test_header_lonceng_menampilkan_unread_badge_dan_markup_klik_baca(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 23, 9, 0, 0));

        $tu = $this->makeUser('admin', 'petugas_tu');
        $this->makeUser('guru', 'guru_mapel', [
            'nama' => 'Siti Aminah',
            'username' => 'siti_notif',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => 'siti_notif',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
        ]);

        // Dropdown membaca unreadNotifications: badge angka merah, daftar
        // terbaru (max 5) dengan waktu relatif, atribut data-notif-read untuk
        // klik item, dan tombol "Tandai semua dibaca" di bagian atas.
        Carbon::setTestNow(Carbon::create(2026, 9, 23, 9, 5, 0));
        $this->actingAs($tu)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<span class="notif-badge">1</span>', false)
            ->assertSee('1 belum dibaca')
            ->assertSee('Pengajuan Reset Kredensial')
            ->assertSee('Siti Aminah')
            ->assertSee('5 minutes ago')
            ->assertSee('data-notif-read=')
            ->assertSee('Tandai semua dibaca');

        // Setelah dibaca: item hilang dari dropdown, badge & tombol ikut hilang.
        $tu->unreadNotifications()->get()->markAsRead();

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 9, 6, 0));
        $this->actingAs($tu)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('<span class="notif-badge">', false)
            ->assertSee('0 belum dibaca')
            ->assertDontSee('Tandai semua dibaca')
            ->assertSee('Tidak ada notifikasi baru.');

        Carbon::setTestNow();
    }

    public function test_klik_item_lonceng_menandai_sudah_dibaca(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $this->makeUser('guru', 'guru_mapel', [
            'nama' => 'Rina Wati',
            'username' => 'rina_notif',
        ]);

        $this->post(route('reset-request.store'), [
            'login_id' => 'rina_notif',
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
        ]);

        $notification = $tu->unreadNotifications()->first();
        $this->assertNotNull($notification);

        // Endpoint read dipanggil JS saat item dropdown diklik -> terbaca.
        $this->actingAs($tu)
            ->postJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, $tu->unreadNotifications()->count());
        $this->assertNotNull($tu->notifications()->first()->read_at);
    }

    // ================= Panel Admin TU =================

    public function test_panel_admin_terbuka_untuk_petugas_tu(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($admin)
            ->get(route('admin.reset-requests.index'))
            ->assertOk()
            ->assertSee('Pengajuan Reset');
    }

    public function test_panel_admin_ditolak_untuk_user_lain(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)
            ->get(route('admin.reset-requests.index'))
            ->assertStatus(403);
    }

    public function test_panel_menampilkan_daftar_pengajuan_dan_status(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel', ['username' => 'pemohon_tampil']);
        $this->buatPengajuan($pemohon);

        $this->actingAs($admin)
            ->get(route('admin.reset-requests.index'))
            ->assertOk()
            ->assertSee('pemohon_tampil')
            ->assertSee('Lupa Sandi')
            ->assertSee('Menunggu');
    }

    public function test_approve_membuat_token_unik_dan_expiry_24_jam(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $reset = $this->buatPengajuan($pemohon);

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 8, 0, 0));

        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id))
            ->assertSessionHas('success');

        $fresh = $reset->fresh();
        $this->assertSame(ResetRequest::STATUS_APPROVED, $fresh->status);
        $this->assertNotNull($fresh->reset_token);
        $this->assertSame(64, strlen($fresh->reset_token));
        $this->assertTrue($fresh->isValidToken());
        $this->assertSame(
            Carbon::create(2026, 9, 24, 8, 0, 0)->timestamp,
            $fresh->token_expires_at->timestamp
        );

        Carbon::setTestNow();
    }

    public function test_approve_pengajuan_yang_sudah_diproses_ditolak(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $reset = $this->buatPengajuan($pemohon);

        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id))
            ->assertSessionHas('success');

        // Approve kedua: 422 — sudah diproses.
        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id))
            ->assertStatus(422);
    }

    public function test_reject_membutuhkan_alasan_dan_menyimpan(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $reset = $this->buatPengajuan($pemohon);

        // Tanpa alasan -> error.
        $this->actingAs($admin)
            ->post(route('admin.reset-requests.reject', $reset->id))
            ->assertSessionHasErrors('admin_note');

        // Dengan alasan -> status rejected.
        $this->actingAs($admin)
            ->post(route('admin.reset-requests.reject', $reset->id), [
                'admin_note' => 'Tanda tangan tidak sesuai arsip.',
            ])
            ->assertSessionHas('success');

        $fresh = $reset->fresh();
        $this->assertSame(ResetRequest::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Tanda tangan tidak sesuai arsip.', $fresh->admin_note);
        $this->assertNull($fresh->reset_token);
        $this->assertFalse($fresh->isValidToken());
    }

    // ================= Halaman Reset via Token =================

    public function test_reset_sandi_melalui_tautan_unik(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel', [
            'username' => 'reseter_sandi',
            'password' => bcrypt('LamaSandi123'),
        ]);
        $reset = $this->buatPengajuan($pemohon);

        // Admin setujui -> token dibuat.
        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id))
            ->assertSessionHas('success');

        $token = $reset->fresh()->reset_token;

        // Form adaptif: lupa_sandi menampilkan password, tanpa kode aktivasi.
        $this->get(route('reset-credentials.show', $token))
            ->assertOk()
            ->assertSee('Atur Ulang Sandi')
            ->assertSee('PASSWORD BARU')
            ->assertDontSee('KODE AKTIVASI BARU');

        // Simpan password baru.
        $this->post(route('reset-credentials.submit', $token), [
            'password' => 'SandiBaru#2026',
            'password_confirmation' => 'SandiBaru#2026',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Kredensial berhasil diperbarui. Silakan login kembali dengan password/kode aktivasi baru Anda.');

        // Password ter-hash tersimpan.
        $this->assertTrue(Hash::check('SandiBaru#2026', $pemohon->fresh()->password));
        $this->assertFalse(Hash::check('LamaSandi123', $pemohon->fresh()->password));

        // Token di-invalidate — tautan tidak dapat dipakai ulang.
        $fresh = $reset->fresh();
        $this->assertNull($fresh->reset_token);
        $this->assertNull($fresh->token_expires_at);
        $this->assertFalse($fresh->isValidToken());
    }

    public function test_reset_sandi_mengakhiri_sesi_dan_mendarat_di_login(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel', [
            'username' => 'reseter_sesi',
            'password' => bcrypt('SesiLama123'),
        ]);
        $reset = $this->buatPengajuan($pemohon);

        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id));

        $token = $reset->fresh()->reset_token;

        // Simulasi kasus bug: pengguna membawa sesi aktif (mis. Admin TU yang
        // sedang mengecek link reset yang disalin/dikirimnya).
        $this->actingAs($admin)
            ->post(route('reset-credentials.submit', $token), [
                'password' => 'SandiSesi#2026',
                'password_confirmation' => 'SandiSesi#2026',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Kredensial berhasil diperbarui. Silakan login kembali dengan password/kode aktivasi baru Anda.');

        // Sesi yang tadinya aktif harus sudah dibersihkan — status logged out.
        $this->assertGuest();

        // Halaman login TERBUKA sebagai publik (bukan teleportasi ke dashboard
        // admin) dengan pesan sukses.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Kredensial berhasil diperbarui')
            ->assertSee('USERNAME/NIP');
    }

    public function test_reset_kode_aktivasi_melalui_tautan_unik(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('admin', 'waka_kurikulum', [
            'username' => 'reseter_kode',
            'kode_aktivasi' => 'AKT-LAMA',
        ]);
        $reset = $this->buatPengajuan($pemohon, 'lupa_kode_aktivasi');

        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id))
            ->assertSessionHas('success');

        $token = $reset->fresh()->reset_token;

        // Form adaptif: hanya kode aktivasi baru.
        $this->get(route('reset-credentials.show', $token))
            ->assertOk()
            ->assertSee('Atur Ulang Kode Aktivasi')
            ->assertSee('KODE AKTIVASI BARU')
            ->assertDontSee('PASSWORD BARU');

        $this->post(route('reset-credentials.submit', $token), [
            'kode_aktivasi' => 'AKT-BARU-123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame('AKT-BARU-123', $pemohon->fresh()->kode_aktivasi);

        // Token di-invalidate.
        $this->assertNull($reset->fresh()->reset_token);
    }

    public function test_halaman_reset_token_tidak_dikenal_mengarah_ke_login(): void
    {
        $this->get(route('reset-credentials.show', 'token-acak-001'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tautan reset sudah tidak berlaku');
    }

    public function test_token_kedaluwarsa_ditolak_dan_mengarah_ke_login(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $reset = ResetRequest::create([
            'user_id' => $pemohon->id,
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
            'status' => ResetRequest::STATUS_APPROVED,
            'reset_token' => 'TOKEN-EXPIRED-001',
            'token_expires_at' => now()->subHour(),
        ]);

        // GET sekalipun ditolak — langsung kembali ke login dengan pesan error.
        $this->get(route('reset-credentials.show', $reset->reset_token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tautan reset sudah tidak berlaku');

        // POST dengan token expired juga ditolak — kredensial TIDAK berubah.
        $this->post(route('reset-credentials.submit', $reset->reset_token), [
            'password' => 'SandiBaru#2026',
            'password_confirmation' => 'SandiBaru#2026',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tautan reset sudah tidak berlaku');

        $this->assertFalse(Hash::check('SandiBaru#2026', $pemohon->fresh()->password));
    }

    public function test_token_tidak_dapat_dipakai_ulang_setelah_reset(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $reset = $this->buatPengajuan($pemohon);

        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', $reset->id));

        $token = $reset->fresh()->reset_token;

        $this->post(route('reset-credentials.submit', $token), [
            'password' => 'SandiPertama1',
            'password_confirmation' => 'SandiPertama1',
        ])->assertRedirect(route('login'));

        // Coba lagi dengan token yang sama -> ditolak, kembali ke login.
        $this->post(route('reset-credentials.submit', $token), [
            'password' => 'SandiKedua2',
            'password_confirmation' => 'SandiKedua2',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tautan reset sudah tidak berlaku');

        $this->assertTrue(Hash::check('SandiPertama1', $pemohon->fresh()->password));
        $this->assertFalse(Hash::check('SandiKedua2', $pemohon->fresh()->password));
    }

    // ================= Isolasi Data Testing =================

    public function test_token_reset_data_testing_tidak_terlihat_guest_namun_dipakai_it(): void
    {
        $userTesting = $this->makeUser('guru', 'guru_mapel', ['is_testing_data' => true]);
        $reset = ResetRequest::create([
            'user_id' => $userTesting->id,
            'jenis_pengajuan' => 'lupa_sandi',
            'tanda_tangan' => $this->ttd(),
            'status' => ResetRequest::STATUS_APPROVED,
            'reset_token' => 'TOKEN-TESTING-001',
            'token_expires_at' => now()->addHour(),
            'is_testing_data' => true,
        ]);

        // Guest: token data testing tidak dikenali (isolasi data testing) —
        // diarahkan kembali ke login dengan pesan error.
        $this->get(route('reset-credentials.show', $reset->reset_token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tautan reset sudah tidak berlaku');

        // Petugas IT: token terlihat dan dapat dipakai.
        $it = $this->makeUser('petugas_it');
        $this->actingAs($it)
            ->get(route('reset-credentials.show', $reset->reset_token))
            ->assertOk()
            ->assertSee('Atur Ulang Sandi');

        $this->actingAs($it)
            ->post(route('reset-credentials.submit', $reset->reset_token), [
                'password' => 'SandiTestingX1',
                'password_confirmation' => 'SandiTestingX1',
            ])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('SandiTestingX1', $userTesting->fresh()->password));
        $this->assertNull($reset->fresh()->reset_token);
    }

    // ================= Badge Counter Sidebar =================

    public function test_sidebar_menampilkan_badge_jumlah_pending(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $this->buatPengajuan($this->makeUser('guru', 'guru_mapel'));
        $this->buatPengajuan($this->makeUser('guru', 'guru_mapel'));
        $this->buatPengajuan($this->makeUser('guru', 'guru_mapel'));

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Pengajuan Reset')
            ->assertSee('nav-reset-badge-live')
            ->assertSee('resetBadge(3)');
    }

    public function test_badge_counter_nol_tidak_ditampilkan(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            // Nilai awal nol + kondisi visibilitas Alpine (sembunyi saat 0).
            ->assertSee('resetBadge(0)')
            ->assertSee('x-show="count > 0"', false);
    }

    public function test_badge_kapasitas_99_plus(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');

        for ($i = 0; $i < 100; $i++) {
            $this->buatPengajuan($this->makeUser('guru', 'guru_mapel'));
        }

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('99+')
            ->assertSee('resetBadge(100)');
    }

    public function test_badge_tidak_ditampilkan_untuk_non_admin(): void
    {
        $guru = $this->makeUser('guru');
        $this->buatPengajuan($guru);

        $this->actingAs($guru)
            ->get(route('home'))
            ->assertOk()
            // Badge (komponen Alpine) hanya dirender di menu admin — tidak untuk guru.
            ->assertDontSee('resetBadge(');
    }

    public function test_endpoint_counter_mengembalikan_jumlah_pending(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');
        $pemohon1 = $this->makeUser('guru', 'guru_mapel');
        $pemohon2 = $this->makeUser('guru', 'guru_mapel');
        $this->buatPengajuan($pemohon1);
        $this->buatPengajuan($pemohon2);

        $this->actingAs($admin)
            ->getJson(route('admin.reset-requests.count'))
            ->assertOk()
            ->assertExactJson(['count' => 2]);

        // Pengajuan yang sudah disetujui tidak lagi dihitung.
        $this->actingAs($admin)
            ->post(route('admin.reset-requests.approve', ResetRequest::first()->id))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->getJson(route('admin.reset-requests.count'))
            ->assertOk()
            ->assertExactJson(['count' => 1]);
    }

    public function test_endpoint_counter_ditolak_untuk_user_lain(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)
            ->getJson(route('admin.reset-requests.count'))
            ->assertStatus(403);
    }
}