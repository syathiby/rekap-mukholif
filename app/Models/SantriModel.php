<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SantriModel extends Model {
    protected string $table = 'santri';

    public function getFiltered(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['nama'])) {
            $where[] = "nama LIKE :nama";
            $params[':nama'] = "%" . $filters['nama'] . "%";
        }
        if (!empty($filters['kelas'])) {
            $where[] = "kelas LIKE :kelas";
            $params[':kelas'] = "%" . $filters['kelas'] . "%";
        }
        if (!empty($filters['kamar'])) {
            $where[] = "kamar LIKE :kamar";
            $params[':kamar'] = "%" . $filters['kamar'] . "%";
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY CAST(kamar AS UNSIGNED) ASC, nama ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(array $filters = []): int {
        $where = [];
        $params = [];

        if (!empty($filters['nama'])) {
            $where[] = "nama LIKE :nama";
            $params[':nama'] = "%" . $filters['nama'] . "%";
        }
        if (!empty($filters['kelas'])) {
            $where[] = "kelas LIKE :kelas";
            $params[':kelas'] = "%" . $filters['kelas'] . "%";
        }
        if (!empty($filters['kamar'])) {
            $where[] = "kamar LIKE :kamar";
            $params[':kamar'] = "%" . $filters['kamar'] . "%";
        }

        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function insert(array $data): int|false {
        $stmt = $this->db->prepare('INSERT INTO ' . $this->table . ' (nama, kelas, kamar) VALUES (:nama, :kelas, :kamar)');
        if ($stmt->execute([
            ':nama' => $data['nama'],
            ':kelas' => $data['kelas'],
            ':kamar' => $data['kamar']
        ])) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare('UPDATE ' . $this->table . ' SET nama = :nama, kelas = :kelas, kamar = :kamar WHERE id = :id');
        return $stmt->execute([
            ':nama' => $data['nama'],
            ':kelas' => $data['kelas'],
            ':kamar' => $data['kamar'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->table . ' WHERE id = ?');
        return $stmt->execute([$id]);
    }
}


