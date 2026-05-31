<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('pelanggaran_pengabdian_input');

// Pastikan form disubmit dengan benar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pelanggaran'])) {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/csrf_expired.php';
        exit;
    }

    $tipe_pelanggaran = $_POST['tipe_pelanggaran'] ?? '';
    $dicatat_oleh = $_SESSION['user_id'];

    // =======================================================
    // === CABANG LOGIKA 1: JIKA TIPE PELANGGARAN INDIVIDU ===
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

        // Ambil info Jenis Pelanggaran (Nama & Poin)
        $query_jp = "SELECT nama_pelanggaran, poin FROM jenis_pelanggaran WHERE id = ?";
        $stmt_jp = mysqli_prepare($conn, $query_jp);
        mysqli_stmt_bind_param($stmt_jp, "i", $jenis_pelanggaran_id);
        mysqli_stmt_execute($stmt_jp);
        $result_jp = mysqli_stmt_get_result($stmt_jp);
        $data_pelanggaran = mysqli_fetch_assoc($result_jp);
        mysqli_stmt_close($stmt_jp);

        if (!$data_pelanggaran) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Jenis pelanggaran tidak valid.'];
            header("Location: create.php");
            exit();
        }
        $poin_to_add = (int)$data_pelanggaran['poin'];
        $nama_pelanggaran = $data_pelanggaran['nama_pelanggaran'];

        // Siapkan Query
        $query_insert = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $query_insert);
        $query_update = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $query_update);

        // Mulai Transaksi
        mysqli_begin_transaction($conn);
        $success_count = 0;
        $error_count = 0;
        $error_message = '';
        $last_insert_id = null; // Simpan ID untuk link notifikasi

        foreach ($santri_ids as $santri_id) {
            $santri_id_int = (int)$santri_id;
            
            mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
            $exec_insert = mysqli_stmt_execute($stmt_insert);
            if ($exec_insert) {
                // Ambil ID hanya jika insert berhasil
                $last_insert_id = mysqli_insert_id($conn); 
            }
            
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

        // Finalisasi
        if ($error_count > 0) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => "Terjadi kesalahan! Proses dibatalkan. Error: " . $error_message];
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

            // Catat log input pelanggaran individu
            write_activity_log('CREATE', 'pelanggaran', "Mencatat pelanggaran '" . htmlspecialchars($nama_pelanggaran) . "' (Poin: $poin_to_add) untuk " . count($santri_names) . " santri: " . implode(', ', $santri_names), [
                'santri_ids' => $santri_ids,
                'santri_names' => $santri_names,
                'jenis_pelanggaran_id' => $jenis_pelanggaran_id,
                'nama_pelanggaran' => $nama_pelanggaran,
                'bagian' => 'Pengabdian',
                'poin' => $poin_to_add,
                'tanggal' => $tanggal
            ]);

            $_SESSION['message'] = ['type' => 'success', 'text' => "Berhasil mencatat $success_count pelanggaran individu."];
        }

        header("Location: create.php");
        exit();

    // =======================================================
    // === CABANG LOGIKA 2: JIKA TIPE PELANGGARAN KAMAR ===
    // =======================================================
    } elseif ($tipe_pelanggaran === 'kamar') {

        $kamar_list = $_POST['kamar'] ?? [];
        $catatan_array = $_POST['catatan'] ?? []; 
        $tanggal = $_POST['tanggal_kamar'] ?? '';

        if (empty($kamar_list) || empty($tanggal)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Untuk pelanggaran kebersihan, Anda harus memilih tanggal dan minimal satu kamar.'];
            header("Location: create.php");
            exit();
        }

        $query = "INSERT INTO pelanggaran_kebersihan (kamar, tanggal, dicatat_oleh, catatan) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);

        $success_count = 0;
        foreach ($kamar_list as $kamar) {
            $catatan_spesifik = trim($catatan_array[$kamar] ?? '');
            mysqli_stmt_bind_param($stmt, "ssis", $kamar, $tanggal, $dicatat_oleh, $catatan_spesifik);
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }
        mysqli_stmt_close($stmt);

        if ($success_count > 0) {
            write_activity_log('CREATE', 'pelanggaran', "Mencatat pelanggaran kebersihan untuk " . count($kamar_list) . " kamar: " . implode(', ', $kamar_list), [
                'kamar_list' => $kamar_list,
                'tanggal' => $tanggal,
                'catatan' => $catatan_array
            ]);
            $_SESSION['message'] = ['type' => 'success', 'text' => "Berhasil mencatat $success_count pelanggaran kebersihan kamar."];
        }

        header("Location: create.php");
        exit();

    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tipe pelanggaran tidak valid. Silakan coba lagi.'];
        header("Location: create.php");
        exit();
    }
}

// Jika file diakses langsung, tendang balik
http_response_code(403);
require __DIR__ . '/../../bootstrap/access_denied.php';
exit;