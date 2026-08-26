<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/csrf_expired.php';
        exit;
    }

    // Validasi input user_id
    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    $userId = (int)$_POST['user_id'];
    $loggedInUserId = (int)($_SESSION['user_id'] ?? 0); 
    
    // Cegah user mengedit izinnya sendiri
    if ($userId === $loggedInUserId) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }
    
    // Ambil data user target
    $stmt_check_role = $conn->prepare("SELECT username, nama_lengkap, role FROM users WHERE id = ?");
    $stmt_check_role->bind_param("i", $userId);
    $stmt_check_role->execute();
    $result_user = $stmt_check_role->get_result();
    $targetUser = $result_user->fetch_assoc();
    $stmt_check_role->close();

    if (!$targetUser) {
        $_SESSION['error_message'] = "❌ Pengguna tidak ditemukan.";
        header("Location: index.php");
        exit;
    }

    $targetRole = strtolower(trim((string)$targetUser['role']));
    $userName = $targetUser['username'];

    // Proteksi role pengelola & admin bagi non-admin
    $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    if (($targetRole === 'admin' || $targetRole === 'pengelola') && !$is_admin) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }

    $action = $_POST['action'] ?? 'save_overrides';

    // ── AKSI 1: RESET SEMUA KE DEFAULT ROLE ──────────────────────────
    if ($action === 'reset_all') {
        $conn->begin_transaction();
        try {
            $stmt_del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $stmt_del->bind_param("i", $userId);
            $stmt_del->execute();
            $stmt_del->close();

            $conn->commit();
            touch_permissions_version();

            write_activity_log('RESET_PERMISSION', 'izin', "Mereset semua izin khusus user '" . htmlspecialchars($userName) . "' kembali ke default role", [
                'target_user_id' => $userId,
                'target_username' => $userName,
                'role' => $targetRole
            ]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Izin @" . htmlspecialchars($userName) . " di-reset ke default role (" . htmlspecialchars(ucfirst($targetRole)) . ")"
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => "Gagal mereset izin: " . $e->getMessage()
            ];
        }

        header("Location: index.php?user_id=" . $userId);
        exit;
    }

    // ── AKSI 2: SIMPAN OVERRIDES (ALLOW & DENY) ──────────────────────
    $submittedOverrides = $_POST['overrides'] ?? []; // [perm_id => 'default' | 'allow' | 'deny']

    // Ambil daftar izin bawaan role user target
    $rolePermissionIds = [];
    $stmtRole = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
    $stmtRole->bind_param("s", $targetRole);
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    while ($row = $resRole->fetch_assoc()) {
        $rolePermissionIds[] = (int)$row['permission_id'];
    }
    $stmtRole->close();

    $conn->begin_transaction();
    try {
        // 1. Bersihkan semua override lama milik user ini
        $stmt_del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt_del->bind_param("i", $userId);
        $stmt_del->execute();
        $stmt_del->close();

        // 2. Simpan hanya baris yang BENAR-BENAR BERBEDA dari bawaan role
        $stmt_ins = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id, is_allowed) VALUES (?, ?, ?)");
        
        $allowCount = 0;
        $denyCount = 0;
        $overrideDetails = [];

        foreach ($submittedOverrides as $permId => $choice) {
            $permIdInt = (int)$permId;
            $hasRoleDefault = in_array($permIdInt, $rolePermissionIds, true);

            if ($choice === 'allow') {
                // Hanya perlu disimpan ke DB jika role aslinya BELUM punya
                if (!$hasRoleDefault) {
                    $isAllowedVal = 1;
                    $stmt_ins->bind_param("iii", $userId, $permIdInt, $isAllowedVal);
                    $stmt_ins->execute();
                    $allowCount++;
                    $overrideDetails[] = "Allow: ID $permIdInt";
                }
            } elseif ($choice === 'deny') {
                // Hanya perlu disimpan ke DB jika role aslinya SUDAH punya (untuk dicabut/diblokir)
                if ($hasRoleDefault) {
                    $isAllowedVal = 0;
                    $stmt_ins->bind_param("iii", $userId, $permIdInt, $isAllowedVal);
                    $stmt_ins->execute();
                    $denyCount++;
                    $overrideDetails[] = "Deny: ID $permIdInt";
                }
            }
            // Jika 'default': tidak perlu simpan apa-apa
        }
        $stmt_ins->close();

        $conn->commit();
        touch_permissions_version();

        // Catat log aktivitas
        write_activity_log('UPDATE_PERMISSION', 'izin', "Memperbarui izin khusus untuk user '" . htmlspecialchars($userName) . "'", [
            'target_user_id' => $userId,
            'target_username' => $userName,
            'role' => $targetRole,
            'allow_overrides_count' => $allowCount,
            'deny_overrides_count' => $denyCount
        ]);

        $msgParts = [];
        if ($allowCount > 0) $msgParts[] = "$allowCount izin tambahan";
        if ($denyCount > 0) $msgParts[] = "$denyCount izin dicabut";
        $summaryTxt = !empty($msgParts) ? " • " . implode(', ', $msgParts) : "";

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => "Izin @" . htmlspecialchars($userName) . " berhasil diperbarui" . $summaryTxt
        ];

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => "Gagal memperbarui izin: " . $e->getMessage()
        ];
    }

    header("Location: index.php?user_id=" . $userId);
    exit;

} else {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}