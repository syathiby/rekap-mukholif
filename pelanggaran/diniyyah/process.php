<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php';
guard('pelanggaran_diniyyah_input');

if (isset($_POST['create_bulk_pelanggaran'])) {
    $jenis_pelanggaran_id = (int)$_POST['jenis_pelanggaran_id'];
    $tanggal = $_POST['tanggal'];
    $santri_ids = isset($_POST['santri_ids']) ? $_POST['santri_ids'] : [];
    $dicatat_oleh = $_SESSION['user_id'];

    // Validasi Awal
    if (empty($jenis_pelanggaran_id) || empty($tanggal) || empty($santri_ids) || !is_array($santri_ids)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data tidak lengkap. Pilih jenis pelanggaran, tanggal, dan tambahkan minimal satu santri.'];
        header("Location: create.php");
        exit();
    }

    // =================================================================
    // LANGKAH #1: Ambil Poin dari Jenis Pelanggaran
    // =================================================================
    $query_get_poin = "SELECT poin FROM jenis_pelanggaran WHERE id = ?";
    $stmt_get_poin = mysqli_prepare($conn, $query_get_poin);
    mysqli_stmt_bind_param($stmt_get_poin, "i", $jenis_pelanggaran_id);
    mysqli_stmt_execute($stmt_get_poin);
    $result_poin = mysqli_stmt_get_result($stmt_get_poin);
    $data_pelanggaran = mysqli_fetch_assoc($result_poin);
    mysqli_stmt_close($stmt_get_poin);

    if (!$data_pelanggaran) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Jenis pelanggaran tidak valid.'];
        header("Location: create.php");
        exit();
    }
    $poin_to_add = (int)$data_pelanggaran['poin'];

    // =================================================================
    // LANGKAH #2: Siapkan DUA Query (INSERT dan UPDATE)
    // =================================================================
    $query_insert = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $query_insert);

    $query_update = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $query_update);

    // =================================================================
    // LANGKAH #3: Gunakan DATABASE TRANSACTION
    // =================================================================
    mysqli_begin_transaction($conn);

    $success_count = 0;
    $error_count = 0;
    $error_message = '';

    foreach ($santri_ids as $santri_id) {
        $santri_id_int = (int)$santri_id;

        // Proses 1: Insert riwayat pelanggaran
        mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
        $exec_insert = mysqli_stmt_execute($stmt_insert);

        // Proses 2: Update poin_aktif santri
        $exec_update = true;
        if ($exec_insert && $poin_to_add > 0) {
            mysqli_stmt_bind_param($stmt_update, "ii", $poin_to_add, $santri_id_int);
            $exec_update = mysqli_stmt_execute($stmt_update);
        }

        // Cek keberhasilan
        if ($exec_insert && $exec_update) {
            $success_count++;
        } else {
            $error_count++;
            $error_message = mysqli_error($conn);
            mysqli_rollback($conn); // Batalkan semua jika ada satu kegagalan
            break;
        }
    }

    mysqli_stmt_close($stmt_insert);
    mysqli_stmt_close($stmt_update);

    // =================================================================
    // LANGKAH #4: Finalisasi (Commit atau Rollback)
    // =================================================================
    if ($error_count > 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "Terjadi kesalahan! Proses dibatalkan. Gagal di santri ke-" . ($success_count + 1) . ". Error: " . $error_message
        ];
    } else {
        mysqli_commit($conn); // Simpan permanen semua perubahan
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Proses selesai. Berhasil mencatat $success_count pelanggaran dan memperbarui poin."
        ];
    }

    header("Location: create.php");
    exit();
}

// Redirect jika akses langsung
header("Location: create.php");
exit();