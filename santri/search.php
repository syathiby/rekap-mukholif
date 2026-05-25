<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('santri_view');  

header('Content-Type: application/json');

$term = isset($_GET['term']) ? $_GET['term'] : '';
$search_term = "%" . $term . "%";

$query = "SELECT id, nama, kelas, kamar FROM santri 
          WHERE nama LIKE ? 
          ORDER BY nama 
          LIMIT 10";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$santri = [];
while ($row = mysqli_fetch_assoc($result)) {
    $santri[] = [
        'id' => $row['id'],
        'nama' => $row['nama'],
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar']
    ];
}

echo json_encode($santri);
?>