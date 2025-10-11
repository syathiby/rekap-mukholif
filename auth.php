<?php
// Pastikan session sudah aktif sebelum file ini dipanggil.
if (session_status() === PHP_SESSION_NONE) {
    // Ini cuma buat jaga-jaga, harusnya session udah start di header.
    session_start();
}

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
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
                require __DIR__ . '/access_denied.php';
                
                // ...dan yang paling penting, hentikan paksa eksekusi skrip.
                exit; 
            }
        }
        // Kalau lolos semua peraturan, berarti aman. Lanjutkan!
    }
}


/**
 * =================================================================
 * FUNGSI PENGHANCUR SESI v2: logout()
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

        // Hancurkan session
        session_destroy();

        // Arahkan pengguna kembali ke halaman login
        // REVISI: Gunakan BASE_URL untuk path dinamis
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}