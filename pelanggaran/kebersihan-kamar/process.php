<?php
include '../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kamarList = $_POST['kamar'] ?? [];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
    $catatan = $_POST['catatan'] ?? '';
    $jenis_hukuman = $_POST['jenis_hukuman'] ?? 'Tidak Ditentukan';
    $user_id = $_SESSION['user_id'] ?? 1;

    if (empty($kamarList)) {
        $_SESSION['error'] = "Minimal pilih 1 kamar dulu bro.";
        header("Location: index.php");
        exit();
    }

    $berhasil = 0;
    foreach ($kamarList as $kamar) {
        $kamarSafe   = mysqli_real_escape_string($conn, $kamar);
        $catatanSafe = mysqli_real_escape_string($conn, $catatan);
        $tanggalSafe = mysqli_real_escape_string($conn, $tanggal);
        $jenisSafe   = mysqli_real_escape_string($conn, $jenis_hukuman);

        // INSERT ke pelanggaran_kebersihan
        $insert = mysqli_query($conn, "
            INSERT INTO pelanggaran_kebersihan (kamar, tanggal, dicatat_oleh, catatan)
            VALUES ('$kamarSafe', '$tanggalSafe', '$user_id', '$catatanSafe')
        ");

        if ($insert) {
            $berhasil++;
        }
    }

    $_SESSION['success'] = "Berhasil mencatat $berhasil kamar pelanggaran kebersihan.";
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
