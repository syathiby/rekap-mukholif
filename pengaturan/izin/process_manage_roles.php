<?php
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

// Validasi CSRF Token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/csrf_expired.php';
    exit;
}

$action = $_POST['action'] ?? '';
$protectedRoles = ['admin', 'pelihat', 'pengelola'];
$reservedSlugs = ['admin', 'pelihat', 'pengelola', 'root', 'system', 'superuser', 'user', 'guest'];

// Fungsi Helper untuk membuat ID ramah sistem (Slugifier)
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '_', $slug); // Ganti spasi/karakter spesial dengan underscore
    $slug = preg_replace('/_+/', '_', $slug); // Hapus underscore ganda
    return trim($slug, '_');
}

if ($action === 'add') {
    $role_name = trim($_POST['role_name'] ?? '');
    
    if (empty($role_name)) {
        $_SESSION['error_message'] = "Nama role tidak boleh kosong.";
        header("Location: manage_roles.php");
        exit();
    }

    $id = generateSlug($role_name);
    
    if (empty($id) || strlen($id) < 2) {
        $_SESSION['error_message'] = "Nama role tidak valid. Mohon gunakan karakter alfanumerik yang jelas.";
        header("Location: manage_roles.php");
        exit();
    }

    if (in_array($id, $reservedSlugs)) {
        $_SESSION['error_message'] = "ID role '$id' merupakan kata kunci sistem terproteksi. Silakan gunakan nama role lain.";
        header("Location: manage_roles.php");
        exit();
    }
    
    // Cek apakah ID sudah ada
    $stmt = $conn->prepare("SELECT id FROM roles WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Role dengan ID/nama mirip ('$id') sudah ada di sistem. Silakan gunakan nama lain.";
        $stmt->close();
        header("Location: manage_roles.php");
        exit();
    }
    $stmt->close();

    // Insert ke database
    $stmt = $conn->prepare("INSERT INTO roles (id, role_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $id, $role_name);
    
    if ($stmt->execute()) {
        write_activity_log('CREATE', 'roles', "Menambahkan role baru: '$role_name' (ID: $id)", ['id' => $id, 'role_name' => $role_name]);
        $_SESSION['success_message'] = "Role <strong>" . htmlspecialchars($role_name) . "</strong> berhasil ditambahkan.";
    } else {
        $_SESSION['error_message'] = "Gagal menambahkan role: " . $conn->error;
    }
    $stmt->close();

} elseif ($action === 'edit') {
    $id = trim($_POST['id'] ?? '');
    $role_name = trim($_POST['role_name'] ?? '');

    if (empty($id) || empty($role_name)) {
        $_SESSION['error_message'] = "Data tidak lengkap.";
        header("Location: manage_roles.php");
        exit();
    }

    // Role terlindungi kini boleh diedit NAMAnya, tetapi tidak boleh dihapus.
    $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
    $stmt->bind_param("ss", $role_name, $id);
    
    if ($stmt->execute()) {
        write_activity_log('UPDATE', 'roles', "Memperbarui nama role: '$role_name' (ID: $id)", ['id' => $id, 'role_name' => $role_name]);
        $_SESSION['success_message'] = "Nama role berhasil diperbarui menjadi <strong>" . htmlspecialchars($role_name) . "</strong>.";
    } else {
        $_SESSION['error_message'] = "Gagal memperbarui role: " . $conn->error;
    }
    $stmt->close();

} elseif ($action === 'delete') {
    $id = trim($_POST['id'] ?? '');

    if (empty($id)) {
        $_SESSION['error_message'] = "ID tidak valid.";
        header("Location: manage_roles.php");
        exit();
    }

    // Perlindungan Ganda: Role bawaan sistem tidak boleh dihapus
    if (in_array($id, $protectedRoles)) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // 1. Cek apakah masih ada pengguna yang menggunakan role ini
    $stmtUser = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE role = ?");
    $stmtUser->bind_param("s", $id);
    $stmtUser->execute();
    $userCount = (int)($stmtUser->get_result()->fetch_assoc()['user_count'] ?? 0);
    $stmtUser->close();

    if ($userCount > 0) {
        $_SESSION['error_message'] = "Role <strong>" . htmlspecialchars($id) . "</strong> tidak dapat dihapus karena masih digunakan oleh <strong>$userCount pengguna aktif</strong>. Ubah role pengguna terkait terlebih dahulu.";
        header("Location: manage_roles.php");
        exit();
    }

    // 2. Ambil nama role untuk audit log
    $stmtName = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
    $stmtName->bind_param("s", $id);
    $stmtName->execute();
    $roleData = $stmtName->get_result()->fetch_assoc();
    $roleName = $roleData['role_name'] ?? $id;
    $stmtName->close();

    // 3. Hapus relasi role_permissions dan roles dalam satu transaksi aman
    $conn->begin_transaction();
    try {
        // Hapus izin default role ini terlebih dahulu
        $stmtPerm = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
        $stmtPerm->bind_param("s", $id);
        $stmtPerm->execute();
        $stmtPerm->close();

        // Hapus master role
        $stmtRole = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $stmtRole->bind_param("s", $id);
        $stmtRole->execute();
        $stmtRole->close();

        $conn->commit();

        write_activity_log('DELETE', 'roles', "Menghapus role: '$roleName' (ID: $id)", ['id' => $id, 'role_name' => $roleName]);
        $_SESSION['success_message'] = "Role <strong>" . htmlspecialchars($roleName) . "</strong> berhasil dihapus sepenuhnya beserta relasi izin defaultnya.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal menghapus role: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Aksi tidak dikenali.";
}

header("Location: manage_roles.php");
exit();
