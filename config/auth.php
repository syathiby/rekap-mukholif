<?php
// auth.php — Fungsi keamanan: guard(), has_permission(), logout()
// SESSION dijamin sudah aktif oleh bootstrap/init.php sebelum file ini dipanggil.

/**
 * =================================================================
 * FUNGSI SATPAM SAKTI v4: has_permission()
 * =================================================================
 * Cek apakah user punya setidaknya SATU dari izin yang diminta.
 *
 * @param string|array $requiredPermissions Izin yang dibutuhkan, bisa string atau array.
 * @return bool True jika punya izin, false jika tidak.
 */
if (!function_exists('has_permission')) {
    function has_permission($requiredPermissions) {
        // Aturan Emas: Admin selalu punya akses ke semuanya.
        if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
            return true;
        }
        
        // Jika user belum login atau tidak punya daftar izin, auto tolak.
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }

        $userPermissions = $_SESSION['permissions'];
        
        // Pastikan izin yang diminta selalu dalam bentuk array biar gampang dicek.
        $required = is_array($requiredPermissions) ? $requiredPermissions : [$requiredPermissions];
        
        // Cek apakah ada irisan antara izin user dan izin yang dibutuhkan.
        // Kalau irisannya tidak kosong, berarti ada izin yang cocok.
        $commonPermissions = array_intersect($required, $userPermissions);

        return !empty($commonPermissions);
    }
}


/**
 * =================================================================
 * PROTOKOL PENJAGAAN v4: guard()
 * =================================================================
 * Fungsi ini menjaga halaman atau proses.
 * Jika user tidak lolos, akan ditendang atau dihentikan.
 *
 * @param string|array|null $permission Izin yang dibutuhkan. 
 * Jika null, hanya cek login.
 */
if (!function_exists('guard')) {
    function guard($permission = null) {
        // Tambahkan header anti-cache agar halaman terproteksi tidak disimpan di cache browser (mencegah bypass tombol Back)
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        }

        // Peraturan #1: Belum login? Tendang ke halaman login.
        if (!isset($_SESSION['user_id'])) {
            // REVISI: Gunakan BASE_URL untuk path dinamis
            header("Location: " . BASE_URL . "/login.php");
            exit; // Wajib: Hentikan eksekusi skrip setelah redirect.
        }

        // Peraturan #2: Kalau ada tiket izin yang harus dicek...
        if ($permission !== null) {
            // ...dan ternyata user gak punya tiketnya...
            if (!has_permission($permission)) {
                // ...kirim status 403 Forbidden (Akses Ditolak) ke browser.
                http_response_code(403);
                
                // ...langsung tampilkan halaman 'access_denied.php' di sini juga.
                // Metode 'require' lebih baik daripada redirect karena URL di browser tidak berubah.
                require __DIR__ . '/../bootstrap/access_denied.php';
                
                // ...dan yang paling penting, hentikan paksa eksekusi skrip.
                exit; 
            }
        }
        // Kalau lolos semua peraturan, berarti aman. Lanjutkan!
    }
}


/**
 * =================================================================
 * FUNGSI PENGHANCUR SESI v3: logout()
 * =================================================================
 * Menghancurkan session dan mengarahkan ke halaman login.
 */
if (!function_exists('logout')) {
    function logout() {
        // Mulai session jika belum aktif, untuk mengaksesnya
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Hapus semua variabel session
        $_SESSION = array();

        // Hapus session cookie di browser secara manual
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Hancurkan session di server
        session_destroy();

        // Arahkan pengguna kembali ke halaman login
        // REVISI: Gunakan BASE_URL untuk path dinamis
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}