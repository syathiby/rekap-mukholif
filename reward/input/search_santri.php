<?php
require_once '../../db.php';
require_once '../../auth.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$term = mysqli_real_escape_string($conn, $term);

// Ambil Nama, Kelas, Kamar, Poin Aktif
$query = "SELECT id, nama, kelas, kamar, poin_aktif 
          FROM santri 
          WHERE nama LIKE '%$term%' 
          ORDER BY nama ASC LIMIT 15";

$result = mysqli_query($conn, $query);

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

echo json_encode($data);
?>