<?php
session_start();
require_once __DIR__ . '/../../bootstrap/init.php';

// Validasi guard
guard('izin_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/csrf_expired.php';
        exit;
    }

    $role = isset($_POST['role']) ? strtolower($_POST['role']) : null;
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    if (!$role) {
        $_SESSION['error_message'] = "Error: Role tidak valid.";
        header("Location: role.php");
        exit;
    }

    if ($role === 'admin') {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    } elseif ($role === 'pengelola') {
        if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
            http_response_code(403);
            require __DIR__ . '/../../bootstrap/access_denied.php';
            exit;
        }
    }

    $availableRoles = [];
    $resRoles = $conn->query("SELECT id FROM roles WHERE id != 'admin'");
    while($r = $resRoles->fetch_assoc()) {
        $availableRoles[] = $r['id'];
    }
    if (!in_array($role, $availableRoles)) {
        $_SESSION['error_message'] = "Error: Role tidak dikenal.";
        header("Location: role.php");
        exit;
    }

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // Hapus izin role yang lama
        $stmtDelete = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
        $stmtDelete->bind_param("s", $role);
        $stmtDelete->execute();
        $stmtDelete->close();

        // Tambah izin yang baru ke role_permissions
        if (!empty($permissions)) {
            $stmtInsert = $conn->prepare("INSERT INTO role_permissions (role, permission_id) VALUES (?, ?)");
            foreach ($permissions as $perm_id) {
                $perm_id = (int)$perm_id;
                $stmtInsert->bind_param("si", $role, $perm_id);
                $stmtInsert->execute();
            }
            $stmtInsert->close();

            // SINKRONISASI KE USER LAMA (SISTEM MENIMPA / MERGE)
            // Tidak menghapus izin yang sudah ada, hanya menambahkan izin baru dari role
            $stmtUserPerm = $conn->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
            $resUsers = $conn->query("SELECT id FROM users WHERE role = '" . $conn->real_escape_string($role) . "'");
            while ($u = $resUsers->fetch_assoc()) {
                $uid = (int)$u['id'];
                foreach ($permissions as $perm_id) {
                    $perm_id = (int)$perm_id;
                    $stmtUserPerm->bind_param("ii", $uid, $perm_id);
                    $stmtUserPerm->execute();
                }
            }
            $stmtUserPerm->close();
        }

        $conn->commit();
        
        // Log aktivitas
        write_activity_log('UPDATE', 'role_permissions', "Memperbarui izin default untuk role: " . ucfirst($role), [
            'role' => $role,
            'total_permissions' => count($permissions)
        ]);
        
        $_SESSION['success_message'] = "Izin default untuk role <strong>" . ucfirst($role) . "</strong> berhasil diperbarui!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan: " . $e->getMessage();
    }

    $conn->close();
    header("Location: role.php?role=" . urlencode($role));
    exit;
} else {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}
?>
