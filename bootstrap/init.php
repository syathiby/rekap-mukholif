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
require_once __DIR__ . '/../config/app.php'; 

// Protokol 2: Panggil file database
require_once __DIR__ . '/../config/database.php';
// Protokol 3: Panggil file autentikasi
require_once __DIR__ . '/../config/auth.php';
// ✅ Protokol 4: Panggil kotak perkakas helper kita!
require_once __DIR__ . '/helpers.php';

// Protokol 5: Siapin data-data umum
date_default_timezone_set('Asia/Jakarta');

// Protokol 6: Cek auto-logout setelah login 1 jam (3600 detik) demi keamanan
if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 3600) {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header("Location: " . BASE_URL . "/login.php?timeout=1");
        exit();
    }
}