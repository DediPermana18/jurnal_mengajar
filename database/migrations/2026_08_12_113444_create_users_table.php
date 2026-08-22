<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nip', 50)->nullable()->unique();
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->string('kode_aktivasi', 100)->nullable();
            
            // Perbaruan daftar role lengkap
            $table->enum('role', [
                'super_admin',
                'admin_tu',
                'admin_kurikulum',
                'guru_mapel',
                'wali_kelas',
                'guru_piket',
                'piket_satpam',
                'guru',
                'admin'
            ])->default('guru_mapel');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};