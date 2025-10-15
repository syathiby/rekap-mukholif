<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../init.php';
guard('pelanggaran_kesantrian_input');
?>

<?php

// Atur header agar outputnya dianggap sebagai JSON oleh browser
header('Content-Type: application/json');

// Ambil kata kunci pencarian dari parameter URL (?term=...)
$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

if (strlen($searchTerm) < 2) {
    echo json_encode([]); // Jika ketikan kurang dari 2, kirim array kosong
    exit;
}

// Query pencarian data santri yang aman dengan prepared statement
$query = "SELECT id, nama, kelas, kamar FROM santri WHERE nama LIKE ? LIMIT 10"; // Ditambah limit biar ga berat
$stmt = mysqli_prepare($conn, $query);

$likeTerm = "%" . $searchTerm . "%";
mysqli_stmt_bind_param($stmt, "s", $likeTerm);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$santriArray = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // =======================================================
        // === INI DIA BAGIAN YANG KITA UBAH ===
        // =======================================================
        // Format label untuk ditampilkan di dropdown autocomplete jadi lebih deskriptif
        $label = htmlspecialchars($row['nama']) . ' — Kelas ' . htmlspecialchars($row['kelas']) . ' • Kamar ' . htmlspecialchars($row['kamar']);
        
        // Format data agar bisa dibaca oleh jQuery UI Autocomplete
        $santriArray[] = [
            'id'    => $row['id'],
            'value' => $row['nama'], // Value yang masuk ke input text saat dipilih
            'label' => $label,       // Text yang tampil di dropdown (sudah diformat)
            'nama'  => $row['nama'],
            'kelas' => $row['kelas'],
            'kamar' => $row['kamar']
        ];
    }
}

// Cetak hasil dalam format JSON
echo json_encode($santriArray);

mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;