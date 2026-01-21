<?php
require_once '../../init.php';
guard('jenis_reward_delete');

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    
    // Cek dulu dipake gak datanya (Opsional, tapi bagus buat validasi)
    $cek = mysqli_query($conn, "SELECT id FROM daftar_reward WHERE jenis_reward_id=$id");
    
    if(mysqli_num_rows($cek) > 0) {
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Gagal hapus! Data ini sedang dipakai di riwayat reward santri.'];
    } else {
        if(mysqli_query($conn, "DELETE FROM jenis_reward WHERE id=$id")) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data berhasil dihapus.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: '.mysqli_error($conn)];
        }
    }
}

header("Location: index.php");
exit;
?>