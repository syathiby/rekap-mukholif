<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

// Cuma yang punya izin boleh hapus
guard('user_manage');

// Pastikan ada ID yang dikirim
if (isset($_POST['ids']) && is_array($_POST['ids']) && !empty($_POST['ids'])) {
    $ids_to_delete = $_POST['ids'];
    $success_count = 0;
    $error_count = 0;

    foreach ($ids_to_delete as $id) {
        $user_id_to_delete = (int)$id;

        // PENTING: Jangan biarkan user menghapus dirinya sendiri!
        if (isset($_SESSION['user_id']) && $user_id_to_delete == $_SESSION['user_id']) {
            $error_count++;
            continue;
        }

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

            $can_delete = true;

            // Cek permission seperti di delete-user.php
            if (strtolower($role_to_delete) === 'admin') {
                $can_delete = false;
            } elseif (strtolower($role_to_delete) === 'pengelola') {
                if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
                    $can_delete = false;
                }
            }

            if ($can_delete) {
                // Siapkan query delete pake prepared statement (biar aman)
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id_to_delete);

                if ($stmt->execute()) {
                    write_activity_log('DELETE', 'users', "Menghapus user/staf (Bulk): '" . htmlspecialchars($username_to_delete) . "' (Nama: " . htmlspecialchars($nama_to_delete) . ")", [
                        'id' => $user_id_to_delete,
                        'username' => $username_to_delete,
                        'nama_lengkap' => $nama_to_delete,
                        'role' => $role_to_delete
                    ]);
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            } else {
                $error_count++;
            }
        } else {
            // User tidak ditemukan di database
            $error_count++;
        }
        $stmt_check->close();
    }

    if ($success_count > 0 && $error_count == 0) {
        $_SESSION['success_message'] = "✅ Berhasil menghapus $success_count user terpilih.";
    } elseif ($success_count > 0 && $error_count > 0) {
        $_SESSION['success_message'] = "✅ Berhasil menghapus $success_count user terpilih, namun $error_count data gagal/ditolak (karena akses).";
    } else {
        $_SESSION['error_message'] = "❌ Gagal menghapus user atau Anda tidak memiliki izin untuk menghapus user yang dipilih.";
    }
} else {
    // Kalo akses file ini tanpa ID, kasih notif error
    $_SESSION['error_message'] = "❌ Tidak ada user yang dipilih untuk dihapus.";
}

$conn->close();

// Apapun hasilnya, balikin ke halaman daftar user
header("Location: index.php");
exit;
?>
