<?php
// File: rekap-mukholif/rapot/bulk_delete.php

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' (Harus punya izin delete)
guard('rapot_delete');

// 3. Cek apakah ini request POST dan ada ID-nya
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
    set_flash_message('Error: Request tidak valid.', 'danger');
    header('Location: index.php');
    exit;
}

// 4. Ambil dan bersihkan ID
$ids_to_delete = (array)$_POST['ids'];
$sanitized_ids = [];
foreach ($ids_to_delete as $id) {
    // Pastikan semua ID adalah integer
    $sanitized_ids[] = (int)$id;
}

// Hapus ID yang 0 atau dobel
$sanitized_ids = array_filter(array_unique($sanitized_ids));

if (empty($sanitized_ids)) {
    set_flash_message('Error: Tidak ada ID rapot yang valid untuk dihapus.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    // 5. Siapin query DELETE ... IN (...)
    $count = count($sanitized_ids);
    
    // Bikin placeholder tanda tanya: (?, ?, ?)
    $placeholders = implode(',', array_fill(0, $count, '?'));
    
    // Bikin tipe data string: "iii"
    $types = str_repeat('i', $count);
    
    $sql = "DELETE FROM rapot_kepengasuhan WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    
    // Bind semua ID
    $stmt->bind_param($types, ...$sanitized_ids);
    
    $stmt->execute();

    // 6. Cek hasil dan kasih notif
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows > 0) {
        set_flash_message("Berhasil menghapus {$affected_rows} rapot.", 'success');
    } else {
        set_flash_message('Tidak ada rapot yang dihapus (mungkin sudah dihapus sebelumnya).', 'warning');
    }
    
} catch (Exception $e) {
    // Kalo gagal (misal error koneksi)
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

// 7. Balikin ke halaman index (dia bakal ngambil filter dari session)
header('Location: index.php');
exit;
?>