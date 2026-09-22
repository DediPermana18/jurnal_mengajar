<?php

namespace App\Imports\Exceptions;

use RuntimeException;

/**
 * Dilempar saat Import Data Siswa menemukan kelas / nama tingkat pada file yang
 * TIDAK terdaftar di Data Master Kelas.
 *
 * Karena seluruh proses import dibungkus DB::transaction di controller, exception
 * ini memicu rollback — menjamin TIDAK ADA baris siswa yang tersimpan parsial
 * ketika satu baris header kelas saja gagal di-resolve.
 */
class KelasNotFoundDuringImport extends RuntimeException
{
}