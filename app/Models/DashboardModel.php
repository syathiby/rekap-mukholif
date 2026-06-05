<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DashboardModel extends Model {
    
    public function getActivePeriod(): string {
        $sql = "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif'";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return $result ? $result['nilai'] : '2000-01-01';
    }

    public function getStats(string $startDate, string $endDate): array {
        $stats = [];
        
        $sqlUmum = "SELECT COUNT(*) AS total FROM pelanggaran WHERE tanggal BETWEEN :start AND :end";
        $stmtUmum = $this->db->prepare($sqlUmum);
        $stmtUmum->execute([':start' => $startDate, ':end' => $endDate]);
        $stats['pelanggaran_umum'] = (int) $stmtUmum->fetchColumn();

        $sqlKebersihan = "SELECT COUNT(*) AS total FROM pelanggaran_kebersihan WHERE tanggal BETWEEN :start AND :end";
        $stmtKeb = $this->db->prepare($sqlKebersihan);
        $stmtKeb->execute([':start' => $startDate, ':end' => $endDate]);
        $stats['pelanggaran_kebersihan'] = (int) $stmtKeb->fetchColumn();

        $stats['total_pelanggaran'] = $stats['pelanggaran_umum'] + $stats['pelanggaran_kebersihan'];

        $sqlSantri = "SELECT COUNT(*) AS total FROM santri";
        $stats['santri'] = (int) $this->db->query($sqlSantri)->fetchColumn();

        $sqlJp = "SELECT COUNT(*) AS total FROM jenis_pelanggaran";
        $stats['jenis_pelanggaran'] = (int) $this->db->query($sqlJp)->fetchColumn();

        $sqlTeladan = "SELECT COUNT(s.id) as total 
                       FROM santri s
                       WHERE NOT EXISTS (
                           SELECT 1 FROM pelanggaran p 
                           WHERE p.santri_id = s.id AND p.tanggal BETWEEN :start AND :end
                       )";
        $stmtTeladan = $this->db->prepare($sqlTeladan);
        $stmtTeladan->execute([':start' => $startDate, ':end' => $endDate]);
        $stats['santri_tanpa_pelanggaran'] = (int) $stmtTeladan->fetchColumn();

        return $stats;
    }

    public function getRecentViolations(): array {
        $sql = "
            (
                SELECT p.id, s.nama, s.kamar, jp.nama_pelanggaran, p.tanggal, u.nama_lengkap AS pencatat
                FROM pelanggaran p
                JOIN santri s ON p.santri_id = s.id
                JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                LEFT JOIN users u ON p.dicatat_oleh = u.id
                WHERE p.tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
            UNION ALL
            (
                SELECT pk.id, 'Penghuni Kamar' AS nama, pk.kamar, 'Kebersihan Kamar' AS nama_pelanggaran, pk.tanggal, u.nama_lengkap AS pencatat
                FROM pelanggaran_kebersihan pk
                LEFT JOIN users u ON pk.dicatat_oleh = u.id
                WHERE pk.tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            )
            ORDER BY tanggal DESC
            LIMIT 7
        ";
        return $this->db->query($sql)->fetchAll();
    }

    public function getFrequentViolation(string $startDate, string $endDate): ?array {
        $sql = "
            SELECT nama_pelanggaran, SUM(total) AS total_gabungan FROM (
                SELECT jp.nama_pelanggaran, COUNT(*) as total 
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                WHERE p.tanggal BETWEEN :start1 AND :end1
                GROUP BY jp.nama_pelanggaran
                UNION ALL
                SELECT 'Kebersihan Kamar' AS nama_pelanggaran, COUNT(*) AS total
                FROM pelanggaran_kebersihan pk
                WHERE pk.tanggal BETWEEN :start2 AND :end2
                HAVING COUNT(*) > 0
            ) AS gabung
            GROUP BY nama_pelanggaran
            ORDER BY total_gabungan DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start1' => $startDate, ':end1' => $endDate,
            ':start2' => $startDate, ':end2' => $endDate
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getTopViolators(string $startDate, string $endDate): array {
        $sql = "
            SELECT 
                s.nama, 
                s.kamar, 
                SUM(jp.poin) as total_poin,
                COALESCE(rwd.total_reward, 0) as total_reward,
                (SUM(jp.poin) - COALESCE(rwd.total_reward, 0)) AS poin_bersih_periode
            FROM pelanggaran p
            JOIN santri s ON p.santri_id = s.id
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            LEFT JOIN (
                SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
                FROM daftar_reward dr
                JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                WHERE dr.tanggal BETWEEN :start1 AND :end1
                GROUP BY dr.santri_id
            ) rwd ON s.id = rwd.santri_id
            WHERE p.tanggal BETWEEN :start2 AND :end2
            GROUP BY s.id
            ORDER BY total_poin DESC, s.nama ASC
            LIMIT 5
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start1' => $startDate, ':end1' => $endDate,
            ':start2' => $startDate, ':end2' => $endDate
        ]);
        return $stmt->fetchAll();
    }

    public function getBestStudents(string $startDate, string $endDate): array {
        $sql = "
            SELECT s.id, s.nama, s.kelas, s.kamar,
                   COALESCE(rwd.total_reward, 0) AS total_reward,
                   COALESCE(rpt.avg_rapot, 0) AS avg_rapot
            FROM santri s
            LEFT JOIN (
                SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
                FROM daftar_reward dr
                JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                WHERE dr.tanggal BETWEEN :start1 AND :end1
                GROUP BY dr.santri_id
            ) rwd ON s.id = rwd.santri_id
            LEFT JOIN (
                SELECT santri_id, 
                       ((AVG(puasa_sunnah) + AVG(sholat_duha) + AVG(sholat_malam) + AVG(sedekah) + AVG(sunnah_tidur) + AVG(ibadah_lainnya) + 
                         AVG(lisan) + AVG(sikap) + AVG(kesopanan) + AVG(muamalah) + 
                         AVG(tidur) + AVG(keterlambatan) + AVG(seragam) + AVG(makan) + AVG(arahan) + AVG(bahasa_arab) + 
                         AVG(mandi) + AVG(penampilan) + AVG(piket) + AVG(kerapihan_barang)) / 20) AS avg_rapot
                FROM rapot_kepengasuhan
                WHERE STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
                      BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(:start2, '%Y-%m'), '-01'), '%Y-%m-%d') 
                      AND LAST_DAY(:end2)
                GROUP BY santri_id
            ) rpt ON s.id = rpt.santri_id
            WHERE s.id NOT IN (
                SELECT p.santri_id FROM pelanggaran p WHERE p.tanggal BETWEEN :start3 AND :end3
            )
            ORDER BY total_reward DESC, avg_rapot DESC, CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC
            LIMIT 5
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start1' => $startDate, ':end1' => $endDate,
            ':start2' => $startDate, ':end2' => $endDate,
            ':start3' => $startDate, ':end3' => $endDate
        ]);
        return $stmt->fetchAll();
    }

    public function getTopHistory(): array {
        $sql = "
            SELECT id, nama, kelas, kamar, poin_aktif
            FROM santri
            WHERE poin_aktif > 0
            ORDER BY poin_aktif DESC, nama ASC
            LIMIT 5
        ";
        return $this->db->query($sql)->fetchAll();
    }
}

