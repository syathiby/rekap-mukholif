<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('pelanggaran_tahfidz_input');

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
    $jenis_pelanggaran_id = (int)$_POST['jenis_pelanggaran_id'];
    $tanggal = $_POST['tanggal'];
    $santri_ids = isset($_POST['santri_ids']) ? $_POST['santri_ids'] : [];
    $dicatat_oleh = $_SESSION['user_id'];

    // Validasi Awal: Pastikan semua data penting ada
    if (empty($jenis_pelanggaran_id) || empty($tanggal) || empty($santri_ids) || !is_array($santri_ids)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Pilih jenis pelanggaran, tanggal, dan tambahkan minimal satu santri.']);
            exit();
        }
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data tidak lengkap. Pilih jenis pelanggaran, tanggal, dan tambahkan minimal satu santri.'];
        header("Location: create.php");
        exit();
    }

    // =================================================================
    // LANGKAH #1: Ambil Poin dari Jenis Pelanggaran (INI BARU)
    // =================================================================
    // Kita perlu tahu berapa poin yang akan ditambahkan.
    $query_get_poin = "SELECT poin, nama_pelanggaran, bagian FROM jenis_pelanggaran WHERE id = ?";
    $stmt_get_poin = mysqli_prepare($conn, $query_get_poin);
    mysqli_stmt_bind_param($stmt_get_poin, "i", $jenis_pelanggaran_id);
    mysqli_stmt_execute($stmt_get_poin);
    $result_poin = mysqli_stmt_get_result($stmt_get_poin);
    $data_pelanggaran = mysqli_fetch_assoc($result_poin);
    mysqli_stmt_close($stmt_get_poin);

    // Jika jenis pelanggaran tidak ditemukan atau poinnya 0, kita bisa langsung skip proses update poin
    if (!$data_pelanggaran) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Jenis pelanggaran tidak valid.']);
            exit();
        }
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

    // Query baru untuk mengupdate poin_aktif di tabel santri
    $query_update = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $query_update);


    // =================================================================
    // LANGKAH #3: Gunakan DATABASE TRANSACTION (SANGAT PENTING!)
    // =================================================================
    // Ini biar prosesnya "all or nothing". Kalau ada satu aja error di tengah jalan,
    // semua data yang udah masuk bakal dibatalin. Jadi data lu nggak bakal setengah-setengah.
    mysqli_begin_transaction($conn);

    $success_count = 0;
    $error_count = 0;
    $error_message = '';

    foreach ($santri_ids as $santri_id) {
        $santri_id_int = (int)$santri_id;

        // Proses 1: Masukkan ke tabel pelanggaran
        mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
        $exec_insert = mysqli_stmt_execute($stmt_insert);

        // Proses 2: Update poin_aktif di tabel santri
        $exec_update = true; // Anggap sukses dulu
        if ($exec_insert && $poin_to_add > 0) { // Hanya update jika insert berhasil DAN poinnya lebih dari 0
            mysqli_stmt_bind_param($stmt_update, "ii", $poin_to_add, $santri_id_int);
            $exec_update = mysqli_stmt_execute($stmt_update);
        }

        // Cek apakah kedua proses berhasil
        if ($exec_insert && $exec_update) {
            $success_count++;
        } else {
            $error_count++;
            $error_message = mysqli_error($conn); // Simpan pesan error terakhir
            // Jika ada satu saja yang gagal, langsung batalkan semua dan keluar dari loop
            mysqli_rollback($conn);
            break; 
        }
    }

    // Tutup prepared statements
    mysqli_stmt_close($stmt_insert);
    mysqli_stmt_close($stmt_update);

    // =================================================================
    // LANGKAH #4: Finalisasi (Commit atau Rollback)
    // =================================================================
    if ($error_count > 0) {
        // Jika ada error, sesi pesan diisi dengan info kegagalan.
        // Rollback sudah dijalankan di dalam loop tadi.
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => "Terjadi kesalahan! Proses dibatalkan. Gagal di santri ke-" . ($success_count + 1) . ". Error: " . $error_message
            ]);
            exit();
        }
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "Terjadi kesalahan! Proses dibatalkan. Gagal di santri ke-" . ($success_count + 1) . ". Error: " . $error_message
        ];
    } else {
        // Jika semua berhasil, commit transaksi untuk menyimpan permanen semua perubahan.
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

        // Catat log input pelanggaran
        write_activity_log('CREATE', 'pelanggaran', "Mencatat pelanggaran '" . htmlspecialchars($data_pelanggaran['nama_pelanggaran']) . "' (Poin: $poin_to_add) untuk " . count($santri_names) . " santri: " . implode(', ', $santri_names), [
            'santri_ids' => $santri_ids,
            'santri_names' => $santri_names,
            'jenis_pelanggaran_id' => $jenis_pelanggaran_id,
            'nama_pelanggaran' => $data_pelanggaran['nama_pelanggaran'],
            'bagian' => $data_pelanggaran['bagian'],
            'poin' => $poin_to_add,
            'tanggal' => $tanggal
        ]);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => "Proses selesai. Berhasil mencatat $success_count pelanggaran dan memperbarui poin."
            ]);
            exit();
        }
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Proses selesai. Berhasil mencatat $success_count pelanggaran dan memperbarui poin."
        ];
    }

    header("Location: create.php");
    exit();
}

// Redirect jika ada yang coba akses file ini langsung
if ($is_ajax) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses langsung tidak diizinkan.']);
    exit();
}
http_response_code(403);
require __DIR__ . '/../../bootstrap/access_denied.php';
exit;