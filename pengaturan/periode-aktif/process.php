<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('periode_aktif_manage');
?>

<?php

// Validasi keamanan (CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/csrf_expired.php';
    exit;
}

$periode_aktif = $_POST['periode_aktif'] ?? '';

if ($periode_aktif === '') {
    $_SESSION['notif'] = [
        'type' => 'danger',
        'msg' => '⚠ Tanggal tidak boleh kosong.'
    ];
    header('Location: index.php');
    exit;
}

// Ambil periode lama dulu buat pembanding log
$q_old = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$old_periode = (mysqli_num_rows($q_old) > 0) ? mysqli_fetch_assoc($q_old)['nilai'] : 'Tidak ditentukan';

// Simpan ke tabel pengaturan
$stmt = $conn->prepare("
    INSERT INTO pengaturan (nama, nilai) 
    VALUES ('periode_aktif', ?)
    ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
");
$stmt->bind_param("s", $periode_aktif);
$stmt->execute();
$stmt->close();

// Catat log perubahan periode aktif
write_activity_log('CHANGE_PERIODE', 'periode-aktif', "Mengubah periode aktif sistem dari '" . $old_periode . "' menjadi '" . $periode_aktif . "'", [
    'periode_lama' => $old_periode,
    'periode_baru' => $periode_aktif
]);

$_SESSION['notif'] = [
    'type' => 'success',
    'msg' => '✅ Periode aktif berhasil disimpan!'
];

header('Location: index.php');
exit;