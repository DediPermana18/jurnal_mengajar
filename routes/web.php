<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JurnalMengajarController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\MapelController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\SiswaController;

// Halaman utama langsung ke Jurnal Mengajar
Route::get('/', [JurnalMengajarController::class, 'index'])->name('home');

// Resource Routes (sudah mencakup index, create, store, show, edit, update, destroy)
Route::resource('jurnal', JurnalMengajarController::class);
Route::resource('guru', GuruController::class);
Route::resource('mapel', MapelController::class);
Route::resource('kelas', KelasController::class);
Route::resource('siswa', SiswaController::class);
