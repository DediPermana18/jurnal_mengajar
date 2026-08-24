<h1 align="center">WebJournal - Management System</h1>

<p align="center">
  <b>Sistem Presensi & Manajemen Jurnal Mengajar Digital Berbasis Real-Time</b>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/TailwindCSS-3.0-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
</p>

## 📌 Tentang WebJournal

**WebJournal** adalah platform web modern yang dirancang untuk merelokasi presensi KBM (Kegiatan Belajar Mengajar) dari jurnal kertas konvensional ke sistem digital. Aplikasi ini mempermudah guru dalam mencatat materi dan presensi siswa, mengotomatisasi penyesuaian jadwal khusus, serta memberikan visibilitas penuh kepada Waka Kurikulum secara real-time.

## ✨ Fitur Utama

- ⚡ **Dynamic Special Day Mode:** Penyesuaian jadwal otomatis (seperti *Mode Khusus Hari Senin: Upacara Ditiadakan / KBM Dimajukan 1 JP*) yang sinkron dengan jam dinding real-time.
- 📝 **Batch Fill Jurnal Mengajar:** Fitur otomatisasi pengisian presensi & materi 1-kali klik untuk slot jam mengajar yang berurutan dalam 1 blok mapel.
- ⏱️ **Real-Time Keterlambatan Tracking:** Sistem cerdas berbasis waktu server yang memverifikasi status pengisian jurnal secara adil (`Sudah Terisi`, `Terisi (Terlambat)`, dan `Belum Terisi (Terlambat)`).
- 👥 **Multi-Role Access Control:** 
  - **Tata Usaha (TU):** Pengelolaan Data Master (Pengguna/Guru, Data Siswa, Data Kelas, dan Data Jurusan).
  - **Waka Kurikulum:** Plotting Jadwal Pelajaran & Pengaturan Mode Khusus Hari.
  - **Guru Mapel / Wali Kelas:** Presensi & Pengisian Jurnal Mengajar Harian.
- 🛡️ **Data Integrity Guard:** Proteksi relasi data ketat (seperti validasi *soft-delete* guru wali kelas dan pencegahan bentrok *unique constraint* pada jadwal).

## 🛠️ Tech Stack

- **Framework:** [Laravel 11](https://laravel.com)
- **Language:** PHP 8.2+
- **Database:** MySQL / MariaDB
- **Frontend:** Blade, Tailwind CSS, Alpine.js

## 🚀 Panduan Instalasi (Getting Started)

### 1. Clone Repository
```bash
git clone [https://github.com/username-kamu/webjournal.git](https://github.com/username-kamu/webjournal.git)
cd webjournal

```

### 2. Install Dependensi

```bash
composer install
npm install && npm run build

```

### 3. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate

```

*Sesuaikan konfigurasi koneksi `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di file `.env`.*

### 4. Migrate & Seed Database

```bash
php artisan migrate --seed

```

### 5. Jalankan Local Server

```bash
php artisan serve

```
