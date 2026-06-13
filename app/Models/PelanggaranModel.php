<?php

namespace App\Models;

use App\Core\Database;
use Exception;

class PelanggaranModel
{
    /**
     * Get violations by bagian, optionally filtered by date range.
     * Uses JOIN to get santri and jenis_pelanggaran details.
     */
    public static function getByBagian(string $bagian, ?string $periodeAwal = null, ?string $periodeAkhir = null): array
    {
        $conn = Database::getInstance()->getConnection();
        
        $sql = "SELECT p.*, s.nama as santri_nama, s.kamar as santri_kamar, s.kelas as santri_kelas,
                       jp.nama_pelanggaran, jp.poin, jp.bagian, jp.kategori
                FROM pelanggaran p
                JOIN santri s ON s.id = p.santri_id
                JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
                WHERE LOWER(jp.bagian) = LOWER(?)";
                
        $types = "s";
        $params = [$bagian];

        if ($periodeAwal && $periodeAkhir) {
            $sql .= " AND DATE(p.tanggal) BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $periodeAwal;
            $params[] = $periodeAkhir;
        }

        $sql .= " ORDER BY p.tanggal DESC, p.id DESC";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Get specific student's violation history.
     */
    public static function getBySantri(int $santriId): array
    {
        $conn = Database::getInstance()->getConnection();
        
        $sql = "SELECT p.*, jp.nama_pelanggaran, jp.poin, jp.bagian, jp.kategori
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
                WHERE p.santri_id = ?
                ORDER BY p.tanggal DESC, p.id DESC";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $santriId);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Get total violation points for a student, optionally filtered by date.
     */
    public static function getTotalPoinSantri(int $santriId, ?string $awal = null, ?string $akhir = null): int
    {
        $conn = Database::getInstance()->getConnection();
        
        $sql = "SELECT SUM(jp.poin) as total_poin
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
                WHERE p.santri_id = ?";
                
        $types = "i";
        $params = [$santriId];

        if ($awal && $akhir) {
            $sql .= " AND DATE(p.tanggal) BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $awal;
            $params[] = $akhir;
        }

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        mysqli_stmt_close($stmt);
        return (int)($row['total_poin'] ?? 0);
    }

    /**
     * Create violations for multiple students (Non-Bahasa).
     */
    public static function createBulk(array $santriIds, int $jenisPelanggaranId, string $tanggal, int $dicatatOleh): array
    {
        $conn = Database::getInstance()->getConnection();
        
        // 1. Get violation info
        $stmt_info = mysqli_prepare($conn, "SELECT poin FROM jenis_pelanggaran WHERE id = ?");
        mysqli_stmt_bind_param($stmt_info, "i", $jenisPelanggaranId);
        mysqli_stmt_execute($stmt_info);
        $result_info = mysqli_stmt_get_result($stmt_info);
        $jenisData = mysqli_fetch_assoc($result_info);
        mysqli_stmt_close($stmt_info);
        
        if (!$jenisData) {
            throw new Exception("Jenis pelanggaran tidak ditemukan.");
        }
        
        $poinBaru = (int)$jenisData['poin'];
        $insertedIds = [];

        // 2. Prepare queries
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)");
        $stmt_update_poin = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?");
        
        mysqli_begin_transaction($conn);
        
        try {
            foreach ($santriIds as $santriId) {
                $santriIdInt = (int)$santriId;
                
                // Insert
                mysqli_stmt_bind_param($stmt_insert, "iisi", $santriIdInt, $jenisPelanggaranId, $tanggal, $dicatatOleh);
                if (!mysqli_stmt_execute($stmt_insert)) {
                    throw new Exception("Gagal menyimpan data pelanggaran.");
                }
                $insertedIds[] = mysqli_insert_id($conn);
                
                // Update Poin
                if ($poinBaru > 0) {
                    mysqli_stmt_bind_param($stmt_update_poin, "ii", $poinBaru, $santriIdInt);
                    if (!mysqli_stmt_execute($stmt_update_poin)) {
                        throw new Exception("Gagal mengupdate poin santri.");
                    }
                }
            }
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_stmt_close($stmt_insert);
            mysqli_stmt_close($stmt_update_poin);
            throw $e;
        }

        mysqli_stmt_close($stmt_insert);
        mysqli_stmt_close($stmt_update_poin);
        
        return $insertedIds;
    }

    /**
     * Create violations for multiple students (Bahasa) with Replace & Log logic.
     * If $isClear is true, it just removes and logs old violations without inserting new ones.
     */
    public static function createBahasa(array $santriIds, int $jenisPelanggaranId, string $tanggal, int $dicatatOleh, bool $isClear = false): void
    {
        $conn = Database::getInstance()->getConnection();
        
        $poinBaru = 0;
        if (!$isClear) {
            $stmt_info = mysqli_prepare($conn, "SELECT poin FROM jenis_pelanggaran WHERE id = ? AND LOWER(bagian) = 'bahasa'");
            mysqli_stmt_bind_param($stmt_info, "i", $jenisPelanggaranId);
            mysqli_stmt_execute($stmt_info);
            $result_info = mysqli_stmt_get_result($stmt_info);
            $jenisData = mysqli_fetch_assoc($result_info);
            mysqli_stmt_close($stmt_info);
            
            if (!$jenisData) {
                throw new Exception("Jenis pelanggaran bahasa tidak valid.");
            }
            $poinBaru = (int)$jenisData['poin'];
        }

        $stmt_cari_lama = mysqli_prepare($conn, "SELECT p.id, p.jenis_pelanggaran_id, p.tanggal, jp.poin 
                                                 FROM pelanggaran p 
                                                 JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
                                                 WHERE p.santri_id = ? AND LOWER(jp.bagian) = 'bahasa'");
        $stmt_log = mysqli_prepare($conn, "INSERT INTO log_bahasa (santri_id, jenis_pelanggaran_id, poin_lama, tanggal_melanggar, diganti_oleh) VALUES (?, ?, ?, ?, ?)");
        $stmt_kurangi_poin = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        $stmt_hapus_lama = mysqli_prepare($conn, "DELETE FROM pelanggaran WHERE id = ?");
        
        $stmt_insert = null;
        $stmt_tambah_poin = null;
        if (!$isClear) {
            $stmt_insert = mysqli_prepare($conn, "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)");
            $stmt_tambah_poin = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?");
        }

        mysqli_begin_transaction($conn);

        try {
            foreach ($santriIds as $santriId) {
                $santriIdInt = (int)$santriId;
                
                // 1. Process old violations
                mysqli_stmt_bind_param($stmt_cari_lama, "i", $santriIdInt);
                mysqli_stmt_execute($stmt_cari_lama);
                $result_lama = mysqli_stmt_get_result($stmt_cari_lama);
                $old_violations = mysqli_fetch_all($result_lama, MYSQLI_ASSOC);
                
                foreach ($old_violations as $row_lama) {
                    $id_lama = $row_lama['id'];
                    $poin_lama = (int)$row_lama['poin'];
                    $jp_id_lama = $row_lama['jenis_pelanggaran_id'];
                    $tgl_lama = $row_lama['tanggal'];
                    
                    // a. Log
                    mysqli_stmt_bind_param($stmt_log, "iiisi", $santriIdInt, $jp_id_lama, $poin_lama, $tgl_lama, $dicatatOleh);
                    if (!mysqli_stmt_execute($stmt_log)) throw new Exception("Gagal backup log bahasa.");
                    
                    // b. Reduce point
                    if ($poin_lama > 0) {
                        mysqli_stmt_bind_param($stmt_kurangi_poin, "ii", $poin_lama, $santriIdInt);
                        if (!mysqli_stmt_execute($stmt_kurangi_poin)) throw new Exception("Gagal mengurangi poin lama.");
                    }
                    
                    // c. Delete
                    mysqli_stmt_bind_param($stmt_hapus_lama, "i", $id_lama);
                    if (!mysqli_stmt_execute($stmt_hapus_lama)) throw new Exception("Gagal menghapus data lama.");
                }
                
                // 2. Insert new violation (if not clear)
                if (!$isClear) {
                    mysqli_stmt_bind_param($stmt_insert, "iisi", $santriIdInt, $jenisPelanggaranId, $tanggal, $dicatatOleh);
                    if (!mysqli_stmt_execute($stmt_insert)) throw new Exception("Gagal insert pelanggaran baru.");
                    
                    if ($poinBaru > 0) {
                        mysqli_stmt_bind_param($stmt_tambah_poin, "ii", $poinBaru, $santriIdInt);
                        if (!mysqli_stmt_execute($stmt_tambah_poin)) throw new Exception("Gagal menambah poin baru.");
                    }
                }
            }
            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        } finally {
            if ($stmt_cari_lama) mysqli_stmt_close($stmt_cari_lama);
            if ($stmt_log) mysqli_stmt_close($stmt_log);
            if ($stmt_kurangi_poin) mysqli_stmt_close($stmt_kurangi_poin);
            if ($stmt_hapus_lama) mysqli_stmt_close($stmt_hapus_lama);
            if ($stmt_insert) mysqli_stmt_close($stmt_insert);
            if ($stmt_tambah_poin) mysqli_stmt_close($stmt_tambah_poin);
        }
    }

    /**
     * Delete a single violation and refund the points.
     */
    public static function deleteById(int $id): bool
    {
        $conn = Database::getInstance()->getConnection();
        
        $stmt_info = mysqli_prepare($conn, "SELECT p.santri_id, jp.poin 
                                            FROM pelanggaran p 
                                            JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id 
                                            WHERE p.id = ?");
        mysqli_stmt_bind_param($stmt_info, "i", $id);
        mysqli_stmt_execute($stmt_info);
        $result_info = mysqli_stmt_get_result($stmt_info);
        $data = mysqli_fetch_assoc($result_info);
        mysqli_stmt_close($stmt_info);
        
        if (!$data) {
            return false;
        }
        
        $santriId = (int)$data['santri_id'];
        $poin = (int)$data['poin'];
        
        $stmt_delete = mysqli_prepare($conn, "DELETE FROM pelanggaran WHERE id = ?");
        $stmt_kurangi_poin = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        
        mysqli_begin_transaction($conn);
        try {
            if ($poin > 0) {
                mysqli_stmt_bind_param($stmt_kurangi_poin, "ii", $poin, $santriId);
                if (!mysqli_stmt_execute($stmt_kurangi_poin)) throw new Exception("Gagal refund poin.");
            }
            
            mysqli_stmt_bind_param($stmt_delete, "i", $id);
            if (!mysqli_stmt_execute($stmt_delete)) throw new Exception("Gagal hapus pelanggaran.");
            
            mysqli_commit($conn);
            $success = true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $success = false;
        }
        
        mysqli_stmt_close($stmt_delete);
        mysqli_stmt_close($stmt_kurangi_poin);
        return $success;
    }
}
