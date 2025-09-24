<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

guard('user_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php");
    exit;
}

$is_edit_mode = isset($_POST['user_id']) && !empty($_POST['user_id']);
$redirect_url = $is_edit_mode ? "form-user.php?id=" . $_POST['user_id'] : "form-user.php";

$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$username     = trim($_POST['username'] ?? '');
$password     = $_POST['password'] ?? '';
$role         = trim(strtolower($_POST['role'] ?? ''));

if (empty($nama_lengkap) || empty($username) || empty($role)) {
    $_SESSION['error_message'] = "❌ Nama, username, dan role wajib diisi.";
    header("Location: $redirect_url");
    exit;
}
if (!$is_edit_mode && empty($password)) {
    $_SESSION['error_message'] = "❌ Password wajib diisi untuk user baru.";
    header("Location: $redirect_url");
    exit;
}

// --- LOGIKA BARU: VALIDASI ROLE ADMIN DI SISI SERVER ---
if ($role === 'admin') {
    if ($is_edit_mode) {
        // Mode edit: Cek role asli user di database. Boleh save kalau role aslinya emang udah admin.
        $user_id_check = (int)$_POST['user_id'];
        $stmt_role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_role_check->bind_param("i", $user_id_check);
        $stmt_role_check->execute();
        $result_role = $stmt_role_check->get_result();
        
        if ($result_role->num_rows === 1) {
            $user_asli = $result_role->fetch_assoc();
            // Jika role aslinya BUKAN admin, maka tolak perubahan jadi admin
            if (strtolower($user_asli['role']) !== 'admin') {
                $_SESSION['error_message'] = "❌ Aksi Ditolak! Anda tidak bisa mengubah role user lain menjadi 'Admin'.";
                header("Location: $redirect_url");
                exit;
            }
        }
        $stmt_role_check->close();
    } else {
        // Mode tambah: Langsung tolak jika mencoba membuat user admin baru
        $_SESSION['error_message'] = "❌ Aksi Ditolak! Anda tidak bisa menambahkan user baru dengan role 'Admin'.";
        header("Location: $redirect_url");
        exit;
    }
}

// Cek duplikasi username
if ($is_edit_mode) {
    $user_id = (int)$_POST['user_id'];
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt_check->bind_param("si", $username, $user_id);
} else {
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
}
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $_SESSION['error_message'] = "❌ Username '".htmlspecialchars($username)."' sudah digunakan.";
    $stmt_check->close();
    header("Location: $redirect_url");
    exit;
}
$stmt_check->close();

// Proses update atau insert data
if ($is_edit_mode) {
    $user_id = (int)$_POST['user_id'];

    if (!empty($password)) {
        $hashedPassword = hash('sha256', $password);
        $stmt_update = $conn->prepare("UPDATE users SET nama_lengkap = ?, username = ?, role = ?, password = ? WHERE id = ?");
        $stmt_update->bind_param("ssssi", $nama_lengkap, $username, $role, $hashedPassword, $user_id);
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET nama_lengkap = ?, username = ?, role = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $nama_lengkap, $username, $role, $user_id);
    }

    if ($stmt_update->execute()) {
        $_SESSION['success_message'] = "✅ Data user '".htmlspecialchars($username)."' berhasil diperbarui!";
    } else {
        $_SESSION['error_message'] = "❌ Gagal memperbarui data user.";
    }
    $stmt_update->close();

} else {
    $hashedPassword = hash('sha256', $password);
    
    $stmt_insert = $conn->prepare("INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("ssss", $nama_lengkap, $username, $hashedPassword, $role); 

    if ($stmt_insert->execute()){
        $_SESSION['success_message'] = "✅ User '".htmlspecialchars($username)."' berhasil dibuat!";
        $redirect_url = "form-user.php"; // Redirect ke form kosong setelah berhasil
    } else {
        $_SESSION['error_message'] = "❌ Gagal menyimpan data ke database.";
    }
    $stmt_insert->close();
}

$conn->close();
header("Location: $redirect_url");
exit;
?>