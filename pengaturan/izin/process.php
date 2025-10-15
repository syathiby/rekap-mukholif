<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../init.php';
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
    $loggedInUserId = $_SESSION['user_id'] ?? null; 
    
    if ($userId === $loggedInUserId) {
        // Jika user mencoba mengedit izinnya sendiri, langsung tendang!
        $conn->close(); // Tutup koneksi sebelum redirect
        header("Location: " . BASE_URL . "/access_denied.php");
        exit;
    }
    // --- AKHIR DARI LOGIKA BARU ---

    // =================================================================
    // ✅ PERUBAHAN DIMULAI DI SINI
    // =================================================================
    // Ambil nama user dulu buat notifikasi yang lebih cakep
    $userName = ''; // Siapin variabel kosong
    $stmt_get_name = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt_get_name->bind_param("i", $userId);
    $stmt_get_name->execute();
    $result_name = $stmt_get_name->get_result();
    if ($user_row = $result_name->fetch_assoc()) {
        $userName = $user_row['username']; // Dapetin namanya
    }
    $stmt_get_name->close();
    // =================================================================
    // ✅ AKHIR DARI PERUBAHAN
    // =================================================================

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
        // ✅ UBAH NOTIFIKASI: Sebutin nama user-nya
        $_SESSION['success_message'] = "✅ Tiket untuk user '" . htmlspecialchars($userName) . "' berhasil diperbarui!";

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