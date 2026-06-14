<?php
// helpers.php — Fungsi serbaguna: flash message, format data, dll.
// SESSION dijamin sudah aktif oleh bootstrap/init.php sebelum file ini dipanggil.

/**
 * =================================================================
 * FUNGSI PESAN KILAT (FLASH MESSAGE)
 * =================================================================
 */

/**
 * Menyimpan pesan flash ke dalam session.
 * Pesan ini hanya akan ditampilkan satu kali.
 *
 * @param string $type    Jenis pesan (e.g., 'success', 'danger', 'warning', 'info').
 * Ini akan menentukan warna notifikasinya.
 * @param string $message Isi pesan yang ingin ditampilkan.
 */
function set_flash_message($message, $type = 'success')
{
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Menampilkan pesan flash jika ada, lalu menghapusnya dari session.
 * Fungsi ini harus dipanggil di halaman di mana notifikasi ingin ditampilkan
 * (biasanya di bawah header atau di atas konten utama).
 */
function display_flash_message()
{
    $has_message = false;
    $type = '';
    $message = '';

    if (isset($_SESSION['flash_message'])) {
        $type    = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        unset($_SESSION['flash_message']);
        $has_message = true;
    } elseif (isset($_SESSION['message'])) {
        // Tangkap juga format $_SESSION['message'] yang lama
        $type    = $_SESSION['message']['type'];
        $message = isset($_SESSION['message']['text']) ? $_SESSION['message']['text'] : (isset($_SESSION['message']['message']) ? $_SESSION['message']['message'] : '');
        unset($_SESSION['message']);
        if (!empty($message)) {
            $has_message = true;
        }
    }

    if ($has_message) {
        $swal_icon = $type;
        if ($type === 'danger') {
            $swal_icon = 'error';
        }

        // Tentukan judul berdasarkan ikon (EYD yang baik)
        $title = 'Informasi';
        if ($swal_icon === 'success') {
            $title = 'Berhasil!';
        } elseif ($swal_icon === 'error') {
            $title = 'Oops...';
        } elseif ($swal_icon === 'warning') {
            $title = 'Peringatan!';
        }
        
        $message_js = addslashes($message);
        $title_js = addslashes($title);

        echo "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
                Toast.fire({
                    icon: '{$swal_icon}',
                    title: '{$title_js}',
                    text: '{$message_js}'
                });
            }
        });
        </script>
        ";
    }
}

/**
 * =================================================================
 * FUNGSI BANTUAN LAINNYA
 * =================================================================
 */

/**
 * Mencatat aktivitas pengguna ke dalam database.
 *
 * @param string $aksi      Jenis aksi (e.g., 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'RESET_POIN', 'RESTORE', 'BACKUP', 'UPDATE_PERMISSION', 'CHANGE_PERIODE')
 * @param string $fitur     Kategori fitur/modul (e.g., 'santri', 'pelanggaran', 'reward', 'users', 'izin', 'periode-aktif', 'reset-poin', 'backup-restore', 'auth')
 * @param string $deskripsi Keterangan aktivitas yang ramah pengguna
 * @param mixed  $detail    Data tambahan (seperti array data lama vs baru) yang akan dikonversi ke JSON
 */
function write_activity_log($aksi, $fitur, $deskripsi, $detail = null)
{
    global $conn;

    // Pastikan koneksi database tersedia
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    // Ambil data user dari session
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Sistem';
    $namaLengkap = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Sistem Otomatis';

    // Deteksi IP Address secara aman
    $ipAddress = '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }
    $ipAddress = trim($ipAddress);

    // Deteksi User Agent
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';

    // Format detail ke JSON jika ada
    $detailJson = null;
    if ($detail !== null) {
        $detailJson = json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Insert ke database menggunakan prepared statement agar aman dari SQL Injection
    $stmt = $conn->prepare("INSERT INTO log_aktifitas (user_id, username, nama_lengkap, aksi, fitur, deskripsi, detail, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssssss", $userId, $username, $namaLengkap, $aksi, $fitur, $deskripsi, $detailJson, $ipAddress, $userAgent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    return false;
}

/**
 * =================================================================
 * FUNGSI KEAMANAN CSRF
 * =================================================================
 */

/**
 * Generate CSRF token baru jika belum ada di session.
 * Panggil fungsi ini di AWAL setiap halaman yang menampilkan form
 * (sebelum require header.php).
 *
 * @return string Token CSRF yang aktif
 */
function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari request POST.
 * Langsung hentikan eksekusi dan tampilkan halaman error jika tidak valid.
 * Gunakan ini di awal blok POST di file process.php atau handler lainnya.
 */
function csrf_validate(): void {
    $token_post    = $_POST['csrf_token'] ?? '';
    $token_session = $_SESSION['csrf_token'] ?? '';

    if (empty($token_post) || empty($token_session) || !hash_equals($token_session, $token_post)) {
        http_response_code(403);
        // Cari path yang benar (bisa dipanggil dari berbagai kedalaman folder)
        $csrf_page = __DIR__ . '/csrf_expired.php';
        if (file_exists($csrf_page)) {
            require $csrf_page;
        } else {
            // Fallback jika path tidak ditemukan
            header('HTTP/1.1 403 Forbidden');
            echo '<p>Token keamanan tidak valid. Silakan kembali dan coba lagi.</p>';
        }
        exit;
    }
}

/**
 * =================================================================
 * FUNGSI FORMATTING
 * =================================================================
 */

/**
 * Menyelaraskan (capitalize) format teks untuk dropdown, label, dan tabel.
 * Mengubah "DINIYYAH" -> "Diniyyah", "BAHASA" -> "Bahasa".
 * 
 * @param string $string Teks yang ingin diformat
 * @return string Teks yang sudah diformat konsisten
 */
function format_typing($string) {
    if (empty($string)) return $string;
    return ucwords(strtolower(trim($string)));
}
?>