<?php
// File: rekap-mukholif/rapot/delete.php

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM'
// Bikin izin baru 'rapot_delete'
guard('rapot_delete');

// 3. Cek ID dari URL
if (empty($_GET['id'])) {
    set_flash_message('Error: ID Rapot tidak ditemukan.', 'danger');
    header('Location: index.php');
    exit;
}

$rapot_id = (int)$_GET['id'];

try {
    // 4. Siapin query DELETE (Pake MySQLi)
    $sql = "DELETE FROM rapot_kepengasuhan WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapot_id);
    $stmt->execute();

    // 5. Cek apakah beneran ada baris yang kehapus
    if ($stmt->affected_rows > 0) {
        set_flash_message('Rapot berhasil dihapus.', 'success');
    } else {
        set_flash_message('Gagal menghapus rapot atau rapot tidak ditemukan.', 'danger');
    }

    $stmt->close();
    
} catch (Exception $e) {
    // Kalo gagal (misal error koneksi)
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

// 6. Balikin ke halaman index
header('Location: index.php');
exit;
?>