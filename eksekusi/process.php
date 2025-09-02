<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('eksekusi_manage');

// ✅ FIX 1: Ambil user_id dari session, ini WAJIB.
$user_id = $_SESSION['user_id'] ?? null;

// Jika tidak ada user_id (tidak login), langsung hentikan.
if (!$user_id) {
    $_SESSION['error'] = "Sesi Anda telah berakhir. Silakan login kembali.";
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggaranList = $_POST['pelanggaran_id'] ?? [];
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
    $catatan = $_POST['catatan'] ?? '';
    $jenis_hukuman = $_POST['jenis_hukuman'] ?? 'Tidak Ditentukan';

    if (empty($pelanggaranList)) {
        $_SESSION['error_message'] = "Minimal pilih 1 pelanggaran yang mau dieksekusi.";
        header("Location: index.php");
        exit();
    }

    // ✅ FIX 2: Gunakan Prepared Statements biar aman dari SQL Injection
    $insert_query = "
        INSERT INTO eksekusi_kebersihan 
        (pelanggaran_id, kamar, jenis_sanksi, catatan, tanggal_eksekusi, dicatat_oleh)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt_insert = mysqli_prepare($conn, $insert_query);

    $select_kamar_query = "SELECT kamar FROM pelanggaran_kebersihan WHERE id = ?";
    $stmt_select = mysqli_prepare($conn, $select_kamar_query);

    $berhasil = 0;
    $gagal = 0;

    foreach ($pelanggaranList as $id) {
        $idSafe = (int)$id;

        // Ambil data kamar dengan aman
        mysqli_stmt_bind_param($stmt_select, "i", $idSafe);
        mysqli_stmt_execute($stmt_select);
        $res = mysqli_stmt_get_result($stmt_select);
        
        if ($row = mysqli_fetch_assoc($res)) {
            $kamar = $row['kamar'];
            
            // Masukkan data eksekusi dengan aman
            mysqli_stmt_bind_param(
                $stmt_insert, 
                "issssi", 
                $idSafe, 
                $kamar, 
                $jenis_hukuman, 
                $catatan, 
                $tanggal, 
                $user_id
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                $berhasil++;
            } else {
                $gagal++;
            }
        } else {
            $gagal++;
        }
    }

    // Tutup statement setelah selesai looping
    mysqli_stmt_close($stmt_insert);
    mysqli_stmt_close($stmt_select);

    $_SESSION['success_message'] = "Berhasil mencatat $berhasil eksekusi pelanggaran. Gagal: $gagal.";
    header("Location: index.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}