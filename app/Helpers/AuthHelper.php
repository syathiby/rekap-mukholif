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
}
