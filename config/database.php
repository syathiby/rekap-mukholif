<?php
// =================================================================
// KONEKSI DATABASE — AsuhTrack (Versi Optimal)
// Optimasi: Static Dotenv (baca .env 1x per proses), Singleton $conn
// =================================================================

/**
 * OPTIMASI #1: Static Flag — Mencegah Dotenv membaca file .env
 * berulang kali. Pada PHP standard (bukan CLI long-running), ini
 * memastikan dalam satu lifecycle request, file .env hanya di-parse
 * satu kali meski require_once sudah menjamin itu di level file,
 * namun jika ada include manual di file lain (misal login.php),
 * static ini menjadi safety net mutlak.
 */
static $__db_env_loaded = false;
if (!$__db_env_loaded) {
    // Autoload hanya dipanggil sekali karena Composer sudah handle de-dup
    if (!class_exists('Dotenv\Dotenv', false)) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    $__db_env_loaded = true;
}

/**
 * OPTIMASI #2: Singleton Connection — $conn dibuat hanya SATU KALI
 * per request lifecycle. Jika file ini di-require_once berkali-kali,
 * koneksi yang sudah ada akan langsung dipakai ulang.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    $__db_host = $_ENV['DB_HOST']     ?? 'localhost';
    $__db_user = $_ENV['DB_USER']     ?? '';
    $__db_pass = $_ENV['DB_PASSWORD'] ?? '';
    $__db_name = $_ENV['DB_NAME']     ?? '';

    // Gunakan OOP mysqli — lebih bersih, siap untuk prepared statements
    $conn = new mysqli($__db_host, $__db_user, $__db_pass, $__db_name);

    /**
     * OPTIMASI #3: Error Handling Elegan & Aman
     * — Tidak ada stack trace / credential bocor ke browser user.
     * — Error dicatat ke error_log server (bisa dilihat di cPanel Hostinger).
     */
    if ($conn->connect_errno) {
        // Log detail error ke server (tidak terlihat user)
        error_log('[AsuhTrack] Koneksi database gagal: ' . $conn->connect_error);

        // Kirim HTTP 503 ke browser (Service Unavailable)
        http_response_code(503);

        // Tampilkan pesan generik yang aman ke user
        die('
        <div style="font-family:sans-serif;text-align:center;padding:40px;color:#555;">
            <h2>&#128683; Layanan Sementara Tidak Tersedia</h2>
            <p>Terjadi gangguan koneksi pada server kami. Silakan coba lagi dalam beberapa menit.</p>
            <small style="color:#aaa;">Kode: DB_CONN_ERR</small>
        </div>');
    }

    /**
     * OPTIMASI #4: Set Charset Eksplisit
     * Mencegah bug karakter (mojibake) pada data bahasa Arab / nama dengan
     * karakter khusus. Wajib untuk tabel yang menyimpan teks multibahasa.
     */
    $conn->set_charset('utf8mb4');

    // Bersihkan variabel kredensial dari memori setelah koneksi berhasil
    unset($__db_host, $__db_user, $__db_pass, $__db_name);
}

// CATATAN: date_default_timezone_set() DIHAPUS dari sini.
// Timezone kini diatur SATU KALI di bootstrap/init.php (sumber kebenaran tunggal).