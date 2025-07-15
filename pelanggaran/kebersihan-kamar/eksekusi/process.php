<?php
include __DIR__ . '/../../../db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kamarList = $_POST['kamar'] ?? [];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
    $catatan = $_POST['catatan'] ?? '';
    $jenis_hukuman = $_POST['jenis_hukuman'] ?? 'Tidak Ditentukan';

    if (empty($kamarList)) {
        $_SESSION['error'] = "Minimal pilih 1 kamar dulu bro.";
        header("Location: index.php");
        exit();
    }

    $berhasil = 0;
    foreach ($kamarList as $kamar) {
        $kamarSafe     = mysqli_real_escape_string($conn, $kamar);
        $catatanSafe   = mysqli_real_escape_string($conn, $catatan);
        $tanggalSafe   = mysqli_real_escape_string($conn, $tanggal);
        $jenisSafe     = mysqli_real_escape_string($conn, $jenis_hukuman);

        // Catat sebagai eksekusi (bukan pelanggaran baru)
        $insert = mysqli_query($conn, "
            INSERT INTO eksekusi_kebersihan (kamar, jenis_sanksi, catatan, tanggal_eksekusi, dicatat_oleh)
            VALUES ('$kamarSafe', '$jenisSafe', '$catatanSafe', '$tanggalSafe', $user_id)
        ");

        if ($insert) {
            $berhasil++;
        }
    }

    $_SESSION['success'] = "Berhasil mencatat $berhasil kamar yang dihukum.";
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
