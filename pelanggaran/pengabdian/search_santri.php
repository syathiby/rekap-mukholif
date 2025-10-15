<?php 
// ✅ FIX: Hapus 'header.php', panggil yang penting aja
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../init.php';
guard('pelanggaran_pengabdian_input'); 

// --- Mulai Logika Pencarian ---

// Ambil dan bersihkan istilah pencarian
$term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : '';

// Query ke database
$query = "SELECT id, nama, kelas, kamar FROM santri WHERE nama LIKE '%$term%' LIMIT 10";
$result = mysqli_query($conn, $query);

// Siapkan array untuk menampung data
$santri_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Format label yang akan muncul di dropdown autocomplete
    $label = htmlspecialchars($row['nama']) . ' — Kelas ' . htmlspecialchars($row['kelas']) . ' • Kamar ' . htmlspecialchars($row['kamar']);
    
    // Masukkan ke array dengan format yang dibutuhkan jQuery UI Autocomplete
    $santri_data[] = [
        'id'    => $row['id'],
        'value' => $row['nama'], // Teks yang akan masuk ke input field saat dipilih
        'label' => $label,      // Teks yang akan tampil di dropdown
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar']
    ];
}

// ✅ FIX: Atur header sebagai JSON dan cetak hasilnya
header('Content-Type: application/json');
echo json_encode($santri_data);