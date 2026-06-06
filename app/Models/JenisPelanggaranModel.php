<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class JenisPelanggaranModel extends Model {
    protected string $table = 'jenis_pelanggaran';

    /**
     * Ambil daftar jenis pelanggaran berdasarkan bagian tertentu
     */
    public function getByBagian(string $bagian): array {
        $stmt = $this->db->prepare("SELECT id, nama_pelanggaran, poin, kategori, bagian FROM {$this->table} WHERE LOWER(bagian) = LOWER(?) ORDER BY nama_pelanggaran ASC");
        $stmt->execute([$bagian]);
        return $stmt->fetchAll();
    }

    /**
     * Cek apakah jenis pelanggaran sedang dipakai di tabel pelanggaran
     */
    public function isUsed(int $id): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pelanggaran WHERE jenis_pelanggaran_id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Ambil daftar bagian unik yang ada di tabel jenis_pelanggaran
     */
    public function getDaftarBagian(): array {
        // Fallback default jika tabel kosong
        $defaultBagian = ['KESANTRIAN', 'BAHASA', 'DINIYYAH', 'TAHFIDZ', 'PENGABDIAN'];
        
        $stmt = $this->db->query("SELECT DISTINCT bagian FROM {$this->table} WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC");
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($result)) {
            return $defaultBagian;
        }
        
        return $result;
    }

    /**
     * Override delete untuk mencegah penghapusan jika data sedang digunakan
     */
    public function delete(int $id): bool {
        if ($this->isUsed($id)) {
            throw new \Exception('Jenis Pelanggaran tidak dapat dihapus karena sudah pernah digunakan oleh santri.');
        }
        return parent::delete($id);
    }
}
