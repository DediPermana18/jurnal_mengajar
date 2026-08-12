<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JurnalMengajarController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\MapelController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\SiswaController;

// Halaman utama / login langsung mengarah ke Dashboard Admin
Route::get('/', [JurnalMengajarController::class, 'index'])->name('home');
Route::get('/login', function () {
    return redirect()->route('home');
})->name('login');

// Resource Routes (Admin Data Master & Jurnal)
Route::resource('admin/jurnal', JurnalMengajarController::class);
Route::resource('admin/guru', GuruController::class);
Route::resource('admin/mapel', MapelController::class);
Route::resource('admin/kelas', KelasController::class);
Route::resource('admin/siswa', SiswaController::class);
