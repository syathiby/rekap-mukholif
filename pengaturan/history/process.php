<?php
// Debugging - aktifkan sementara
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Guard dengan permission yang benar
guard('history_manage');

// ✅ FIX: Debug lebih detail
echo "<pre>";
echo "DEBUG DETAIL:\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "POST data: ";
print_r($_POST);
echo "Apakah 'batalkan' ada di POST? " . (isset($_POST['batalkan']) ? 'YA' : 'TIDAK') . "\n";
echo "</pre>";

// ✅ FIX: Ganti pengecekan menjadi cek apakah ada id (lebih reliable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $bagian  = $_POST['bagian'] ?? '';
    
    $redirect_url = "index.php?tanggal=" . urlencode($tanggal) . "&bagian=" . urlencode($bagian);

    if ($id <= 0) {
        $_SESSION['pesan_error'] = "ID pelanggaran tidak valid!";
        header("Location: $redirect_url");
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        // Ambil poin & santri
        $stmt = $conn->prepare("
            SELECT p.santri_id, jp.poin
            FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare statement gagal: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data) {
            throw new Exception("Data pelanggaran dengan ID $id tidak ditemukan!");
        }

        // Kurangi poin santri
        $stmt = $conn->prepare("UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement update santri gagal: " . $conn->error);
        }
        $stmt->bind_param("ii", $data['poin'], $data['santri_id']);
        $stmt->execute();

        // Hapus data pelanggaran
        $stmt = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement delete pelanggaran gagal: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();

        mysqli_commit($conn);

        $_SESSION['pesan_sukses'] = "Data pelanggaran berhasil dibatalkan.";
        header("Location: $redirect_url");
        exit;

    } catch (Exception $e) {
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