<?php
// File: rekap-mukholif/rapot/crud_bulanan/bulk_delete.php
// (dipindah dari rapot/crud/bulk_delete.php — path diupdate)

require_once __DIR__ . '/../../bootstrap/init.php';
guard('rapot_delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
    set_flash_message('Error: Request tidak valid.', 'danger');
    header('Location: ../../rapot/index.php');
    exit;
}

$ids_to_delete = (array)$_POST['ids'];
$sanitized_ids = array_filter(array_unique(array_map('intval', $ids_to_delete)));

if (empty($sanitized_ids)) {
    set_flash_message('Error: Tidak ada ID rapot yang valid untuk dihapus.', 'danger');
    header('Location: ../../rapot/index.php');
    exit;
}

try {
    $count        = count($sanitized_ids);
    $placeholders = implode(',', array_fill(0, $count, '?'));
    $types        = str_repeat('i', $count);
    $sql          = "DELETE FROM rapot_kepengasuhan WHERE id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$sanitized_ids);
    $stmt->execute();

    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows > 0) {
        write_activity_log('DELETE', 'rapot', "Menghapus $affected_rows rapot kepengasuhan secara massal", ['deleted_ids' => $sanitized_ids]);
        set_flash_message("Berhasil menghapus {$affected_rows} rapot.", 'success');
    } else {
        set_flash_message('Tidak ada rapot yang dihapus (mungkin sudah dihapus sebelumnya).', 'warning');
    }

} catch (Exception $e) {
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

header('Location: ../../rapot/index.php');
exit;
