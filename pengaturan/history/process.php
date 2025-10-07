<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

guard('history_manage');

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
        $stmt = $conn->prepare("
            SELECT p.santri_id, jp.poin
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

        $stmt = $conn->prepare("UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?");
        $stmt->bind_param("ii", $data['poin'], $data['santri_id']);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
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