<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot identitas IT/QA asli sebelum Emergency Super Admin Takeover
     * ("Kartu As") menimpa role/sub_role.
     *
     * Diisi ItEmergencyController::promoteSelf (nilai 'petugas_it' /
     * 'qa_tester') dan dipakai demoteSelf untuk mengembalikan identitas
     * IT/QA yang benar saat akun kembali ke Mode IT biasa.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('emergency_origin_role', 50)
                ->nullable()
                ->after('is_emergency_takeover');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('emergency_origin_role');
        });
    }
};