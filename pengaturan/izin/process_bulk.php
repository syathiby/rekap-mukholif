<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 0. Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/csrf_expired.php';
        exit;
    }

    // 1. Validasi Input Pengguna
    if (!isset($_POST['user_ids']) || !is_array($_POST['user_ids']) || empty($_POST['user_ids'])) {
        $_SESSION['error_message'] = "❌ Mohon pilih minimal satu pengguna sasaran.";
        header("Location: bulk.php");
        exit;
    }

    // 2. Validasi Input Tindakan Izin
    if (!isset($_POST['actions']) || !is_array($_POST['actions'])) {
        $_SESSION['error_message'] = "❌ Data tindakan izin tidak valid.";
        header("Location: bulk.php");
        exit;
    }

    $userIds = $_POST['user_ids'];
    $actions = $_POST['actions']; // Format: [perm_id => 'add' | 'remove' | 'no_change']

    $loggedInUserId = $_SESSION['user_id'] ?? null;
    $validUserIds = [];
    foreach ($userIds as $uid) {
        $uidInt = (int)$uid;
        if ($uidInt !== $loggedInUserId && $uidInt > 0) {
            $validUserIds[] = $uidInt;
        }
    }

    if (empty($validUserIds)) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // 3.1. Pencegahan Eskalasi Hak Akses: Filter ID yang berstatus admin (dan pengelola jika bukan admin)
    $validIdsStr = implode(',', $validUserIds);
    $safeUserIds = [];
    $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    $role_condition = $is_admin ? "role != 'admin'" : "role NOT IN ('admin', 'pengelola')";
    $res = $conn->query("SELECT id FROM users WHERE id IN ($validIdsStr) AND $role_condition");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $safeUserIds[] = (int)$row['id'];
        }
    }
    
    $validUserIds = $safeUserIds;

    if (empty($validUserIds)) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // 4. Proses Perubahan Massal dengan Transaksi Database
    $conn->begin_transaction();
    try {
        // Persiapkan query check, insert, dan delete agar eksekusi sangat cepat
        $stmt_check = $conn->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND permission_id = ?");
        $stmt_insert = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
        $stmt_delete = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?");

        if (!$stmt_check || !$stmt_insert || !$stmt_delete) {
            throw new Exception("Gagal mempersiapkan query database.");
        }

        $appliedAdds = 0;
        $appliedRemoves = 0;

        foreach ($validUserIds as $userId) {
            foreach ($actions as $permId => $actionVal) {
                $permIdInt = (int)$permId;
                
                if ($actionVal === 'add') {
                    // Cek apakah sudah punya izin ini
                    $stmt_check->bind_param("ii", $userId, $permIdInt);
                    $stmt_check->execute();
                    $result = $stmt_check->get_result();
                    $alreadyHas = $result->num_rows > 0;
                    
                    if (!$alreadyHas) {
                        $stmt_insert->bind_param("ii", $userId, $permIdInt);
                        $stmt_insert->execute();
                        $appliedAdds++;
                    }
                } elseif ($actionVal === 'remove') {
                    // Jalankan penghapusan izin
                    $stmt_delete->bind_param("ii", $userId, $permIdInt);
                    $stmt_delete->execute();
                    if ($stmt_delete->affected_rows > 0) {
                        $appliedRemoves++;
                    }
                }
                // Jika 'no_change', abaikan / biarkan
            }
        }

        // Tutup statement
        $stmt_check->close();
        $stmt_insert->close();
        $stmt_delete->close();

        // Kunci semua perubahan!
        $conn->commit();

        // Susun pesan notifikasi sukses yang informatif
        $userCount = count($validUserIds);
        $_SESSION['success_message'] = "✅ Perubahan massal berhasil diterapkan pada <strong>" . $userCount . "</strong> pengguna terpilih! (Izin ditambahkan: " . $appliedAdds . ", dicabut: " . $appliedRemoves . ")";

    } catch (Exception $e) {
        // Batalkan seluruh perubahan jika terjadi error
        $conn->rollback();
        $_SESSION['error_message'] = "❌ Gagal menerapkan perubahan izin massal: " . $e->getMessage();
    }
} else {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}
?>
