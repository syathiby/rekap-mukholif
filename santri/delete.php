<?php
// Protokol Khusus Ruang Mesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_delete');

// 1. Validasi ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Memulai mode transaksi
    mysqli_begin_transaction($conn);

    try {
        // 2. Hapus dulu data "anak" di tabel pelanggaran (Pake Prepared Statement!)
        $stmt1 = mysqli_prepare($conn, "DELETE FROM pelanggaran WHERE santri_id = ?");
        mysqli_stmt_bind_param($stmt1, "i", $id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // 3. Baru hapus data "induk" di tabel santri (Pake Prepared Statement!)
        $stmt2 = mysqli_prepare($conn, "DELETE FROM santri WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);

        // Cek apakah ada baris yang benar-benar terhapus
        if (mysqli_stmt_affected_rows($stmt2) > 0) {
            // Jika berhasil, commit transaksi (simpan permanen semua perubahan)
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Data santri dan semua riwayat pelanggarannya berhasil dihapus.";
        } else {
            // Jika santri dengan ID tsb tidak ditemukan
            throw new Exception("Santri dengan ID $id tidak ditemukan.");
        }
        mysqli_stmt_close($stmt2);

    } catch (Exception $e) {
        // Jika ada error di salah satu langkah, rollback (batalkan semua perubahan)
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Gagal menghapus data: " . $e->getMessage();
    }

} else {
    $_SESSION['error_message'] = "ID Santri tidak valid.";
}

// 4. Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>