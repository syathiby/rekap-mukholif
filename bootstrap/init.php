<?php
// =================================================================
// MARKAS KOMANDO APLIKASI (INIT.PHP) — Versi Optimal
// File ini adalah SATU-SATUNYA entry-point inisialisasi.
// Dipanggil pertama kali di setiap halaman. Isinya hanya logic.
// =================================================================

/**
 * OPTIMASI #1: Production Safety — Matikan tampilan error ke browser.
 * Error tetap dicatat di error_log server Hostinger, tidak bocor ke user.
 * Ini juga mencegah PHP Warning/Notice muncul di tengah HTML dan
 * merusak tampilan di HP user.
 */
if (!ini_get('display_errors')) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

/**
 * OPTIMASI #2: Session Config — Atur sebelum session_start().
 * - gc_maxlifetime: waktu hidup session file di server (24 jam = 86400 detik)
 *   Ini batas MAKSIMUM. Inactivity timeout (12 jam) diatur di bawah.
 * - cookie_lifetime: 0 = session cookie (hilang saat browser ditutup)
 * - cookie_httponly: Blokir JavaScript mengakses cookie session (anti-XSS)
 * - cookie_samesite: Proteksi CSRF dasar
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * OPTIMASI #3: Timezone — Sumber Kebenaran Tunggal.
 * Hanya dipanggil DI SINI, tidak di config/database.php lagi.
 * Menghilangkan 1 fungsi PHP call yang redundan di setiap request.
 */
date_default_timezone_set('Asia/Jakarta');

// ─── LOAD KOMPONEN SISTEM ─────────────────────────────────────────────
// Urutan pemanggilan PENTING — jangan diubah.

// Protokol 1: Konstanta aplikasi (BASE_URL, dll) — paling pertama.
require_once __DIR__ . '/../config/app.php';

// Protokol 2: Koneksi database (Singleton — hanya buat koneksi 1x).
require_once __DIR__ . '/../config/database.php';

// Protokol 3: Fungsi auth: guard(), has_permission(), logout().
require_once __DIR__ . '/../config/auth.php';

// Protokol 4: Fungsi helper: set_flash_message(), display_flash_message().
require_once __DIR__ . '/helpers.php';

// Protokol 5: Ambil Pengaturan Global (Dieksekusi 1x per request, tersedia di semua halaman)
$q_periode_global = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif_row = mysqli_fetch_assoc($q_periode_global);
define('PERIODE_AKTIF', $periode_aktif_row ? $periode_aktif_row['nilai'] : '2000-01-01');

// ─────────────────────────────────────────────────────────────────────

/**
 * OPTIMASI #4: Inactivity Timeout Check yang Efisien.
 * Cek hanya dilakukan jika KEDUA kunci session ada (short-circuit evaluation).
 * PHP mengevaluasi `isset` pertama; jika false, tidak lanjut ke kondisi berikutnya.
 * Tidak ada query database di sini — murni operasi session (RAM).
 */
if (isset($_SESSION['user_id'], $_SESSION['login_time'])) {
    // Batas inaktif: 3 jam (10800 detik)
    if ((time() - $_SESSION['login_time']) > 10800) {
        // Hancurkan session secara bersih
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: ' . BASE_URL . '/logout.php?timeout=1');
        exit();
    }

    // Reset timer aktivitas — user masih aktif, perpanjang sesi.
    $_SESSION['login_time'] = time();
}