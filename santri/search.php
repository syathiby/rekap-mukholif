<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('santri_view');  

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php';
?>

<?php
header('Content-Type: application/json');

$term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : '';

$query = "SELECT id, nama, kelas, kamar FROM santri 
          WHERE nama LIKE '%$term%' 
          ORDER BY nama 
          LIMIT 10";

$result = mysqli_query($conn, $query);

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