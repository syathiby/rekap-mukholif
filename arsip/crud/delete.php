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
        set_flash_message('ID Arsip tidak valid.', 'error');
        header('Location: ../index.php');
        exit;
    }
    $arsip_id = (int)$_POST['id'];

    // 1. Verifikasi Wajib Password Admin
    $admin_password = (string)($_POST['admin_password'] ?? $_POST['password'] ?? '');
    if (empty($admin_password)) {
        set_flash_message('Password Admin wajib diisi untuk menghapus arsip.', 'error');
        header('Location: ../index.php');
        exit;
    }

    // Ambil semua akun user ber-role admin yang aktif
    $stmt_admin = $conn->prepare("SELECT id, username, password FROM users WHERE LOWER(TRIM(role)) = 'admin' AND (is_active = 1 OR is_active IS NULL)");
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result();

    $password_valid = false;
    $admin_matched_id = null;
    $needs_rehash_id = null;

    while ($admin = $res_admin->fetch_assoc()) {
        if (password_verify($admin_password, $admin['password'])) {
            $password_valid = true;
            $admin_matched_id = $admin['id'];
            if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                $needs_rehash_id = $admin['id'];
            }
            break;
        } elseif (strlen($admin['password']) === 64 && ctype_xdigit($admin['password'])) {
            if (hash('sha256', $admin_password) === $admin['password']) {
                $password_valid = true;
                $admin_matched_id = $admin['id'];
                $needs_rehash_id = $admin['id'];
                break;
            }
        }
    }
    $stmt_admin->close();

    if (!$password_valid) {
        set_flash_message('Password Admin yang Anda masukkan salah!', 'error');
        header('Location: ../index.php');
        exit;
    }

    // Rehash otomatis jika masih pakai legacy SHA-256
    if ($needs_rehash_id !== null) {
        $new_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt_upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_upd->bind_param("si", $new_hash, $needs_rehash_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    }

    // 2. Eksekusi penghapusan data arsip
    mysqli_begin_transaction($conn);
    try {
        // Ambil info arsip buat log
        $stmt_info = $conn->prepare("SELECT judul FROM arsip WHERE id = ?");
        $stmt_info->bind_param('i', $arsip_id);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        // Pastikan tabel anak log bahasa & rapot tahunan ikut terhapus bersih
        $conn->query("DELETE FROM arsip_data_log_bahasa WHERE arsip_id = $arsip_id");
        $conn->query("DELETE FROM arsip_data_rapot_tahunan WHERE arsip_id = $arsip_id");

        // Hapus data induk arsip (cascading ke seluruh tabel anak)
        $stmt = $conn->prepare("DELETE FROM arsip WHERE id = ?");
        $stmt->bind_param('i', $arsip_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        mysqli_commit($conn);
        
        if ($affected > 0 && $info) {
            write_activity_log('DELETE', 'backup-restore', "Menghapus data arsip permanen: '" . htmlspecialchars($info['judul']) . "' (Diverifikasi dengan Password Admin)", ['arsip_id' => $arsip_id]);
        }

        set_flash_message('Data arsip berhasil dihapus secara permanen.', 'success');

    } catch (Exception $exception) {
        mysqli_rollback($conn);
        set_flash_message('Gagal menghapus arsip: ' . $exception->getMessage(), 'error');
    }
    
    header('Location: ../index.php');
    exit;
}

// Jika action tidak dikenali, tendang balik
http_response_code(403);
require __DIR__ . '/../../bootstrap/access_denied.php';
exit;
