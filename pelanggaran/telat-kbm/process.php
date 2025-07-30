<?php
include __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['santri_ids']) && is_array($_POST['santri_ids'])) {
        // Pastikan jenis pelanggaran "Telat Sholat" ada di database
        $jenis_pelanggaran_id = 2; // Sesuaikan dengan ID di tabel jenis_pelanggaran
        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 1; // Sesuaikan dengan sistem login Anda
        
        $errors = [];
        $success_count = 0;
        
        foreach ($_POST['santri_ids'] as $santri_id) {
            // Validasi santri_id
            $santri_id = mysqli_real_escape_string($conn, $santri_id);
            $check_santri = mysqli_query($conn, "SELECT id FROM santri WHERE id = '$santri_id'");
            
            if (mysqli_num_rows($check_santri) === 0) {
                $errors[] = "Santri dengan ID $santri_id tidak ditemukan";
                continue;
            }
            
            // Insert pelanggaran
            $query = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) 
                      VALUES ('$santri_id', '$jenis_pelanggaran_id', '$timestamp', '$user_id')";
            
            if (mysqli_query($conn, $query)) {
                $success_count++;
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
?>