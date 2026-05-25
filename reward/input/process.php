<?php
require_once __DIR__ . '/../../bootstrap/init.php';

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
    $q_reward = mysqli_prepare($conn, "SELECT poin_reward FROM jenis_reward WHERE id = ?");
    mysqli_stmt_bind_param($q_reward, "i", $reward_id);
    mysqli_stmt_execute($q_reward);
    $res_reward = mysqli_stmt_get_result($q_reward);
    $d_reward = mysqli_fetch_assoc($res_reward);
    mysqli_stmt_close($q_reward);
    $nilai_pengurang = (int)$d_reward['poin_reward'];

    mysqli_begin_transaction($conn);

    try {
        $log_stmt = mysqli_prepare($conn, "INSERT INTO daftar_reward (santri_id, jenis_reward_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)");
        $update_stmt = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");

        foreach ($santri_ids as $sid) {
            $sid = (int) $sid;

            // 1. Simpan Log
            mysqli_stmt_bind_param($log_stmt, "iisi", $sid, $reward_id, $tanggal, $user_id);
            if (!mysqli_stmt_execute($log_stmt)) throw new Exception("Gagal log history");

            // 2. LOGIKA: Kurangi poin (bisa jadi minus/tabungan)
            mysqli_stmt_bind_param($update_stmt, "ii", $nilai_pengurang, $sid);
            if (!mysqli_stmt_execute($update_stmt)) throw new Exception("Gagal update poin santri");
        }
        mysqli_stmt_close($log_stmt);
        mysqli_stmt_close($update_stmt);

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