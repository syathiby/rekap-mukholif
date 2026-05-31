<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

guard('user_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
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

// --- LOGIKA BARU: VALIDASI ROLE DAN USERNAME ADMIN DI SISI SERVER ---
// 1. Blokir username 'admin' untuk user baru atau perubahan username menjadi 'admin' (kecuali jika user tersebut aslinya sudah admin)
$is_trying_username_admin = (strtolower($username) === 'admin');
if ($is_trying_username_admin) {
    $allow_username_admin = false;
    if ($is_edit_mode) {
        $user_id_check = (int)$_POST['user_id'];
        $stmt_admin_check = $conn->prepare("SELECT role, username FROM users WHERE id = ?");
        $stmt_admin_check->bind_param("i", $user_id_check);
        $stmt_admin_check->execute();
        $result_admin = $stmt_admin_check->get_result();
        if ($result_admin->num_rows === 1) {
            $user_asli_data = $result_admin->fetch_assoc();
            if (strtolower($user_asli_data['role']) === 'admin' && strtolower($user_asli_data['username']) === 'admin') {
                $allow_username_admin = true;
            }
        }
        $stmt_admin_check->close();
    }
    
    if (!$allow_username_admin) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }
}

// 2. Proteksi Perubahan Role dan Edit User Admin
if ($is_edit_mode) {
    $user_id_check = (int)$_POST['user_id'];
    $stmt_role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role_check->bind_param("i", $user_id_check);
    $stmt_role_check->execute();
    $result_role = $stmt_role_check->get_result();
    
    if ($result_role->num_rows === 1) {
        $user_asli = $result_role->fetch_assoc();
        $role_asli = strtolower($user_asli['role']);
        
        // Skenario A: User target aslinya adalah admin
        if ($role_asli === 'admin') {
            $logged_in_user_id = $_SESSION['user_id'] ?? null;
            // Hanya admin bersangkutan yang boleh mengedit dirinya sendiri
            if ($user_id_check !== (int)$logged_in_user_id) {
                $stmt_role_check->close();
                $conn->close();
                http_response_code(403);
                require __DIR__ . '/../../bootstrap/access_denied.php';
                exit;
            }
            
            // Mencegah admin mendegradasi rolenya sendiri menjadi bukan admin
            if ($role !== 'admin') {
                $stmt_role_check->close();
                http_response_code(403);
                require __DIR__ . '/../../bootstrap/access_denied.php';
                exit;
            }
        } elseif ($role_asli === 'pengelola') {
            // Skenario B: User target aslinya adalah pengelola
            if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
                $stmt_role_check->close();
                $conn->close();
                http_response_code(403);
                require __DIR__ . '/../../bootstrap/access_denied.php';
                exit;
            }
        }
    }
    $stmt_role_check->close();
}

// 3. Mencegah pembuatan role admin/pengelola baru atau promosi ilegal
if ($role === 'admin' || $role === 'pengelola') {
    if ($role === 'pengelola' && (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')) {
        // Non-admin mencoba memberikan role pengelola
        $conn->close();
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    if ($role === 'admin') {
        if (!$is_edit_mode) {
            // Mode tambah: Langsung blokir pembuatan admin baru
            $conn->close();
            http_response_code(403);
            require __DIR__ . '/../../bootstrap/access_denied.php';
            exit;
        } else {
            // Mode edit: Cek apakah role asli user di database memang admin.
            // Jika aslinya bukan admin, tolak keras (mencegah promosi ilegal ke admin).
            $user_id_check = (int)$_POST['user_id'];
            $stmt_promo_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_promo_check->bind_param("i", $user_id_check);
            $stmt_promo_check->execute();
            $result_promo = $stmt_promo_check->get_result();
            if ($result_promo->num_rows === 1) {
                $user_promo = $result_promo->fetch_assoc();
                if (strtolower($user_promo['role']) !== 'admin') {
                    $stmt_promo_check->close();
                    $conn->close();
                    http_response_code(403);
                    require __DIR__ . '/../../bootstrap/access_denied.php';
                    exit;
                }
            }
            $stmt_promo_check->close();
        }
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
        write_activity_log('UPDATE', 'users', "Mengubah profil/informasi user '" . htmlspecialchars($username) . "' (Role: " . htmlspecialchars($role) . ")", [
            'id' => $user_id,
            'nama_lengkap' => $nama_lengkap,
            'username' => $username,
            'role' => $role,
            'password_changed' => !empty($password)
        ]);

        // --- LOGIKA BARU: Update izin jika role berubah ---
        if (isset($role_asli) && $role !== $role_asli && $role !== 'admin') {
            $conn->query("DELETE FROM user_permissions WHERE user_id = $user_id");
            $stmt_perms = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id) SELECT ?, permission_id FROM role_permissions WHERE role = ?");
            $stmt_perms->bind_param("is", $user_id, $role);
            $stmt_perms->execute();
            $stmt_perms->close();
        }

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
        $new_user_id = $conn->insert_id;
        write_activity_log('CREATE', 'users', "Membuat user/staf baru: '" . htmlspecialchars($username) . "' (Role: " . htmlspecialchars($role) . ")", [
            'id' => $new_user_id,
            'nama_lengkap' => $nama_lengkap,
            'username' => $username,
            'role' => $role
        ]);

        // --- LOGIKA BARU: Insert izin default ---
        if ($role !== 'admin') {
            $stmt_perms = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id) SELECT ?, permission_id FROM role_permissions WHERE role = ?");
            $stmt_perms->bind_param("is", $new_user_id, $role);
            $stmt_perms->execute();
            $stmt_perms->close();
        }

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