<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

// Validasi CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/csrf_expired.php';
    exit;
}
$action = $_POST['action'] ?? '';



// === PROSES PENGHAPUSAN ARSIP (DARI INDEX.PHP) ===
if ($action === 'delete') {
    guard('arsip_delete');

    if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = 'ID Arsip tidak valid.';
        header('Location: index.php');
        exit;
    }
    $arsip_id = (int)$_POST['id'];

    // Cuma butuh ini doang!
    try {
        // Ambil info arsip buat log
        $stmt_info = $conn->prepare("SELECT judul FROM arsip WHERE id = ?");
        $stmt_info->bind_param('i', $arsip_id);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        // Hapus data induknya aja, anak-anaknya bakal ikut kehapus otomatis
        // berkat ON DELETE CASCADE di database lu.
        $stmt = $conn->prepare("DELETE FROM arsip WHERE id = ?");
        $stmt->bind_param('i', $arsip_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0 && $info) {
            write_activity_log('DELETE', 'backup-restore', "Menghapus data arsip permanen: '" . htmlspecialchars($info['judul']) . "'", ['arsip_id' => $arsip_id]);
        }
        $stmt->close();

        $_SESSION['success_message'] = 'Arsip berhasil dihapus permanen!';

    } catch (mysqli_sql_exception $exception) {
        // Nggak perlu transaksi karena cuma 1 query
        $_SESSION['error_message'] = 'Gagal menghapus arsip: ' . $exception->getMessage();
    }
    
    header('Location: ../index.php');
    exit;
}

// Jika action tidak dikenali, tendang balik
http_response_code(403);
require __DIR__ . '/../../bootstrap/access_denied.php';
exit;
