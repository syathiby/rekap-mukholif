## APLIKASI REKAP MUKHOLLIF

### 1. Buat File `.env`

Buat sebuah file baru di direktori utama proyek (sejajar dengan `db.php`) dan beri nama `.env` (diawali dengan titik). Isi file tersebut dengan:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=db_mukholif
```

-----

### 2. Instal Library untuk Membaca `.env`

PHP tidak bisa membaca file `.env` secara bawaan. Perlu library tambahan. Cara termudah adalah menggunakan **Composer**. Jika belum punya Composer, silakan install terlebih dahulu.

Setelah selesai instalasi composer, Buka terminal di direktori proyek dan jalankan perintah:

```bash
composer require vlucas/phpdotenv
```

Ini akan membuat folder `vendor` dan file `composer.json` di proyek.

-----

### 3. Perbarui Kode `db.php`

Sekarang, ubah file `db.php` untuk memuat variabel dari `.env`.

```php
<?php

// 1. Muat autoloader dari Composer
require 'vendor/autoload.php';

// 2. Muat variabel dari file .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 3. Gunakan variabel dari $_ENV
$host     = $_ENV['DB_HOST'];
$user     = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$dbname   = $_ENV['DB_NAME'];

// Kode koneksi tetap sama
$conn = mysqli_connect(hostname: $host, username: $user, password: $password, database: $dbname);

if (!$conn) {
    die("koneksi gagal: " . mysqli_connect_error());
}

// Set timezone ke Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

?>
```