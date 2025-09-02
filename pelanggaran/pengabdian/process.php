<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
guard('pelanggaran_pengabdian_input');

// Pastikan form disubmit dengan benar
if (isset($_POST['submit_pelanggaran'])) {

    $tipe_pelanggaran = $_POST['tipe_pelanggaran'] ?? '';
    $dicatat_oleh = $_SESSION['user_id'];

    // =======================================================
    // === CABANG LOGIKA 1: JIKA TIPE PELANGGARAN INDIVIDU (DIROMBAK) ===
    // =======================================================
    if ($tipe_pelanggaran === 'individu') {

        $santri_ids = $_POST['santri_ids'] ?? [];
        $jenis_pelanggaran_id = (int)($_POST['jenis_pelanggaran_id'] ?? 0);
        $tanggal = $_POST['tanggal_individu'] ?? '';

        // Validasi Awal
        if (empty($santri_ids) || $jenis_pelanggaran_id === 0 || empty($tanggal)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Untuk pelanggaran individu, Anda harus memilih jenis pelanggaran, tanggal, dan minimal satu santri.'];
            header("Location: create.php");
            exit();
        }

        // LANGKAH #1: Ambil Poin dari Jenis Pelanggaran
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

        // LANGKAH #2: Siapkan DUA Query (INSERT dan UPDATE)
        $query_insert = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $query_insert);

        $query_update = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $query_update);

        // LANGKAH #3: Gunakan DATABASE TRANSACTION
        mysqli_begin_transaction($conn);

        $success_count = 0;
        $error_count = 0;
        $error_message = '';

        foreach ($santri_ids as $santri_id) {
            $santri_id_int = (int)$santri_id;

            // Proses 1: Insert riwayat
            mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
            $exec_insert = mysqli_stmt_execute($stmt_insert);

            // Proses 2: Update skor
            $exec_update = true;
            if ($exec_insert && $poin_to_add > 0) {
                mysqli_stmt_bind_param($stmt_update, "ii", $poin_to_add, $santri_id_int);
                $exec_update = mysqli_stmt_execute($stmt_update);
            }

            if (!($exec_insert && $exec_update)) {
                $error_count++;
                $error_message = mysqli_error($conn);
                mysqli_rollback($conn);
                break;
            }
            $success_count++;
        }

        mysqli_stmt_close($stmt_insert);
        mysqli_stmt_close($stmt_update);

        // LANGKAH #4: Finalisasi
        if ($error_count > 0) {
            $_SESSION['message'] = [
                'type' => 'danger',
                'text' => "Terjadi kesalahan! Proses dibatalkan. Gagal di santri ke-" . ($success_count + 1) . ". Error: " . $error_message
            ];
        } else {
            mysqli_commit($conn);
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "Proses selesai. Berhasil mencatat $success_count pelanggaran individu dan memperbarui poin."
            ];
        }

        header("Location: create.php");
        exit();

    // =======================================================
    // === CABANG LOGIKA 2: JIKA TIPE PELANGGARAN KAMAR (TETAP SAMA) ===
    // =======================================================
    } elseif ($tipe_pelanggaran === 'kamar') {

        $kamar_list = $_POST['kamar'] ?? [];
        $catatan = trim($_POST['catatan'] ?? '');
        $tanggal = $_POST['tanggal_kamar'] ?? '';

        // Validasi
        if (empty($kamar_list) || empty($tanggal)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Untuk pelanggaran kebersihan, Anda harus memilih tanggal dan minimal satu kamar.'];
            header("Location: create.php");
            exit();
        }

        $query = "INSERT INTO pelanggaran_kebersihan (kamar, tanggal, dicatat_oleh, catatan) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);

        $success_count = 0;
        foreach ($kamar_list as $kamar) {
            mysqli_stmt_bind_param($stmt, "ssis", $kamar, $tanggal, $dicatat_oleh, $catatan);
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }
        mysqli_stmt_close($stmt);

        $_SESSION['message'] = ['type' => 'success', 'text' => "Berhasil mencatat $success_count pelanggaran kebersihan kamar."];
        header("Location: create.php");
        exit();

    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tipe pelanggaran tidak valid. Silakan coba lagi.'];
        header("Location: create.php");
        exit();
    }
}

// Jika file diakses langsung, tendang balik
header("Location: create.php");
exit();