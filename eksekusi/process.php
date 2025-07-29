<?php
include __DIR__ . '../db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggaranList = $_POST['pelanggaran_id'] ?? [];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
    $catatan = $_POST['catatan'] ?? '';
    $jenis_hukuman = $_POST['jenis_hukuman'] ?? 'Tidak Ditentukan';

    if (empty($pelanggaranList)) {
        $_SESSION['error'] = "Minimal pilih 1 pelanggaran dulu bro.";
        header("Location: index.php");
        exit();
    }

    $berhasil = 0;

    foreach ($pelanggaranList as $id) {
        $idSafe = (int)$id;
        $res = mysqli_query($conn, "SELECT kamar FROM pelanggaran_kebersihan WHERE id = $idSafe");
        if ($row = mysqli_fetch_assoc($res)) {
            $kamarSafe = mysqli_real_escape_string($conn, $row['kamar']);
            $catatanSafe = mysqli_real_escape_string($conn, $catatan);
            $tanggalSafe = mysqli_real_escape_string($conn, $tanggal);
            $jenisSafe = mysqli_real_escape_string($conn, $jenis_hukuman);

            $insert = mysqli_query($conn, "
                INSERT INTO eksekusi_kebersihan 
                (pelanggaran_id, kamar, jenis_sanksi, catatan, tanggal_eksekusi, dicatat_oleh)
                VALUES ($idSafe, '$kamarSafe', '$jenisSafe', '$catatanSafe', '$tanggalSafe', $user_id)
            ");

            if ($insert) $berhasil++;
        }
    }

    $_SESSION['success'] = "Berhasil mencatat $berhasil pelanggaran yang dihukum.";
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}