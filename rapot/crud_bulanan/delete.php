<?php
// File: rekap-mukholif/rapot/crud_bulanan/delete.php
// (dipindah dari rapot/crud/delete.php — path diupdate)

require_once __DIR__ . '/../../bootstrap/init.php';
guard('rapot_delete');

if (empty($_GET['id'])) {
    set_flash_message('Error: ID Rapot tidak ditemukan.', 'danger');
    header('Location: ../../rapot/index.php');
    exit;
}

$rapot_id = (int)$_GET['id'];

$kamar_filter_musyrif = checkMusyrifKamarAccess();
if ($kamar_filter_musyrif !== null) {
    $stmt_kamar_check = $conn->prepare("SELECT s.kamar FROM rapot_kepengasuhan r JOIN santri s ON r.santri_id = s.id WHERE r.id = ?");
    $stmt_kamar_check->bind_param("i", $rapot_id);
    $stmt_kamar_check->execute();
    $res_kamar = $stmt_kamar_check->get_result()->fetch_assoc();
    $stmt_kamar_check->close();
    
    if (!$res_kamar || (int)$res_kamar['kamar'] !== $kamar_filter_musyrif) {
        set_flash_message('Gagal menghapus: Anda tidak memiliki akses ke rapot tersebut (Beda Kamar).', 'danger');
        header('Location: ../../rapot/index.php');
        exit;
    }
}

try {
    // Ambil info rapot sebelum dihapus untuk log
    $stmt_info = $conn->prepare("SELECT r.bulan, r.tahun, s.nama FROM rapot_kepengasuhan r JOIN santri s ON r.santri_id = s.id WHERE r.id = ?");
    $stmt_info->bind_param("i", $rapot_id);
    $stmt_info->execute();
    $info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    $sql  = "DELETE FROM rapot_kepengasuhan WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapot_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        if ($info) {
            write_activity_log('DELETE', 'rapot', "Menghapus rapot kepengasuhan santri '" . htmlspecialchars($info['nama']) . "' periode " . $info['bulan'] . " " . $info['tahun'], [
                'rapot_id' => $rapot_id,
                'info'     => $info,
            ]);
        }
        set_flash_message('Rapot berhasil dihapus.', 'success');
    } else {
        set_flash_message('Gagal menghapus rapot atau rapot tidak ditemukan.', 'danger');
    }

    $stmt->close();

} catch (Exception $e) {
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

header('Location: ../../rapot/index.php');
exit;
