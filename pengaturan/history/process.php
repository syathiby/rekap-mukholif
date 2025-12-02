<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../init.php';

// Pastikan user punya hak akses buat manage history
guard('history_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    // Ambil data filter buat redirect balik biar user gak bingung
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $bagian  = $_POST['bagian'] ?? '';
    
    // Ambil ID user yang lagi login buat dicatat sebagai 'penghapus'
    $user_login_id = $_SESSION['user_id'] ?? null; 
    
    $redirect_url = "index.php?tanggal=" . urlencode($tanggal) . "&bagian=" . urlencode($bagian);

    if ($id <= 0) {
        $_SESSION['pesan_error'] = "ID pelanggaran tidak valid!";
        header("Location: $redirect_url");
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        // 1. AMBIL DATA LAMA SEBELUM DIHAPUS (Termasuk Poin & Jenis Pelanggaran)
        // Kita perlu data ini buat disimpen ke log_history
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
        // Mencatat detail pelanggaran + siapa yg menghapus & kapan
        $stmt_log = $conn->prepare("
            INSERT INTO log_history 
            (santri_id, jenis_pelanggaran_id, poin, tanggal_pelanggaran, dicatat_oleh, dihapus_oleh) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // s = string, i = integer
        // Parameter: santri_id, jenis_pelanggaran_id, poin, tgl_pelanggaran, pencatat_awal, penghapus_sekarang
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

        // 3. KEMBALIKAN (KURANGI) POIN SANTRI
        // Karena pelanggaran dihapus, poin santri harus dikurangi sesuai poin pelanggaran tsb
        $stmt_update = $conn->prepare("UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        $stmt_update->bind_param("ii", $data['poin'], $data['santri_id']);
        $stmt_update->execute();

        // 4. HAPUS PERMANEN DARI TABEL PELANGGARAN
        $stmt_delete = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();

        // Kalau semua lancar, COMMIT transaksi
        mysqli_commit($conn);

        $_SESSION['pesan_sukses'] = "Data berhasil dihapus dan tercatat di riwayat.";
        header("Location: $redirect_url");
        exit;

    } catch (Exception $e) {
        // Kalau ada error, ROLLBACK semua perubahan biar database aman
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