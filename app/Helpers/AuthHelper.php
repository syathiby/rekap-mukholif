<?php
declare(strict_types=1);

namespace App\Helpers;

class AuthHelper {
    
    /**
     * Cek apakah user yang login memiliki izin tertentu.
     * Menggunakan session ['permissions'] yang diisi saat login.
     * Tidak melakukan query database.
     *
     * @param string|array $namaIzin
     * @return bool
     */
    public static function hasPermission(string|array $namaIzin): bool {
        // Jika belum login, pasti false
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Bypass untuk admin: role 'admin' diizinkan semua hal
        if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
            return true;
        }

        // Ambil array izin user dari session
        $userPermissions = $_SESSION['permissions'] ?? [];

        // Jika string, cek apakah ada di array
        if (is_string($namaIzin)) {
            return in_array($namaIzin, $userPermissions, true);
        }

        // Jika array, cek apakah ada minimal salah satu (OR logic)
        // atau bisa juga AND logic tergantung kebutuhan, 
        // tapi PRD biasanya mengimplikasikan OR untuk multiple
        if (is_array($namaIzin)) {
            foreach ($namaIzin as $izin) {
                if (in_array($izin, $userPermissions, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Pastikan user memiliki izin, jika tidak langsung lempar 403 Forbidden.
     * Mendukung deteksi request HTMX.
     *
     * @param string|array $namaIzin
     */
    public static function requirePermission(string|array $namaIzin): void {
        if (!self::hasPermission($namaIzin)) {
            http_response_code(403);
            
            $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
            $viewFile = defined('VIEW_PATH') ? VIEW_PATH . '/errors/403.php' : __DIR__ . '/../../views/errors/403.php';
            
            if ($isHtmx) {
                if (file_exists($viewFile)) {
                    require $viewFile;
                } else {
                    echo "<h2>403 Forbidden</h2><p>Anda tidak memiliki akses.</p>";
                }
            } else {
                if (file_exists($viewFile)) {
                    ob_start();
                    require $viewFile;
                    $content = ob_get_clean();
                    
                    $layoutFile = defined('VIEW_PATH') ? VIEW_PATH . '/layouts/main.php' : __DIR__ . '/../../views/layouts/main.php';
                    if (file_exists($layoutFile)) {
                        require $layoutFile;
                    } else {
                        echo $content;
                    }
                } else {
                    echo "<h2>403 Forbidden</h2><p>Anda tidak memiliki akses.</p>";
                }
            }
            exit;
        }
    }
}
