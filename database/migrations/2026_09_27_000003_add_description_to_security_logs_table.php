<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom opsional `description` untuk baris audit non-login (mis. aksi
     * Emergency Suspend oleh rekan Petugas TU / Admin).
     *
     * Tabel security_logs tetap IMMUTABLE (append-only): kolom ini hanya diisi
     * saat baris dibuat (SecurityAuditService::emergencySuspend / recordLogin)
     * dan TIDAK pernah di-update maupun di-hapus setelahnya.
     */
    public function up(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};