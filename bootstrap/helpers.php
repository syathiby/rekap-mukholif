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

    // PERBAIKAN: Hanya gunakan REMOTE_ADDR yang tidak bisa dipalsukan.
    // HTTP_CLIENT_IP dan HTTP_X_FORWARDED_FOR bisa dimanipulasi oleh penyerang.
    // Jika suatu saat perlu proxy support, aktifkan kembali dengan whitelist IP proxy.
    $ipAddress = !empty($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '0.0.0.0';

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

/**
 * =================================================================
 * FUNGSI MUSYRIF KAMAR (RBAC EXTENSION)
 * =================================================================
 */

/**
 * Validasi akses Musyrif terhadap kamar.
 * Wajib dipanggil di awal setiap endpoint/controller CRUD Rapot.
 *
 * @return int|null kamar_id jika musyrif, null jika bukan musyrif.
 */
function checkMusyrifKamarAccess() {
    global $conn;
    
    // Jika belum login, biarkan auth middleware yang handle
    if (empty($_SESSION['user_id'])) return null;
    
    // Jika bukan musyrif, tidak ada batasan kamar (return null)
    if (strtolower($_SESSION['role'] ?? '') !== 'musyrif') {
        return null; 
    }
    
    // Jika musyrif, ambil data kamar_id terbaru dari DB
    $stmt = $conn->prepare("SELECT kamar_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$res || empty($res['kamar_id'])) {
        // Musyrif tanpa kamar_id -> Akses Ditolak
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "error_code" => "MUSYRIF_KAMAR_NOT_CONFIGURED",
                "message" => "Kamar Musyrif belum dikonfigurasi. Silakan hubungi Administrator."
            ]);
            exit;
        } else {
            http_response_code(403);
            $error_msg = "Akun Musyrif Anda belum terhubung dengan kamar yang menjadi tanggung jawab. Silakan hubungi Administrator untuk melakukan pengaturan kamar terlebih dahulu.";
            
            // Render beautiful error page
            echo '<!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Akses Ditolak</title>
                <style>
                    body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                    .error-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
                    .error-icon { font-size: 48px; color: #dc3545; margin-bottom: 20px; }
                    .error-title { color: #343a40; margin-bottom: 15px; font-size: 24px; }
                    .error-msg { color: #6c757d; line-height: 1.6; margin-bottom: 25px; }
                    .btn-back { display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; transition: background 0.2s; }
                    .btn-back:hover { background: #0b5ed7; }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">&#10060;</div>
                    <h1 class="error-title">Akses Tidak Dapat Dilanjutkan</h1>
                    <p class="error-msg">' . $error_msg . '</p>
                    <a href="javascript:history.back()" class="btn-back">Kembali Halaman Sebelumnya</a>
                </div>
            </body>
            </html>';
            exit;
        }
    }
    
    return (int)$res['kamar_id'];
}
?>