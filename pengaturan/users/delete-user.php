<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Cuma yang punya izin boleh hapus
guard('user_manage');

// Pastikan ada ID yang dikirim
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_delete = (int)$_GET['id'];

    // PENTING: Jangan biarkan user menghapus dirinya sendiri!
    if (isset($_SESSION['user_id']) && $user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "❌ Anda tidak dapat menghapus akun Anda sendiri.";
        header("Location: index.php");
        exit;
    }

    // ====================================================================
    // ===== BAGIAN BARU: CEK ROLE SEBELUM HAPUS =====
    // ====================================================================
    // Ambil dulu data role dari user yang mau dihapus
    $stmt_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id_to_delete);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $user_data = $result_check->fetch_assoc();
        // Kalau rolenya 'admin', batalkan proses dan kasih notif
        if (strtolower($user_data['role']) === 'admin') {
            $_SESSION['error_message'] = "❌ Wih, jago! Tapi sayangnya, Role Admin tidak dapat dihapus.";
            $stmt_check->close();
            $conn->close();
            header("Location: index.php");
            exit;
        }
    }
    $stmt_check->close();
    // ====================================================================


    // Siapkan query delete pake prepared statement (biar aman)
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_delete);

    if ($stmt->execute()) {
        // Kalo berhasil, kasih notif sukses
        $_SESSION['success_message'] = "✅ User berhasil dihapus.";
    } else {
        // Kalo gagal, kasih notif error
        $_SESSION['error_message'] = "❌ Gagal menghapus user.";
    }
    $stmt->close();
} else {
    // Kalo akses file ini tanpa ID, kasih notif error juga
    $_SESSION['error_message'] = "❌ Aksi tidak valid.";
}

$conn->close();

// Apapun hasilnya, balikin ke halaman daftar user
header("Location: index.php");
exit;
?>