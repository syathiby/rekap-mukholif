<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php'; // Sesuaikan path init.php lu
guard('pelanggaran_bahasa_input'); // Pastikan guard-nya sesuai permission lu

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.']);
            exit();
        }
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/csrf_expired.php';
        exit;
    }
}

if (isset($_POST['create_bulk_pelanggaran'])) {
    $jenis_pelanggaran_id_raw = $_POST['jenis_pelanggaran_id'] ?? '';
    $tanggal = $_POST['tanggal'];
    $santri_ids = isset($_POST['santri_ids']) ? $_POST['santri_ids'] : [];
    $dicatat_oleh = $_SESSION['user_id'];

    $is_clear = ($jenis_pelanggaran_id_raw === 'clear');
    $jenis_pelanggaran_id = $is_clear ? 0 : (int)$jenis_pelanggaran_id_raw;

    // 1. Validasi Input
    if (empty($jenis_pelanggaran_id_raw) || empty($tanggal) || empty($santri_ids) || !is_array($santri_ids)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Pilih jenis pelanggaran atau opsi bersih, tanggal, dan minimal satu santri.']);
            exit();
        }
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data tidak lengkap. Pilih jenis pelanggaran atau opsi bersih, tanggal, dan minimal satu santri.'];
        header("Location: ../bahasa/create.php");
        exit();
    }

    // 2. Ambil Info Pelanggaran Baru (Poin & Bagian)
    if ($is_clear) {
        $bagian_pelanggaran = 'Bahasa';
        $poin_baru = 0;
        $nama_pelanggaran_baru = 'Bersih (Level 0)';
    } else {
        $query_get_info = "SELECT poin, bagian, nama_pelanggaran FROM jenis_pelanggaran WHERE id = ?";
        $stmt_get_info = mysqli_prepare($conn, $query_get_info);
        mysqli_stmt_bind_param($stmt_get_info, "i", $jenis_pelanggaran_id);
        mysqli_stmt_execute($stmt_get_info);
        $result_info = mysqli_stmt_get_result($stmt_get_info);
        $data_pelanggaran = mysqli_fetch_assoc($result_info);
        
        // PENTING: Statement ini ditutup DISINI. Jangan ditutup lagi di bawah.
        mysqli_stmt_close($stmt_get_info);

        if (!$data_pelanggaran) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Jenis pelanggaran tidak valid.']);
                exit();
            }
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Jenis pelanggaran tidak valid.'];
            header("Location: ../bahasa/create.php");
            exit();
        }

        $bagian_pelanggaran = $data_pelanggaran['bagian']; 
        $poin_baru = (int)$data_pelanggaran['poin'];
        $nama_pelanggaran_baru = $data_pelanggaran['nama_pelanggaran'];
    }

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

        // --- STEP 2: INSERT DATA BARU (Jika bukan pembersihan/clear) ---
        $exec_insert = true;
        $exec_update = true;
        
        if (!$is_clear) {
            // 2.1 Insert ke tabel pelanggaran
            mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
            $exec_insert = mysqli_stmt_execute($stmt_insert);

            // 2.2 Update poin santri
            if ($exec_insert && $poin_baru > 0) {
                mysqli_stmt_bind_param($stmt_tambah_poin, "ii", $poin_baru, $santri_id_int);
                $exec_update = mysqli_stmt_execute($stmt_tambah_poin);
            }
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
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => "Gagal memproses data! Error: " . $error_message
            ]);
            exit();
        }
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "Gagal memproses data! Error: " . $error_message
        ];
    } else {
        mysqli_commit($conn);
        
        // Ambil nama santri untuk dicatat di log
        $santri_names = [];
        if (!empty($santri_ids)) {
            $ids_str = implode(',', array_map('intval', $santri_ids));
            $q_s = $conn->query("SELECT nama FROM santri WHERE id IN ($ids_str)");
            if ($q_s) {
                while ($s_row = $q_s->fetch_assoc()) {
                    $santri_names[] = $s_row['nama'];
                }
            }
        }

        // Catat log input pelanggaran/pembersihan
        if ($is_clear) {
            write_activity_log('DELETE', 'pelanggaran', "Membersihkan pelanggaran bahasa (Reset ke Level 0) untuk " . count($santri_names) . " santri: " . implode(', ', $santri_names), [
                'santri_ids' => $santri_ids,
                'santri_names' => $santri_names,
                'jenis_pelanggaran_id' => $jenis_pelanggaran_id,
                'nama_pelanggaran' => 'Bersih (Level 0)',
                'bagian' => 'Bahasa',
                'poin' => 0,
                'tanggal' => $tanggal
            ]);
        } else {
            write_activity_log('CREATE', 'pelanggaran', "Mencatat pelanggaran '" . htmlspecialchars($nama_pelanggaran_baru) . "' (Poin: $poin_baru) untuk " . count($santri_names) . " santri: " . implode(', ', $santri_names), [
                'santri_ids' => $santri_ids,
                'santri_names' => $santri_names,
                'jenis_pelanggaran_id' => $jenis_pelanggaran_id,
                'nama_pelanggaran' => $nama_pelanggaran_baru,
                'bagian' => $bagian_pelanggaran,
                'poin' => $poin_baru,
                'tanggal' => $tanggal
            ]);
        }
        
        $msg_text = $is_clear ? "Berhasil! Status bahasa $success_count santri dibersihkan ke Level 0." : "Berhasil! $success_count data tersimpan.";
        if ($bagian_pelanggaran === 'Bahasa') {
            $msg_text .= " Data bahasa lama sudah diarsipkan ke Log.";
        }
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => $msg_text
            ]);
            exit();
        }
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => $msg_text
        ];
    }

    header("Location: ../bahasa/create.php");
    exit();
}

if ($is_ajax) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses langsung tidak diizinkan.']);
    exit();
}
http_response_code(403);
require __DIR__ . '/../../bootstrap/access_denied.php';
exit;