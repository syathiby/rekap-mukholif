<?php
require_once '../../init.php';

if (isset($_POST['add_reward_bulk'])) {
    guard('reward_input');
    
    $reward_id  = (int) $_POST['jenis_reward_id'];
    $tanggal    = $_POST['tanggal'];
    $santri_ids = $_POST['santri_ids'] ?? [];
    $user_id    = $_SESSION['user_id'];

    if (empty($santri_ids)) {
        header("Location: create.php"); exit;
    }

    // Ambil nilai reward
    $q_reward = mysqli_query($conn, "SELECT poin_reward FROM jenis_reward WHERE id = $reward_id");
    $d_reward = mysqli_fetch_assoc($q_reward);
    $nilai_pengurang = (int)$d_reward['poin_reward'];

    mysqli_begin_transaction($conn);

    try {
        foreach ($santri_ids as $sid) {
            $sid = (int) $sid;

            // 1. Simpan Log
            $log = "INSERT INTO daftar_reward (santri_id, jenis_reward_id, tanggal, dicatat_oleh) 
                    VALUES ('$sid', '$reward_id', '$tanggal', '$user_id')";
            if (!mysqli_query($conn, $log)) throw new Exception("Gagal log history");

            // 2. LOGIKA: Kurangi poin (bisa jadi minus/tabungan)
            $update = "UPDATE santri SET poin_aktif = poin_aktif - $nilai_pengurang WHERE id = $sid";
            
            if (!mysqli_query($conn, $update)) throw new Exception("Gagal update poin santri");
        }

        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => count($santri_ids) . " santri berhasil diberi reward. Poin telah diperbarui."];
        header("Location: ../history/index.php");

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        header("Location: create.php");
    }
} else {
    header("Location: create.php");
}
?>