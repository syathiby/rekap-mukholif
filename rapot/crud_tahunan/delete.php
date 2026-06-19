<?php
// rapot/crud_tahunan/delete.php
// Hapus satu rapor tahunan (kembalikan ke status belum dibuat)

require_once __DIR__ . '/../../bootstrap/init.php';
guard('rapot_delete');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash_message('ID rapor tidak valid.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    // Ambil info untuk log
    $stmt_info = $conn->prepare("
        SELECT rt.periode, COALESCE(NULLIF(rt.kamar, ''), s.kamar) as kamar, s.nama
        FROM rapot_tahunan rt
        LEFT JOIN santri s ON rt.santri_id = s.id
        WHERE rt.id = ?
    ");
    $stmt_info->bind_param('i', $id);
    $stmt_info->execute();
    $info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    if (!$info) {
        set_flash_message('Rapor tidak ditemukan.', 'danger');
        header('Location: index.php');
        exit;
    }

    // Cek apakah sudah APPROVED — tidak boleh dihapus langsung
    $stmt_chk = $conn->prepare("SELECT status FROM rapot_tahunan WHERE id = ?");
    $stmt_chk->bind_param('i', $id);
    $stmt_chk->execute();
    $row_chk = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if ($row_chk['status'] === 'APPROVED') {
        set_flash_message('Rapor yang sudah APPROVED tidak bisa dihapus. Batalkan approval terlebih dahulu.', 'warning');
        header('Location: index.php');
        exit;
    }

    $stmt_del = $conn->prepare("DELETE FROM rapot_tahunan WHERE id = ?");
    $stmt_del->bind_param('i', $id);
    $stmt_del->execute();

    if ($stmt_del->affected_rows > 0) {
        write_activity_log('DELETE', 'rapot_tahunan',
            "Menghapus rapor tahunan santri '{$info['nama']}' periode {$info['periode']} kamar {$info['kamar']}",
            ['rapot_tahunan_id' => $id, 'info' => $info]
        );
        set_flash_message('Rapor tahunan berhasil dihapus.', 'success');
    } else {
        set_flash_message('Gagal menghapus rapor tahunan.', 'danger');
    }
    $stmt_del->close();

} catch (Exception $e) {
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

$redirect = 'index.php';
if (!empty($info['kamar']) && !empty($info['periode'])) {
    $redirect = 'list.php?kamar=' . urlencode($info['kamar']) . '&periode=' . urlencode($info['periode']);
}
header('Location: ' . $redirect);
exit;
