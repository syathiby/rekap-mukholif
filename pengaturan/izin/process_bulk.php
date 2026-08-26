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

    $loggedInUserId = (int)($_SESSION['user_id'] ?? 0);
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

    // 3. Filter ID yang aman (bukan admin dan bukan pengelola jika session bukan admin)
    $validIdsStr = implode(',', $validUserIds);
    $safeUsers = []; // [id => role]
    $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    $role_condition = $is_admin ? "role != 'admin'" : "role NOT IN ('admin', 'pengelola')";
    $res = $conn->query("SELECT id, role FROM users WHERE id IN ($validIdsStr) AND $role_condition");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $safeUsers[(int)$row['id']] = strtolower(trim((string)$row['role']));
        }
    }
    
    if (empty($safeUsers)) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    // 4. Cache daftar izin default untuk setiap role yang terlibat
    $uniqueRoles = array_unique(array_values($safeUsers));
    $rolePermCache = [];
    foreach ($uniqueRoles as $rKey) {
        $rolePermCache[$rKey] = [];
        $stmtR = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
        $stmtR->bind_param("s", $rKey);
        $stmtR->execute();
        $resR = $stmtR->get_result();
        while($rowR = $resR->fetch_assoc()) {
            $rolePermCache[$rKey][] = (int)$rowR['permission_id'];
        }
        $stmtR->close();
    }

    // 5. Proses Perubahan Massal dengan Transaksi Database
    $conn->begin_transaction();
    try {
        $stmt_delete = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?");
        $stmt_upsert = $conn->prepare("
            INSERT INTO user_permissions (user_id, permission_id, is_allowed) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)
        ");

        if (!$stmt_delete || !$stmt_upsert) {
            throw new Exception("Gagal mempersiapkan query database.");
        }

        $appliedAdds = 0;
        $appliedRemoves = 0;

        foreach ($safeUsers as $userId => $userRole) {
            $userRolePerms = $rolePermCache[$userRole] ?? [];

            foreach ($actions as $permId => $actionVal) {
                $permIdInt = (int)$permId;
                if ($actionVal === 'no_change') {
                    continue;
                }

                $hasRoleDefault = in_array($permIdInt, $userRolePerms, true);

                if ($actionVal === 'add') {
                    if ($hasRoleDefault) {
                        // Role sudah punya izin ini: Hapus deny override jika ada (kembali ke default role)
                        $stmt_delete->bind_param("ii", $userId, $permIdInt);
                        $stmt_delete->execute();
                    } else {
                        // Role belum punya: Pasang override Allow (1)
                        $isAllowed = 1;
                        $stmt_upsert->bind_param("iii", $userId, $permIdInt, $isAllowed);
                        $stmt_upsert->execute();
                        $appliedAdds++;
                    }
                } elseif ($actionVal === 'remove') {
                    if ($hasRoleDefault) {
                        // Role aslinya punya: Pasang override Deny (0) untuk memblokir
                        $isAllowed = 0;
                        $stmt_upsert->bind_param("iii", $userId, $permIdInt, $isAllowed);
                        $stmt_upsert->execute();
                        $appliedRemoves++;
                    } else {
                        // Role aslinya tidak punya: Cukup hapus allow override jika ada
                        $stmt_delete->bind_param("ii", $userId, $permIdInt);
                        $stmt_delete->execute();
                    }
                }
            }
        }

        $stmt_delete->close();
        $stmt_upsert->close();

        // Kunci semua perubahan!
        $conn->commit();
        touch_permissions_version();

        $userCount = count($safeUsers);
        $_SESSION['success_message'] = "Izin massal berhasil diterapkan ke " . $userCount . " pengguna • " . $appliedAdds . " izin ditambah, " . $appliedRemoves . " izin dicabut";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "❌ Gagal menerapkan perubahan izin massal: " . $e->getMessage();
    }

    header("Location: bulk.php");
    exit;

} else {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}
