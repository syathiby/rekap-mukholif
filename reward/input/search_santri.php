<?php
// PERBAIKAN KRITIS: Gunakan init.php (pola standar) bukan direct include database/auth
// Ini memastikan session aktif dan akses terproteksi dengan benar
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('reward_input'); // WAJIB: Cek izin sebelum lanjut

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

// PERBAIKAN: Gunakan prepared statement, bukan mysqli_real_escape_string
$likeTerm = '%' . $term . '%';
$stmt = $conn->prepare("SELECT id, nama, kelas, kamar, poin_aktif FROM santri WHERE nama LIKE ? ORDER BY nama ASC LIMIT 15");
$stmt->bind_param("s", $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id'    => $row['id'],
        'value' => $row['nama'],
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar'],
        'poin'  => (int)$row['poin_aktif']
    ];
}
$stmt->close();

echo json_encode($data);