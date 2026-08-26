<?php
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
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
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
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
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
        }

        $conn->commit();
        touch_permissions_version();
        
        // Log aktivitas
        write_activity_log('UPDATE', 'role_permissions', "Memperbarui izin default untuk role: " . ucfirst($role), [
            'role' => $role,
            'total_permissions' => count($permissions)
        ]);
        
        $roleNameDisplay = ucfirst($role);
        $resName = $conn->query("SELECT role_name FROM roles WHERE id = '" . $conn->real_escape_string($role) . "' LIMIT 1");
        if ($resName && $rowName = $resName->fetch_assoc()) {
            if (!empty($rowName['role_name'])) {
                $roleNameDisplay = $rowName['role_name'];
            }
        }

        $_SESSION['success_message'] = "Izin default role " . $roleNameDisplay . " berhasil diperbarui • " . count($permissions) . " izin aktif";
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
