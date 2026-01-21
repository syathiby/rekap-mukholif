<?php
require_once '../../init.php';
guard('jenis_reward_delete');

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids_string = implode(',', $ids);

    // Hapus data yang TIDAK dipakai di tabel transaksi (Safety First)
    // Kalau mau paksa hapus semua, ilangin logic pengecekan ini
    $query = "DELETE FROM jenis_reward 
              WHERE id IN ($ids_string) 
              AND id NOT IN (SELECT DISTINCT jenis_reward_id FROM daftar_reward)";
    
    if (mysqli_query($conn, $query)) {
        $deleted = mysqli_affected_rows($conn);
        $total = count($ids);
        
        if ($deleted < $total) {
            $_SESSION['message'] = ['type' => 'warning', 'text' => "Berhasil hapus $deleted data. Sisanya ($total - $deleted) tidak dihapus karena masih dipakai di riwayat."];
        } else {
            $_SESSION['message'] = ['type' => 'success', 'text' => "$deleted data berhasil dihapus semua."];
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal hapus: ' . mysqli_error($conn)];
    }
} else {
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Tidak ada data yang dipilih.'];
}

header("Location: index.php");
exit;
?>