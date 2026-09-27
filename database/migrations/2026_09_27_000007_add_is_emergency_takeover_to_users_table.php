<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda akun yang pernah melakukan Emergency Super Admin Takeover
     * ("Kartu As") — dipromosikan menjadi Super Admin permanen dari akun
     * Petugas IT / QA Tester.
     *
     * Dipakai sebagai salah satu pembuka gembok pengelolaan akun Utama
     * 'admin' (lihat UserController::isEmergencyPrimaryAdminOverride):
     * selama flag ini aktif pada aktor, tombol suspend akun Utama dibuka
     * agar penyadap/peretas akun utama dapat dikeluarkan seketika.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_emergency_takeover')
                ->default(false)
                ->after('last_security_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_emergency_takeover');
        });
    }
};