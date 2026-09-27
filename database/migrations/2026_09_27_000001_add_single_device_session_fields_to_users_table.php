<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung fitur "Smart Idle-Aware Single Device Session".
     *
     * - last_active_at        : waktu terakhir interaksi user (diperbarui lewat heartbeat).
     * - is_idle               : penanda user sedang AFK / idle (default false).
     * - current_session_id    : ID sesi yang SAAT INI dianggap valid (single device).
     * - last_security_alert_at: throttle notifikasi percobaan login ke kanal user.{id}.security.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('is_active');
            $table->boolean('is_idle')->default(false)->after('last_active_at');
            $table->string('current_session_id')->nullable()->index()->after('is_idle');
            $table->timestamp('last_security_alert_at')->nullable()->after('current_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_session_id']);
            $table->dropColumn([
                'last_active_at',
                'is_idle',
                'current_session_id',
                'last_security_alert_at',
            ]);
        });
    }
};