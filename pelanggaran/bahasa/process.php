<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php'; // Sesuaikan path init.php lu
guard('pelanggaran_bahasa_input'); // Pastikan guard-nya sesuai permission lu

if (isset($_POST['create_bulk_pelanggaran'])) {
    $jenis_pelanggaran_id = (int)$_POST['jenis_pelanggaran_id'];
    $tanggal = $_POST['tanggal'];
    $santri_ids = isset($_POST['santri_ids']) ? $_POST['santri_ids'] : [];
    $dicatat_oleh = $_SESSION['user_id'];

    // 1. Validasi Input
    if (empty($jenis_pelanggaran_id) || empty($tanggal) || empty($santri_ids) || !is_array($santri_ids)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data tidak lengkap. Pilih jenis pelanggaran, tanggal, dan minimal satu santri.'];
        header("Location: ../../create.php");
        exit();
    }

    // 2. Ambil Info Pelanggaran Baru (Poin & Bagian)
    $query_get_info = "SELECT poin, bagian FROM jenis_pelanggaran WHERE id = ?";
    $stmt_get_info = mysqli_prepare($conn, $query_get_info);
    mysqli_stmt_bind_param($stmt_get_info, "i", $jenis_pelanggaran_id);
    mysqli_stmt_execute($stmt_get_info);
    $result_info = mysqli_stmt_get_result($stmt_get_info);
    $data_pelanggaran = mysqli_fetch_assoc($result_info);
    
    // PENTING: Statement ini ditutup DISINI. Jangan ditutup lagi di bawah.
    mysqli_stmt_close($stmt_get_info);

    if (!$data_pelanggaran) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Jenis pelanggaran tidak valid.'];
        header("Location: ../../create.php");
        exit();
    }

    $bagian_pelanggaran = $data_pelanggaran['bagian']; 
    $poin_baru = (int)$data_pelanggaran['poin'];

    // =================================================================
    // PERSIAPAN QUERY (PREPARED STATEMENTS)
    // =================================================================
    
    // A. Query cari pelanggaran lama (Khusus Bahasa)
    $query_cari_lama = "SELECT p.id, p.jenis_pelanggaran_id, p.tanggal, jp.poin 
                        FROM pelanggaran p 
                        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
                        WHERE p.santri_id = ? AND jp.bagian = 'Bahasa'";
    $stmt_cari_lama = mysqli_prepare($conn, $query_cari_lama);

    // B. Query Log History
    $query_log = "INSERT INTO log_bahasa (santri_id, jenis_pelanggaran_id, poin_lama, tanggal_melanggar, diganti_oleh) 
                  VALUES (?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($conn, $query_log);

    // C. Query Kurangi Poin Santri
    $query_kurangi_poin = "UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?";
    $stmt_kurangi_poin = mysqli_prepare($conn, $query_kurangi_poin);

    // D. Query Hapus Pelanggaran Lama
    $query_hapus_lama = "DELETE FROM pelanggaran WHERE id = ?";
    $stmt_hapus_lama = mysqli_prepare($conn, $query_hapus_lama);

    // E. Query Insert Pelanggaran Baru
    $query_insert = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $query_insert);

    // F. Query Tambah Poin Baru
    $query_tambah_poin = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
    $stmt_tambah_poin = mysqli_prepare($conn, $query_tambah_poin);


    // =================================================================
    // EKSEKUSI TRANSAKSI
    // =================================================================
    mysqli_begin_transaction($conn);

    $success_count = 0;
    $error_count = 0;
    $error_message = '';

    foreach ($santri_ids as $santri_id) {
        $santri_id_int = (int)$santri_id;

        // --- STEP 1: LOGIKA KHUSUS BAHASA (REPLACE & LOG) ---
        if ($bagian_pelanggaran === 'Bahasa') {
            
            // Cari data lama
            mysqli_stmt_bind_param($stmt_cari_lama, "i", $santri_id_int);
            mysqli_stmt_execute($stmt_cari_lama);
            $result_lama = mysqli_stmt_get_result($stmt_cari_lama);

            while ($row_lama = mysqli_fetch_assoc($result_lama)) {
                $id_lama = $row_lama['id'];
                $poin_lama = (int)$row_lama['poin'];
                $jp_id_lama = $row_lama['jenis_pelanggaran_id'];
                $tgl_lama = $row_lama['tanggal'];

                // 1.1 Backup ke tabel log_bahasa
                mysqli_stmt_bind_param($stmt_log, "iiisi", $santri_id_int, $jp_id_lama, $poin_lama, $tgl_lama, $dicatat_oleh);
                $exec_log = mysqli_stmt_execute($stmt_log);
                
                if (!$exec_log) {
                    $error_count++;
                    $error_message = "Gagal backup log bahasa: " . mysqli_error($conn);
                    mysqli_rollback($conn);
                    break 2; // Keluar dari foreach
                }

                // 1.2 Kurangi poin santri
                if ($poin_lama > 0) {
                    mysqli_stmt_bind_param($stmt_kurangi_poin, "ii", $poin_lama, $santri_id_int);
                    mysqli_stmt_execute($stmt_kurangi_poin);
                }

                // 1.3 Hapus data lama
                mysqli_stmt_bind_param($stmt_hapus_lama, "i", $id_lama);
                mysqli_stmt_execute($stmt_hapus_lama);
            }
            // Bersihin result set biar loop berikutnya aman
            mysqli_free_result($result_lama);
        }

        // --- STEP 2: INSERT DATA BARU ---
        
        // 2.1 Insert ke tabel pelanggaran
        mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
        $exec_insert = mysqli_stmt_execute($stmt_insert);

        // 2.2 Update poin santri
        $exec_update = true;
        if ($exec_insert && $poin_baru > 0) {
            mysqli_stmt_bind_param($stmt_tambah_poin, "ii", $poin_baru, $santri_id_int);
            $exec_update = mysqli_stmt_execute($stmt_tambah_poin);
        }

        if ($exec_insert && $exec_update) {
            $success_count++;
        } else {
            $error_count++;
            $error_message = mysqli_error($conn);
            mysqli_rollback($conn);
            break; 
        }
    }

    // Tutup statement (KECUALI stmt_get_info yg udah ditutup di atas)
    mysqli_stmt_close($stmt_cari_lama);
    mysqli_stmt_close($stmt_log);
    mysqli_stmt_close($stmt_kurangi_poin);
    mysqli_stmt_close($stmt_hapus_lama);
    mysqli_stmt_close($stmt_insert);
    mysqli_stmt_close($stmt_tambah_poin);

    // =================================================================
    // FINALISASI
    // =================================================================
    if ($error_count > 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "Gagal memproses data! Error: " . $error_message
        ];
    } else {
        mysqli_commit($conn);
        
        $msg_text = "Berhasil! $success_count data tersimpan.";
        if ($bagian_pelanggaran === 'Bahasa') {
            $msg_text .= " Data bahasa lama sudah diarsipkan ke Log.";
        }
        
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => $msg_text
        ];
    }

    header("Location: ../bahasa/create.php");
    exit();
}

header("Location: ../bahasa/create.php");
exit();