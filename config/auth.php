<?php
// auth.php — Fungsi keamanan: guard(), has_permission(), logout(), get_user_effective_permissions()
// SESSION dijamin sudah aktif oleh bootstrap/init.php sebelum file ini dipanggil.

/**
 * =================================================================
 * FUNGSI PERHITUNGAN IZIN EFEKTIF (RBAC + ALLOW & DENY OVERRIDES)
 * =================================================================
 * Menghitung izin gabungan dari Role default + Override Khusus User.
 *
 * @param mysqli $conn Koneksi database
 * @param int $userId ID Pengguna
 * @param string $role ID Role Pengguna
 * @return array Daftar string nama_izin yang aktif
 */
if (!function_exists('get_user_effective_permissions')) {
    function get_user_effective_permissions($conn, $userId, $role) {
        $userId = (int)$userId;
        $role = strtolower(trim((string)$role));
        
        // Admin selalu punya semua izin
        if ($role === 'admin') {
            return ['*'];
        }

        $effective = [];

        // 1. Ambil izin dasar dari Role (role_permissions)
        $stmt = $conn->prepare("
            SELECT p.nama_izin 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role = ?
        ");
        if ($stmt) {
            $stmt->bind_param("s", $role);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $effective[$row['nama_izin']] = true;
            }
            $stmt->close();
        }

        // 2. Terapkan Override Khusus (user_permissions: Allow = 1, Deny = 0)
        $stmt = $conn->prepare("
            SELECT p.nama_izin, up.is_allowed 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if ((int)$row['is_allowed'] === 1) {
                    $effective[$row['nama_izin']] = true; // Allow / Tambahan
                } else {
                    unset($effective[$row['nama_izin']]); // Deny / Cabut
                }
            }
            $stmt->close();
        }

        return array_keys($effective);
    }
}

/**
 * =================================================================
 * REAL-TIME PERMISSION VERSIONING & SYNC
 * =================================================================
 */
define('PERMISSIONS_VERSION_FILE', __DIR__ . '/../cache/permissions_version.txt');

if (!function_exists('touch_permissions_version')) {
    function touch_permissions_version() {
        $dir = dirname(PERMISSIONS_VERSION_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(PERMISSIONS_VERSION_FILE, (string)time(), LOCK_EX);
    }
}

if (!function_exists('get_permissions_version')) {
    function get_permissions_version() {
        if (file_exists(PERMISSIONS_VERSION_FILE)) {
            return (int)@file_get_contents(PERMISSIONS_VERSION_FILE);
        }
        return 0;
    }
}

if (!function_exists('sync_user_session_permissions')) {
    function sync_user_session_permissions($conn) {
        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            return;
        }
        
        $currentVersion = get_permissions_version();
        $sessionVersion = $_SESSION['permissions_version'] ?? -1;

        if ($sessionVersion < $currentVersion || !isset($_SESSION['permissions'])) {
            $_SESSION['permissions'] = get_user_effective_permissions($conn, $_SESSION['user_id'], $_SESSION['role']);
            $_SESSION['permissions_version'] = $currentVersion;
        }
    }
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
        if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
            return true;
        }
        
        // Jika user belum login atau tidak punya daftar izin, auto tolak.
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }

        $userPermissions = $_SESSION['permissions'];
        
        // Jika user memiliki wildcard '*'
        if (in_array('*', $userPermissions, true)) {
            return true;
        }
        
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
            // Tambahkan ?illegal=1 agar login.php mencatatnya sebagai pelanggaran
            header("Location: " . BASE_URL . "/login.php?illegal=1");
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
        // Gunakan BASE_URL untuk path dinamis
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}