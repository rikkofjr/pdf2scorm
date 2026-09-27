====================================================================
               PACKAGE BUILDER SCORM & KUIS LARAVEL                 
====================================================================

Aplikasi berbasis Laravel untuk membuat, mengelola, dan menghasilkan 
(generate) paket materi pembelajaran interaktif berstandar SCORM 1.2 (.zip) 
secara otomatis.

Paket SCORM yang dihasilkan sudah dilengkapi dengan PDF Viewer interaktif 
(PDF.js), Sistem Kuis Evaluasi, serta SCORM API Integration yang siap 
diunggah dan dijalankan langsung di Learning Management System (LMS) 
seperti Moodle.


--------------------------------------------------------------------
1. FITUR UTAMA
--------------------------------------------------------------------
- Dynamic Quiz Builder Backend: Mendukung pengelolaan data modul, PDF, 
  dan soal kuis beserta pilihan jawabannya secara dinamis melalui database.
- Automatic SCORM 1.2 Packing: Mengompres PDF, engine penampil, kuis, 
  dan file imsmanifest.xml menjadi berkas .zip siap pakai.
- Interactive SCORM Player UI:
  * Penampil PDF responsif berbasis PDF.js.
  * Katalog/navigasi slide dan kuis evaluasi.
  * Kuis interaktif dengan validasi jawaban langsung.
  * Integrasi status SCORM (cmi.core.lesson_status: In Progress / Completed).
- Responsive Layout: Tampilan disesuaikan untuk perangkat desktop 
  maupun seluler.


--------------------------------------------------------------------
2. PERSYARATAN SISTEM
--------------------------------------------------------------------
Pastikan perangkat Anda sudah terinstal perangkat lunak berikut:

- PHP >= 8.1 (dengan ekstensi zip, gd, pdo, mbstring, fileinfo aktif)
- Composer >= 2.x
- Database Server: MySQL / MariaDB / PostgreSQL


--------------------------------------------------------------------
3. LANGKAH INSTALASI
--------------------------------------------------------------------
Ikuti langkah-langkah di bawah ini untuk menjalankan aplikasi di 
lingkungan lokal Anda:

1. Clone Repositori:
   download / clone repository ini 

2. Instal Dependensi PHP:
   composer install

3. Konfigurasi Lingkungan (.env):
   cp .env.example .env

   Buka file .env dan atur koneksi database Anda:
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=db_scorm_builder
   DB_USERNAME=root
   DB_PASSWORD=

4. Generate Application Key:
   php artisan key:generate

5. Jalankan Migrasi Database:
   php artisan migrate

6. Buat Symbolic Link Storage:
   php artisan storage:link


--------------------------------------------------------------------
4. STRUKTUR TEMPLATE SCORM (WAJIB)
--------------------------------------------------------------------
Pastikan pustaka pendukung SCORM sudah diletakkan di direktori storage 
sebelum fungsi generate dijalankan:

storage/
└── app/
    └── scorm_template/
        └── js/
            ├── pdf.min.js
            ├── pdf.worker.min.js
            └── scorm-api-wrapper.js


--------------------------------------------------------------------
5. CARA MENGGUNAKAN APLIKASI
--------------------------------------------------------------------
Memanggil Service pada Controller / Command:

Proses generate paket SCORM dapat dipanggil melalui ScormQuizService 
dengan mengumpankan instance model Package.

Contoh Penggunaan pada Controller:

<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\ScormQuizService;

class PackageController extends Controller
{
    public function generate(Package $package, ScormQuizService $scormService)
    {
        // Menghasilkan file .zip SCORM
        $zipPath = $scormService->generateZip($package);

        // Mengunduh file .zip yang berhasil di-generate
        return response()->download($zipPath);
    }
}


Menjalankan Server Lokal:
php artisan serve


--------------------------------------------------------------------
6. STRUKTUR PROJECT UTAMA
--------------------------------------------------------------------
app/
├── Models/
│   ├── Package.php           # Model data paket/modul SCORM
│   ├── QuizQuestion.php      # Model daftar pertanyaan
│   └── QuizOption.php        # Model pilihan & kunci jawaban
└── Services/
    └── ScormQuizService.php  # Service pembuat manifest, HTML player, & ZIP

