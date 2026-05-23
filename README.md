## APLIKASI REKAP MUKHOLLIF

### 1. Buat File `.env`

Buat sebuah file baru di direktori utama proyek (sejajar dengan `index.php` dan `composer.json`) dan beri nama `.env` (diawali dengan titik). Isi file tersebut dengan:

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

### 3. File Konfigurasi Database (`config/database.php`)

Konfigurasi koneksi database telah dipindahkan ke folder konfigurasi pusat di `config/database.php` yang secara otomatis memuat variabel dari `.env`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host     = $_ENV['DB_HOST'];
$user     = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$dbname   = $_ENV['DB_NAME'];

$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    die("koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Jakarta');

?>
```