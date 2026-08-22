<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $normalizations = [
            ['X IPA 1', 'X', 'IPA 1'],
            ['XI IPA 1', 'XI', 'IPA 1'],
            ['XII IPA 1', 'XII', 'IPA 1'],
            ['XII RPL 1', 'XII', 'RPL 1'],
            ['XI TKJ 2', 'XI', 'TKJ 2'],
            ['X RPL 2', 'X', 'RPL 2'],
        ];

        foreach ($normalizations as [$oldName, $tingkat, $newName]) {
            DB::table('kelas')
                ->where('nama_kelas', $oldName)
                ->where('tingkat', $tingkat)
                ->update(['nama_kelas' => $newName]);
        }
    }

    public function down(): void
    {
        $normalizations = [
            ['IPA 1', 'X', 'X IPA 1'],
            ['IPA 1', 'XI', 'XI IPA 1'],
            ['IPA 1', 'XII', 'XII IPA 1'],
            ['RPL 1', 'XII', 'XII RPL 1'],
            ['TKJ 2', 'XI', 'XI TKJ 2'],
            ['RPL 2', 'X', 'X RPL 2'],
        ];

        foreach ($normalizations as [$oldName, $tingkat, $newName]) {
            DB::table('kelas')
                ->where('nama_kelas', $oldName)
                ->where('tingkat', $tingkat)
                ->update(['nama_kelas' => $newName]);
        }
    }
};