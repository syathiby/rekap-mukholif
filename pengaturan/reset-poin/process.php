<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php';
guard('reset_poin_manage');

if (isset($_POST['reset_semua_poin'])) {
    $keterangan = trim($_POST['keterangan']);
    $di_reset_oleh = $_SESSION['user_id'];

    if (empty($keterangan)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Keterangan reset tidak boleh kosong.'];
        header("Location: index.php");
        exit();
    }

    // Mulai transaksi database untuk memastikan semua aman
    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil semua santri yang punya poin
        $query_santri = "SELECT id, poin_aktif FROM santri WHERE poin_aktif > 0";
        $result_santri = mysqli_query($conn, $query_santri);
        $santri_list = mysqli_fetch_all($result_santri, MYSQLI_ASSOC);

        // Siapkan query yang akan dipakai berulang kali
        $stmt_get_permanent = mysqli_prepare($conn, "SELECT SUM(jp.poin) AS total_permanen FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND jp.kategori = 'Sangat Berat'");
        $stmt_log = mysqli_prepare($conn, "INSERT INTO log_reset_poin (id_santri, tanggal_reset, total_poin_sebelum_reset, keterangan, di_reset_oleh) VALUES (?, CURDATE(), ?, ?, ?)");
        $stmt_update = mysqli_prepare($conn, "UPDATE santri SET poin_aktif = ? WHERE id = ?");

        $processed_count = 0;
        foreach ($santri_list as $santri) {
            $id_santri = $santri['id'];
            $poin_sebelum_reset = $santri['poin_aktif'];
            
            // 2. Hitung total poin permanen santri
            mysqli_stmt_bind_param($stmt_get_permanent, "i", $id_santri);
            mysqli_stmt_execute($stmt_get_permanent);
            $result_permanent = mysqli_stmt_get_result($stmt_get_permanent);
            $data_permanent = mysqli_fetch_assoc($result_permanent);
            $poin_baru = $data_permanent['total_permanen'] ?? 0; // Jika tidak ada, poinnya jadi 0

            // 3. Catat ke log reset
            mysqli_stmt_bind_param($stmt_log, "iisi", $id_santri, $poin_sebelum_reset, $keterangan, $di_reset_oleh);
            mysqli_stmt_execute($stmt_log);
            
            // 4. Update poin_aktif santri
            mysqli_stmt_bind_param($stmt_update, "ii", $poin_baru, $id_santri);
            mysqli_stmt_execute($stmt_update);
            
            $processed_count++;
        }
        
        // Tutup semua statement
        mysqli_stmt_close($stmt_get_permanent);
        mysqli_stmt_close($stmt_log);
        mysqli_stmt_close($stmt_update);

        // Jika semua berhasil, simpan permanen
        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => "RESET BERHASIL! Sebanyak $processed_count data poin santri telah direset."];

    } catch (Exception $e) {
        // Jika ada error di tengah jalan, batalkan semua
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => "RESET GAGAL! Terjadi kesalahan pada database: " . $e->getMessage()];
    }

    header("Location: index.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}