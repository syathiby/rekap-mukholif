<?php
require_once __DIR__ . '/../../bootstrap/init.php';
guard('jenis_reward_delete');

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    
    // Cek dulu dipake gak datanya (Opsional, tapi bagus buat validasi)
    $cek = mysqli_query($conn, "SELECT id FROM daftar_reward WHERE jenis_reward_id=$id");
    
    if(mysqli_num_rows($cek) > 0) {
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Gagal hapus! Data ini sedang dipakai di riwayat reward santri.'];
    } else {
        // Ambil nama reward sebelum dihapus untuk dicatat di log
        $r_nama = '';
        $stmt_get = $conn->prepare("SELECT nama_reward, poin_reward FROM jenis_reward WHERE id = ? LIMIT 1");
        $stmt_get->bind_param('i', $id);
        $stmt_get->execute();
        $r_row = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();
        if ($r_row) $r_nama = $r_row['nama_reward'] . ' (Poin: ' . $r_row['poin_reward'] . ')';

        if(mysqli_query($conn, "DELETE FROM jenis_reward WHERE id=$id")) {
            write_activity_log('DELETE', 'jenis_reward', "Menghapus jenis reward: '$r_nama'", [
                'sebelum' => $r_row ?? []
            ]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data berhasil dihapus.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: '.mysqli_error($conn)];
        }
    }
}

header("Location: index.php");
exit;
?>