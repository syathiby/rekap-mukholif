<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Ambil notifikasi yg belum dibaca (limit 5 terbaru)
    $stmt_notif = $conn->prepare("SELECT id, pesan, link, created_at FROM notifikasi 
                                  WHERE user_id = ? AND is_read = 0 
                                  ORDER BY created_at DESC LIMIT 5");
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_notif->close();

    // Ambil jumlah total notif yg belum dibaca
    $stmt_count = $conn->prepare("SELECT COUNT(id) AS total FROM notifikasi WHERE user_id = ? AND is_read = 0");
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $count = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();

    header('Content-Type: application/json');
    echo json_encode(['count' => $count, 'notifications' => $notifications]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}