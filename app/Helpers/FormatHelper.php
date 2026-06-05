<?php
declare(strict_types=1);

namespace App\Helpers;

class FormatHelper {
    
    /**
     * Format tanggal Y-m-d ke format Indonesia (d F Y)
     */
    public static function tanggal(string $date): string {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $exp = explode('-', date('Y-m-d', strtotime($date)));
        return $exp[2] . ' ' . $bulan[(int)$exp[1]] . ' ' . $exp[0];
    }

    /**
     * Format tanggal dan waktu (d F Y H:i)
     */
    public static function waktu(string $datetime): string {
        if (empty($datetime)) return '-';
        
        $date = date('Y-m-d', strtotime($datetime));
        $time = date('H:i', strtotime($datetime));
        
        return self::tanggal($date) . ' ' . $time;
    }

    /**
     * Sanitasi output HTML untuk menghindari XSS
     */
    public static function escape(?string $string): string {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format angka dengan pemisah ribuan
     */
    public static function angka(int|float|null $number): string {
        if ($number === null) return '0';
        return number_format((float)$number, 0, ',', '.');
    }

    public static function timeAgo(string $datetime, bool $full = false): string {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'tahun',
            'm' => 'bulan',
            'w' => 'minggu',
            'd' => 'hari',
            'h' => 'jam',
            'i' => 'menit',
            's' => 'detik',
        ];

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$string) {
            return 'baru saja';
        }

        if ($full) {
            return implode(', ', $string) . ' lalu';
        } else {
            return reset($string) . ' lalu';
        }
    }
}
