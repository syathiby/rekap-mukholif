<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

// Cuma yang punya izin boleh hapus
guard('user_manage');

// Pastikan ada ID yang dikirim
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_delete = (int)$_GET['id'];

    // PENTING: Jangan biarkan user menghapus dirinya sendiri!
    if (isset($_SESSION['user_id']) && $user_id_to_delete == $_SESSION['user_id']) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // ====================================================================
    // ===== BAGIAN CEK ROLE SEBELUM HAPUS (FIXED) =====
    // ====================================================================
    // Ambil dulu data user yang mau dihapus
    $stmt_check = $conn->prepare("SELECT username, nama_lengkap, role FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id_to_delete);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $user_to_delete_data = $result_check->fetch_assoc(); 
        $username_to_delete = $user_to_delete_data['username'];
        $nama_to_delete = $user_to_delete_data['nama_lengkap'];
        $role_to_delete = $user_to_delete_data['role'];

        if (strtolower($role_to_delete) === 'admin') {
            // Kalau rolenya 'admin', langsung tendang ke halaman akses ditolak
            $stmt_check->close();
            $conn->close();
            http_response_code(403);
            require __DIR__ . '/../../bootstrap/access_denied.php';
            exit;
        } elseif (strtolower($role_to_delete) === 'pengelola') {
            // Kalau rolenya 'pengelola', hanya 'admin' yang boleh hapus
            if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
                $stmt_check->close();
                $conn->close();
                http_response_code(403);
                require __DIR__ . '/../../bootstrap/access_denied.php';
                exit;
            }
        }
    } else {
        // User tidak ditemukan di database
        $stmt_check->close();
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }
    $stmt_check->close();
    // ====================================================================


    // Siapkan query delete pake prepared statement (biar aman)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_delete);

    if ($stmt->execute()) {
        write_activity_log('DELETE', 'users', "Menghapus user/staf: '" . htmlspecialchars($username_to_delete) . "' (Nama: " . htmlspecialchars($nama_to_delete) . ")", [
            'id' => $user_id_to_delete,
            'username' => $username_to_delete,
            'nama_lengkap' => $nama_to_delete,
            'role' => $role_to_delete
        ]);
        // Kalo berhasil, kasih notif sukses
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => "User berhasil dihapus."];
    } else {
        // Kalo gagal, kasih notif error
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Gagal menghapus user."];
    }
    $stmt->close();
} else {
    // Kalo akses file ini tanpa ID, kasih notif error juga
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

$conn->close();

// Apapun hasilnya, balikin ke halaman daftar user
header("Location: index.php");
exit;
?>