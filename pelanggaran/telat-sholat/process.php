<?php
include '../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['santri_ids']) && is_array($_POST['santri_ids'])) {
        $jenis_pelanggaran_id = 1; // ID Telat Sholat
        $timestamp = date('Y-m-d H:i:s'); // Format datetime
        $tanggal_only = date('Y-m-d', strtotime($timestamp)); // Buat eksekusi
        $user_id = $_SESSION['user_id'] ?? 1; // Default user

        $errors = [];
        $success_count = 0;

        foreach ($_POST['santri_ids'] as $santri_id) {
            $santri_id = mysqli_real_escape_string($conn, $santri_id);
            $check_santri = mysqli_query($conn, "SELECT id FROM santri WHERE id = '$santri_id'");

            if (mysqli_num_rows($check_santri) === 0) {
                $errors[] = "Santri dengan ID $santri_id tidak ditemukan";
                continue;
            }

            // Insert ke pelanggaran
            $query = "
                INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) 
                VALUES ('$santri_id', '$jenis_pelanggaran_id', '$timestamp', '$user_id')
            ";

            if (mysqli_query($conn, $query)) {
                $success_count++;
                $pelanggaran_id = mysqli_insert_id($conn);
            } else {
                $errors[] = "Gagal mencatat pelanggaran untuk santri ID $santri_id: " . mysqli_error($conn);
            }
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
        }

        if ($success_count > 0) {
            $_SESSION['success'] = "Berhasil mencatat $success_count pelanggaran!";
        }

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Tidak ada santri yang dipilih!";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
