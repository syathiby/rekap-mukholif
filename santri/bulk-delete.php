<?php
// Protokol Khusus Ruang Mesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_delete'); 

// 1. Validasi Input Awal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ids']) && is_array($_POST['ids'])) {
    
    // Sanitasi semua ID untuk memastikan hanya angka yang diproses
    $ids = array_map('intval', array_filter($_POST['ids'], 'is_numeric'));

    if (!empty($ids)) {
        // Memulai mode transaksi
        mysqli_begin_transaction($conn);

        try {
            // 2. Siapkan statement HANYA SEKALI di luar loop untuk efisiensi
            $stmt_pelanggaran = mysqli_prepare($conn, "DELETE FROM pelanggaran WHERE santri_id = ?");
            $stmt_santri = mysqli_prepare($conn, "DELETE FROM santri WHERE id = ?");

            $deleted_count = 0;
            // 3. Loop untuk setiap ID yang akan dihapus
            foreach ($ids as $id) {
                // Hapus dulu data "anak" (pelanggaran)
                mysqli_stmt_bind_param($stmt_pelanggaran, "i", $id);
                mysqli_stmt_execute($stmt_pelanggaran);

                // Baru hapus data "induk" (santri)
                mysqli_stmt_bind_param($stmt_santri, "i", $id);
                mysqli_stmt_execute($stmt_santri);

                // Hitung jika santri benar-benar terhapus
                if (mysqli_stmt_affected_rows($stmt_santri) > 0) {
                    $deleted_count++;
                }
            }

            // Tutup statement setelah loop selesai
            mysqli_stmt_close($stmt_pelanggaran);
            mysqli_stmt_close($stmt_santri);

            // 4. Jika semua proses berhasil, commit transaksi
            mysqli_commit($conn);
            
            if ($deleted_count > 0) {
                $_SESSION['success_message'] = "Berhasil menghapus $deleted_count santri beserta riwayat pelanggarannya.";
            } else {
                $_SESSION['error_message'] = "Tidak ada santri yang dihapus (mungkin ID tidak ditemukan).";
            }

        } catch (Exception $e) {
            // 5. Jika ada satu saja error, batalkan semua perubahan
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Gagal menghapus data. Operasi dibatalkan. Error: " . $e->getMessage();
        }

    } else {
        $_SESSION['error_message'] = "Tidak ada ID santri yang valid untuk dihapus.";
    }
} else {
    $_SESSION['error_message'] = "Tidak ada santri yang dipilih untuk dihapus.";
}

// Redirect setelah proses selesai
header("Location: index.php");
exit;
?>