<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
guard('izin_manage');

// =================================================================
// LOGIKA PROSES DIMULAI DI SINI
// =================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
        $_SESSION['error_message'] = "❌ ID User tidak ditemukan.";
        header("Location: index.php");
        exit;
    }

    $userId = (int)$_POST['user_id'];

    // --- LOGIKA BARU: CEGAH USER MENGEDIT IZINNYA SENDIRI ---
    // Ambil ID user yang sedang login dari session
    $loggedInUserId = $_SESSION['user_id'] ?? null; 
    
    // Bandingkan ID dari form dengan ID dari session
    if ($userId === $loggedInUserId) {
        $_SESSION['error_message'] = "❌ Wih, jago! Tapi sayangnya, Anda tidak bisa mengubah izin untuk diri sendiri.";
        // Kembalikan ke halaman sebelumnya
        header("Location: index.php?user_id=" . $userId);
        exit;
    }
    // --- AKHIR DARI LOGIKA BARU ---

    // Ambil semua ID tiket yg dicentang. Jika tidak ada yg dicentang, jadi array kosong.
    $permissionIds = $_POST['permissions'] ?? [];

    // Gunakan transaksi biar aman!
    $conn->begin_transaction();
    try {
        // 1. Hapus semua tiket lama milik user ini. Biar bersih.
        $stmt_delete = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt_delete->bind_param("i", $userId);
        $stmt_delete->execute();
        $stmt_delete->close();

        // 2. Jika ada tiket baru yang dicentang, masukkan satu per satu.
        if (!empty($permissionIds)) {
            $stmt_insert = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permId) {
                $permIdInt = (int)$permId;
                $stmt_insert->bind_param("ii", $userId, $permIdInt);
                $stmt_insert->execute();
            }
            $stmt_insert->close();
        }

        // Jika semua proses di atas lancar, kunci perubahannya!
        $conn->commit();
        $_SESSION['success_message'] = "✅ Tiket untuk user berhasil diperbarui!";

    } catch (mysqli_sql_exception $exception) {
        // Jika ada satu saja error, batalkan semua perubahan!
        $conn->rollback();
        $_SESSION['error_message'] = "❌ Gagal memperbarui tiket: " . $exception->getMessage();
    }
} else {
    // Jika file ini diakses langsung via URL, tendang
    $_SESSION['error_message'] = "❌ Akses tidak sah.";
}

// Setelah selesai, kembalikan ke halaman loket, sambil bawa ID user biar langsung nampilin user yg sama
$redirect_user_id = $userId ?? ($_POST['user_id'] ?? '');
header("Location: index.php?user_id=" . $redirect_user_id);
exit;
?>