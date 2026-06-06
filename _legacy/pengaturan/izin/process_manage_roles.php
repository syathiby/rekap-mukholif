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
    
    // Cek apakah ID sudah ada
    $stmt = $conn->prepare("SELECT id FROM roles WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Role dengan nama mirip ('$id') sudah ada di sistem. Silakan gunakan nama lain.";
        $stmt->close();
        header("Location: manage_roles.php");
        exit();
    }
    $stmt->close();

    // Insert ke database
    $stmt = $conn->prepare("INSERT INTO roles (id, role_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $id, $role_name);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Role baru berhasil ditambahkan.";
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

    // Dihapus: role terlindungi kini boleh diedit NAMAnya, tetapi tidak boleh dihapus.

    $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
    $stmt->bind_param("ss", $role_name, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Nama role berhasil diperbarui.";
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

    // Perlindungan Ganda (meskipun di UI tombol hapusnya tidak ada)
    if (in_array($id, $protectedRoles)) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // Lakukan Delete
    $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->bind_param("s", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Role berhasil dihapus sepenuhnya dari sistem.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus role: " . $conn->error;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Aksi tidak dikenali.";
}

header("Location: manage_roles.php");
exit();
