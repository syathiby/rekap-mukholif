<?php
// =================================================================
// MARKAS KOMANDO APLIKASI (INIT.PHP)
// File ini adalah yang pertama kali dipanggil di setiap halaman.
// Isinya hanya logic, tidak ada HTML.
// =================================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    session_start();
}

// Protokol 1: Panggil file config sakti kita, ini yang paling pertama!
require_once __DIR__ . '/config.php'; 

// Protokol 2: Panggil file dari folder yang sama
require_once __DIR__ . '/db.php';
// Protokol 3: Panggil file dari folder yang sama
require_once __DIR__ . '/auth.php';
// ✅ Protokol 4: Panggil kotak perkakas helper kita!
require_once __DIR__ . '/helpers.php';

// Protokol 5: Siapin data-data umum
date_default_timezone_set('Asia/Jakarta');