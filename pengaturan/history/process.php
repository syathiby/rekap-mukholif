<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../bootstrap/init.php';

// Pastikan user punya hak akses buat manage history
guard('history_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $tipe = $_POST['tipe'] ?? 'individu';
    
    // Ambil data filter buat redirect balik biar user gak bingung
    $start_date        = $_POST['start_date']        ?? date('Y-m-d');
    $end_date          = $_POST['end_date']          ?? date('Y-m-d');
    $bagian            = $_POST['bagian']            ?? '';
    $search            = $_POST['search']            ?? '';
    $kamar             = $_POST['kamar']             ?? '';
    $kelas             = $_POST['kelas']             ?? '';
    $jenis_pelanggaran = $_POST['jenis_pelanggaran'] ?? '';
    $page              = intval($_POST['page']        ?? 1);
    
    // Ambil ID user yang lagi login buat dicatat sebagai 'penghapus'
    $user_login_id = $_SESSION['user_id'] ?? null; 
    
    $redirect_url = "index.php?" . http_build_query([
        'start_date'        => $start_date,
        'end_date'          => $end_date,
        'bagian'            => $bagian,
        'search'            => $search,
        'kamar'             => $kamar,
        'kelas'             => $kelas,
        'jenis_pelanggaran' => $jenis_pelanggaran,
        'page'              => $page,
    ]);

    if ($id <= 0) {
        $_SESSION['pesan_error'] = "ID pelanggaran tidak valid!";
        header("Location: $redirect_url");
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        if ($tipe === 'individu') {
            // 1. AMBIL DATA LAMA SEBELUM DIHAPUS (Termasuk Poin & Jenis Pelanggaran)
            $stmt = $conn->prepare("
                SELECT p.*, jp.poin 
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                WHERE p.id = ?
            ");
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            if (!$data) {
                throw new Exception("Data pelanggaran tidak ditemukan!");
            }

            // 2. SIMPAN KE TABEL log_history (Audit Trail)
            $stmt_log = $conn->prepare("
                INSERT INTO log_history 
                (santri_id, jenis_pelanggaran_id, poin, tanggal_pelanggaran, dicatat_oleh, dihapus_oleh) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt_log->bind_param(
                "iiisii", 
                $data['santri_id'], 
                $data['jenis_pelanggaran_id'], 
                $data['poin'], 
                $data['tanggal'], 
                $data['dicatat_oleh'],
                $user_login_id
            );
            $stmt_log->execute();

            // Ambil info nama santri & nama pelanggaran untuk log
            $q_info = $conn->prepare("
                SELECT s.nama AS nama_santri, jp.nama_pelanggaran 
                FROM santri s, jenis_pelanggaran jp 
                WHERE s.id = ? AND jp.id = ?
            ");
            $q_info->bind_param("ii", $data['santri_id'], $data['jenis_pelanggaran_id']);
            $q_info->execute();
            $info = $q_info->get_result()->fetch_assoc();
            $q_info->close();

            // Catat log pembatalan pelanggaran
            write_activity_log('DELETE', 'pelanggaran', "Membatalkan/menghapus pelanggaran santri: '" . htmlspecialchars($info['nama_santri'] ?? 'Santri') . "' - '" . htmlspecialchars($info['nama_pelanggaran'] ?? 'Pelanggaran') . "' (Poin: " . $data['poin'] . ")", [
                'id' => $id,
                'santri_id' => $data['santri_id'],
                'nama_santri' => $info['nama_santri'] ?? '',
                'jenis_pelanggaran_id' => $data['jenis_pelanggaran_id'],
                'nama_pelanggaran' => $info['nama_pelanggaran'] ?? '',
                'poin' => $data['poin']
            ]);

            // 3. KEMBALIKAN (KURANGI) POIN SANTRI
            if ($data['poin'] > 0) {
                $stmt_update = $conn->prepare("UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
                $stmt_update->bind_param("ii", $data['poin'], $data['santri_id']);
                $stmt_update->execute();
            }

            // 4. HAPUS PERMANEN DARI TABEL PELANGGARAN
            $stmt_delete = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            $stmt_delete->execute();

        } else if ($tipe === 'kebersihan') {
            // 1. AMBIL DATA KEBERSIHAN
            $stmt = $conn->prepare("SELECT * FROM pelanggaran_kebersihan WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            if (!$data) {
                throw new Exception("Data pelanggaran kebersihan tidak ditemukan!");
            }

            // 2. SIMPAN KE TABEL log_history_kebersihan
            $stmt_log = $conn->prepare("
                INSERT INTO log_history_kebersihan 
                (kamar, tanggal_pelanggaran, catatan, dicatat_oleh, dihapus_oleh) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt_log->bind_param(
                "sssii", 
                $data['kamar'], 
                $data['tanggal'], 
                $data['catatan'], 
                $data['dicatat_oleh'],
                $user_login_id
            );
            $stmt_log->execute();

            // Catat log pembatalan pelanggaran kebersihan
            write_activity_log('DELETE', 'pelanggaran', "Membatalkan/menghapus pelanggaran kebersihan Kamar " . htmlspecialchars($data['kamar']), [
                'id' => $id,
                'kamar' => $data['kamar'],
                'catatan' => $data['catatan']
            ]);

            // 3. HAPUS PERMANEN DARI TABEL PELANGGARAN KEBERSIHAN
            $stmt_delete = $conn->prepare("DELETE FROM pelanggaran_kebersihan WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            $stmt_delete->execute();
        } else {
            throw new Exception("Tipe pelanggaran tidak dikenali!");
        }

        // Kalau semua lancar, COMMIT transaksi
        mysqli_commit($conn);

        $_SESSION['pesan_sukses'] = "Data berhasil dihapus dan tercatat di riwayat log penghapusan.";
        header("Location: $redirect_url");
        exit;

    } catch (Exception $e) {
        // Kalau ada error, ROLLBACK
        mysqli_rollback($conn);
        $_SESSION['pesan_error'] = "Gagal membatalkan: " . $e->getMessage();
        header("Location: $redirect_url");
        exit;
    }
} else {
    $_SESSION['pesan_error'] = "Akses tidak valid!";
    header('Location: index.php');
    exit;
}
?>