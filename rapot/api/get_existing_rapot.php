<?php
// File: rekap-mukholif/rapot/api/get_existing_rapot.php
// Endpoint ringan: kembalikan daftar santri_id yang sudah punya rapot
// untuk bulan + tahun tertentu (digunakan oleh create.php untuk disable dropdown).

require_once __DIR__ . '/../../bootstrap/init.php';

if (!has_permission('rapot_create') && !has_permission('rapot_view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$bulan     = isset($_POST['bulan'])    ? trim($_POST['bulan'])       : '';
$tahun     = isset($_POST['tahun'])    ? (int)$_POST['tahun']        : 0;
$edit_id   = isset($_POST['edit_id'])  ? (int)$_POST['edit_id']      : 0;

// Validasi dasar
$bulan_valid = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

if (!in_array($bulan, $bulan_valid, true) || $tahun < 2000 || $tahun > 2099) {
    echo json_encode(['existing' => []]);
    exit;
}

// Query: cari santri_id yang sudah punya rapot di bulan+tahun ini
// Jika mode edit, kecualikan rapot yang sedang diedit agar santrinya
// tidak ter-disable di dropdown (user masih bisa edit rapot miliknya).
$sql = "SELECT santri_id FROM rapot_kepengasuhan WHERE bulan = ? AND tahun = ?";
$params = [$bulan, $tahun];
$types  = "si";

if ($edit_id > 0) {
    $sql   .= " AND id != ?";
    $params[] = $edit_id;
    $types  .= "i";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$existing_ids = [];
while ($row = $result->fetch_assoc()) {
    $existing_ids[] = (int)$row['santri_id'];
}
$stmt->close();

echo json_encode(['existing' => $existing_ids]);
