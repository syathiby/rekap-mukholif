<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class UserModel extends Model {
    protected string $table = 'users';

    public function findByUsername(string $username): array|false {
        $sql = "SELECT * FROM {$this->table} WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    }

    public function getPermissions(int $userId): array {
        $sql = "SELECT p.nama_izin 
                FROM permissions p
                JOIN user_permissions up ON p.id = up.permission_id
                WHERE up.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
