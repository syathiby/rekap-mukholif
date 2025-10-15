<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../init.php';
guard('pelanggaran_bahasa_input');
?>

<?php

// Ambil term pencarian dari request GET
$term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : '';

// Query untuk mencari nama santri, kelas, dan kamar
// Mencari nama santri di mana pun (tidak hanya di awal)
$query = "SELECT id, nama, kelas, kamar FROM santri WHERE nama LIKE '%$term%' LIMIT 10";
$result = mysqli_query($conn, $query);

$santri_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // =======================================================
    // === INI DIA SATU-SATUNYA BARIS YANG KITA UBAH ===
    // =======================================================
    // Format label untuk ditampilkan di dropdown autocomplete jadi lebih deskriptif
    $label = htmlspecialchars($row['nama']) . ' — Kelas ' . htmlspecialchars($row['kelas']) . ' • Kamar ' . htmlspecialchars($row['kamar']);
    
    $santri_data[] = [
        'id'    => $row['id'],
        'value' => $row['nama'], // Value yang masuk ke input text saat dipilih
        'label' => $label,      // Text yang tampil di dropdown
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar']
    ];
}

// Mengembalikan data dalam format JSON
header('Content-Type: application/json');
echo json_encode($santri_data);