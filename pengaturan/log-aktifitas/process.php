<?php
// FILE: process.php (pengaturan/log-aktifitas/process.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';

// Proteksi Akses: Hanya role 'admin' yang bisa melakukan pembersihan log
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Admin Utama yang memiliki wewenang ini.']);
    exit;
}

// Hanya terima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.']);
    exit;
}

$action   = $_POST['action'] ?? '';
$scope    = $_POST['scope'] ?? '';
$password = $_POST['password'] ?? '';

if ($action !== 'clean_logs' || !in_array($scope, ['all', 'older_6_months'], true)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Aksi atau opsi pembersihan tidak valid.']);
    exit;
}

if (empty($password)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Password wajib diisi untuk verifikasi.']);
    exit;
}

// 1. Verifikasi Password Admin — gunakan password_verify (bcrypt), bukan SHA-256
$admin_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

if (!$admin) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Data admin tidak ditemukan.']);
    exit;
}

// Dukung bcrypt (password_verify) maupun SHA-256 legacy
$password_valid = false;
if (strlen($admin['password']) === 64 && ctype_xdigit($admin['password'])) {
    // Legacy SHA-256 hash (64 hex chars)
    $password_valid = hash_equals($admin['password'], hash('sha256', $password));
} else {
    // Modern bcrypt via password_hash
    $password_valid = password_verify($password, $admin['password']);
}

if (!$password_valid) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Password verifikasi salah!']);
    exit;
}

// 2. Tentukan kueri pembersihan & detail log audit
$affected_rows = 0;
$success = false;
$message = '';

if ($scope === 'older_6_months') {
    // Catat aktivitas SEBELUM penghapusan agar tercatat permanen
    write_activity_log('DELETE', 'log-aktifitas', 'Admin memicu pembersihan log aktivitas yang berumur lebih dari 6 bulan.');

    // Gunakan prepared statement — tidak ada interpolasi string langsung
    $stmt_del = $conn->prepare("DELETE FROM log_aktifitas WHERE dibuat_pada < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
    if ($stmt_del && $stmt_del->execute()) {
        $affected_rows = $stmt_del->affected_rows;
        $stmt_del->close();
        $success = true;
        $message = "Berhasil menghapus {$affected_rows} catatan log yang berumur lebih dari 6 bulan.";
    } else {
        $message = "Gagal mengeksekusi pembersihan log database.";
    }

} elseif ($scope === 'all') {
    // Untuk penghapusan total: catat log dahulu, simpan ID-nya, lalu hapus sisanya
    $log_written = write_activity_log('DELETE', 'log-aktifitas', 'Admin melakukan pembersihan TOTAL pada log aktivitas.');

    // Ambil ID log yang baru saja ditulis (agar tidak ikut terhapus)
    $last_id = (int)$conn->insert_id;

    if ($log_written && $last_id > 0) {
        // Hapus semua KECUALI log pembersihan yang baru dibuat — gunakan prepared statement
        $stmt_del = $conn->prepare("DELETE FROM log_aktifitas WHERE id != ?");
        $stmt_del->bind_param("i", $last_id);
        if ($stmt_del->execute()) {
            $affected_rows = $stmt_del->affected_rows;
            $stmt_del->close();
            $success = true;
            $message = "Seluruh log aktivitas ({$affected_rows} baris) berhasil dibersihkan. Satu catatan audit pembersihan dipertahankan.";
        } else {
            $message = "Gagal melakukan pembersihan total log database.";
        }
    } else {
        // Jika gagal tulis log audit, tetap lakukan penghapusan total (TRUNCATE lebih aman)
        if ($conn->query("TRUNCATE TABLE log_aktifitas")) {
            $success = true;
            $message = "Seluruh log aktivitas berhasil dibersihkan total.";
        } else {
            $message = "Gagal melakukan pembersihan total log database.";
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'message' => $message
]);
exit;
?>
