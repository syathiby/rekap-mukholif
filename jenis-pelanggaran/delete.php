<?php
// Kasih waktu 5 menit (300 detik) buat jaga-jaga
set_time_limit(300);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('jenis_pelanggaran_delete');

// Fungsi ini akan menghitung dan mengurangi poin_aktif santri secara BULK (sekaligus)
function kurangiPoinBulkSebelumHapus($conn, $ids_to_delete) {
    if (empty($ids_to_delete)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
    $types = str_repeat('i', count($ids_to_delete));
    $query_poin = "
        SELECT 
            p.santri_id, 
            SUM(jp.poin) as total_poin_dikurangi
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.jenis_pelanggaran_id IN ($placeholders)
        GROUP BY p.santri_id
    ";
    $stmt_poin = mysqli_prepare($conn, $query_poin);
    mysqli_stmt_bind_param($stmt_poin, $types, ...$ids_to_delete);
    mysqli_stmt_execute($stmt_poin);
    $result_poin = mysqli_stmt_get_result($stmt_poin);
    if (mysqli_num_rows($result_poin) == 0) {
        mysqli_stmt_close($stmt_poin);
        return;
    }
    while ($row = mysqli_fetch_assoc($result_poin)) {
        $santri_id = $row['santri_id'];
        $total_poin_dikurangi = $row['total_poin_dikurangi'];
        if ($total_poin_dikurangi > 0) {
            $stmt_update = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, 'ii', $total_poin_dikurangi, $santri_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }
    }
    mysqli_stmt_close($stmt_poin);
}


// --- Skenario 1: Hapus Satu Data (dari link) ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $ids_to_process = [$id];
    
    kurangiPoinBulkSebelumHapus($conn, $ids_to_process);
    
    // LANGKAH PENTING: Hapus dulu semua data terkait di tabel `pelanggaran`
    $stmt_child = mysqli_prepare($conn, "DELETE FROM pelanggaran WHERE jenis_pelanggaran_id = ?");
    mysqli_stmt_bind_param($stmt_child, 'i', $id);
    mysqli_stmt_execute($stmt_child);
    mysqli_stmt_close($stmt_child);

    // Lanjutkan proses hapus jenis_pelanggaran
    $stmt = mysqli_prepare($conn, "DELETE FROM jenis_pelanggaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Data jenis pelanggaran dan semua riwayatnya berhasil dihapus.";
    }
    mysqli_stmt_close($stmt);
}

// --- Skenario 2: Hapus Banyak Data (dari checkbox) ---
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids_to_delete = array_filter(array_map('intval', $_POST['ids']), function($id) {
        return $id > 0;
    });
    
    if (!empty($ids_to_delete)) {
        kurangiPoinBulkSebelumHapus($conn, $ids_to_delete);

        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $types = str_repeat('i', count($ids_to_delete));

        // LANGKAH PENTING: Hapus dulu semua data terkait di tabel `pelanggaran` secara bulk
        $query_child = "DELETE FROM pelanggaran WHERE jenis_pelanggaran_id IN ($placeholders)";
        $stmt_child = mysqli_prepare($conn, $query_child);
        mysqli_stmt_bind_param($stmt_child, $types, ...$ids_to_delete);
        mysqli_stmt_execute($stmt_child);
        mysqli_stmt_close($stmt_child);

        // Lanjutkan proses hapus jenis_pelanggaran secara bulk
        $query = "DELETE FROM jenis_pelanggaran WHERE id IN ($placeholders)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$ids_to_delete);
        
        if (mysqli_stmt_execute($stmt)) {
            $count = mysqli_stmt_affected_rows($stmt);
            $_SESSION['success_message'] = "$count data jenis pelanggaran dan semua riwayatnya berhasil dihapus.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>