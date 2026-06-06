<?php
// Protokol Khusus Ruang Mesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../bootstrap/init.php';
guard('santri_delete');

// Validasi CSRF: token wajib ada dan valid
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../bootstrap/csrf_expired.php';
    exit;
}

// 1. Validasi ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Ambil nama santri sebelum dihapus
    $stmt_check = mysqli_prepare($conn, "SELECT nama, kelas, kamar FROM santri WHERE id = ?");
    mysqli_stmt_bind_param($stmt_check, "i", $id);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);
    $santri_data = mysqli_fetch_assoc($res_check);
    mysqli_stmt_close($stmt_check);

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
            
            // Catat log hapus santri
            if ($santri_data) {
                write_activity_log('DELETE', 'santri', "Menghapus data santri: '" . htmlspecialchars($santri_data['nama']) . "' (Kelas: " . htmlspecialchars($santri_data['kelas']) . ", Kamar: " . htmlspecialchars($santri_data['kamar']) . ")", [
                    'id' => $id,
                    'nama' => $santri_data['nama'],
                    'kelas' => $santri_data['kelas'],
                    'kamar' => $santri_data['kamar']
                ]);
            }
            
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