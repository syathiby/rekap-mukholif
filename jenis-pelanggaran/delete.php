<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('jenis_pelanggaran_delete');

/**
 * Fungsi ini akan menghitung dan mengurangi poin_aktif santri
 * SEBELUM jenis_pelanggaran dihapus.
 * @param mysqli $conn Koneksi database
 * @param int $jenis_pelanggaran_id ID dari jenis_pelanggaran yang akan dihapus
 */
function kurangiPoinSebelumHapus($conn, $jenis_pelanggaran_id) {
    // Langkah 1: Ambil dulu berapa poin dari jenis pelanggaran ini
    $stmt_poin = mysqli_prepare($conn, "SELECT poin FROM jenis_pelanggaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt_poin, 'i', $jenis_pelanggaran_id);
    mysqli_stmt_execute($stmt_poin);
    $result_poin = mysqli_stmt_get_result($stmt_poin);
    $data_jp = mysqli_fetch_assoc($result_poin);
    mysqli_stmt_close($stmt_poin);

    // Kalau jenis pelanggaran tidak ditemukan atau poinnya 0, tidak ada yang perlu dilakukan
    if (!$data_jp || $data_jp['poin'] == 0) {
        return;
    }
    $poin_per_pelanggaran = $data_jp['poin'];

    // Langkah 2: Cari santri mana saja dan berapa kali mereka melakukan pelanggaran ini
    $query_santri = "SELECT santri_id, COUNT(*) as jumlah FROM pelanggaran WHERE jenis_pelanggaran_id = ? GROUP BY santri_id";
    $stmt_santri = mysqli_prepare($conn, $query_santri);
    mysqli_stmt_bind_param($stmt_santri, 'i', $jenis_pelanggaran_id);
    mysqli_stmt_execute($stmt_santri);
    $result_santri = mysqli_stmt_get_result($stmt_santri);

    // Langkah 3: Loop setiap santri yang terkena dampak dan kurangi poinnya
    while ($row = mysqli_fetch_assoc($result_santri)) {
        $santri_id = $row['santri_id'];
        $jumlah_pelanggaran = $row['jumlah'];
        $total_poin_dikurangi = $jumlah_pelanggaran * $poin_per_pelanggaran;

        // Eksekusi pengurangan poin di tabel santri
        $stmt_update = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, 'ii', $total_poin_dikurangi, $santri_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
    mysqli_stmt_close($stmt_santri);
}

// --- Skenario 1: Hapus Satu Data (dari link) ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // PANGGIL FUNGSI PENGURANGAN POIN SEBELUM DELETE
    kurangiPoinSebelumHapus($conn, $id);
    
    // Lanjutkan proses hapus jenis_pelanggaran
    $stmt = mysqli_prepare($conn, "DELETE FROM jenis_pelanggaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Data jenis pelanggaran berhasil dihapus dan poin santri telah diperbarui.";
    }
    mysqli_stmt_close($stmt);
}

// --- Skenario 2: Hapus Banyak Data (dari checkbox) ---
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids_to_delete = array_map('intval', $_POST['ids']);
    
    if (!empty($ids_to_delete)) {
        // PANGGIL FUNGSI PENGURANGAN POIN UNTUK SETIAP ID SEBELUM DELETE
        foreach ($ids_to_delete as $id) {
            kurangiPoinSebelumHapus($conn, $id);
        }

        // Lanjutkan proses hapus jenis_pelanggaran secara bulk
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $types = str_repeat('i', count($ids_to_delete));
        $query = "DELETE FROM jenis_pelanggaran WHERE id IN ($placeholders)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$ids_to_delete);
        
        if (mysqli_stmt_execute($stmt)) {
            $count = mysqli_stmt_affected_rows($stmt);
            $_SESSION['success_message'] = "$count data jenis pelanggaran berhasil dihapus dan poin santri telah diperbarui.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>