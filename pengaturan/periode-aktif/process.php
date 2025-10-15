<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../init.php';
guard('periode_aktif_manage');
?>

<?php

$periode_aktif = $_POST['periode_aktif'] ?? '';

if ($periode_aktif === '') {
    $_SESSION['notif'] = [
        'type' => 'danger',
        'msg' => '⚠ Tanggal tidak boleh kosong.'
    ];
    header('Location: index.php');
    exit;
}

// Simpan ke tabel pengaturan
$stmt = $conn->prepare("
    INSERT INTO pengaturan (nama, nilai) 
    VALUES ('periode_aktif', ?)
    ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
");
$stmt->bind_param("s", $periode_aktif);
$stmt->execute();
$stmt->close();

$_SESSION['notif'] = [
    'type' => 'success',
    'msg' => '✅ Periode aktif berhasil disimpan!'
];

header('Location: index.php');
exit;