<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

csrf_validate();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'pelihat') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$username_baru = trim($_POST['username'] ?? '');
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($username_baru) || empty($old_password)) {
    $_SESSION['error_message'] = "Username dan Password Lama wajib diisi.";
    header("Location: profil.php");
    exit;
}

// 1. Cek batasan 3 kali per bulan (Hanya jika tidak memiliki izin user_manage)
if (!has_permission('user_manage')) {
    $current_month = date('Y-m');
    $stmt_limit = $conn->prepare("SELECT COUNT(*) as count FROM log_aktifitas WHERE user_id = ? AND fitur = 'profil' AND aksi = 'UPDATE' AND DATE_FORMAT(waktu, '%Y-%m') = ?");
    $stmt_limit->bind_param("is", $user_id, $current_month);
    $stmt_limit->execute();
    $res_limit = $stmt_limit->get_result();
    $limit_data = $res_limit->fetch_assoc();
    $stmt_limit->close();

    if ($limit_data['count'] >= 3) {
        $_SESSION['error_message'] = "Akses ditolak! Anda telah mencapai batas maksimal perubahan profil (3 kali) pada bulan ini.";
        header("Location: profil.php");
        exit;
    }
}

// 2. Ambil data asli user dari DB (terutama password hash)
$stmt_user = $conn->prepare("SELECT username, password FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if ($res_user->num_rows !== 1) {
    $_SESSION['error_message'] = "Data user tidak ditemukan.";
    header("Location: profil.php");
    exit;
}
$user_data = $res_user->fetch_assoc();
$stmt_user->close();

// 3. Validasi Password Lama (Support legacy SHA-256 dan Bcrypt)
$password_valid = false;
$needs_rehash = false;

if (password_verify($old_password, $user_data['password'])) {
    $password_valid = true;
} elseif (strlen($user_data['password']) === 64 && ctype_xdigit($user_data['password'])) {
    // Legacy SHA-256 check
    if (hash('sha256', $old_password) === $user_data['password']) {
        $password_valid = true;
        $needs_rehash = true;
    }
}

if (!$password_valid) {
    $_SESSION['error_message'] = "Konfirmasi gagal: Password lama yang Anda masukkan salah.";
    header("Location: profil.php");
    exit;
}

// 4. Validasi Username (Tidak boleh kembar, tidak boleh 'admin' jika bukan admin asli)
if (strtolower($username_baru) !== strtolower($user_data['username'])) {
    if (strtolower($username_baru) === 'admin') {
        if (strtolower($_SESSION['role']) !== 'admin' || strtolower($user_data['username']) !== 'admin') {
            http_response_code(403);
            require __DIR__ . '/../../bootstrap/access_denied.php';
            exit;
        }
    }

    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt_check->bind_param("si", $username_baru, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        $_SESSION['error_message'] = "Username '" . htmlspecialchars($username_baru) . "' sudah digunakan oleh pengguna lain.";
        header("Location: profil.php");
        exit;
    }
    $stmt_check->close();
}

// 5. Lakukan Pembaruan
$final_password = $user_data['password'];
$pwd_changed = false;
if (!empty($new_password)) {
    $final_password = password_hash($new_password, PASSWORD_DEFAULT);
    $pwd_changed = true;
} elseif ($needs_rehash) {
    // Transisi halus ke bcrypt untuk pengguna yang hanya mengganti username tanpa ganti password
    $final_password = password_hash($old_password, PASSWORD_DEFAULT);
}

$stmt_update = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
$stmt_update->bind_param("ssi", $username_baru, $final_password, $user_id);

if ($stmt_update->execute()) {
    // Update session
    $_SESSION['username'] = $username_baru;
    
    // Log Aktivitas
    $perubahan = [];
    if (strtolower($username_baru) !== strtolower($user_data['username'])) {
        $perubahan[] = "Username (" . $user_data['username'] . " -> $username_baru)";
    }
    if ($pwd_changed) {
        $perubahan[] = "Password";
    }
    
    $desc = "Mengubah profil mandiri: " . (!empty($perubahan) ? implode(', ', $perubahan) : "Tidak ada perubahan data");

    // Jangan log jika tidak ada yang berubah
    if (!empty($perubahan)) {
        write_activity_log('UPDATE', 'profil', $desc, [
            'username_lama' => $user_data['username'],
            'username_baru' => $username_baru,
            'password_diganti' => $pwd_changed
        ]);
        $_SESSION['success_message'] = "Profil Anda berhasil diperbarui!";
    } else {
        $_SESSION['success_message'] = "Data disimpan (namun tidak ada perubahan yang terdeteksi).";
    }

} else {
    $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan pembaruan sistem.";
}

$stmt_update->close();
header("Location: profil.php");
exit;
?>
