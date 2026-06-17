<?php 
// ✅ FIX: Hapus 'header.php', panggil yang penting aja
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('pelanggaran_pengabdian_input'); 

// --- Mulai Logika Pencarian ---

// PERBAIKAN: Gunakan prepared statement, lebih aman dari mysqli_real_escape_string
$term_raw = isset($_GET['term']) ? $_GET['term'] : '';
if (strlen($term_raw) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$likeTerm = '%' . $term_raw . '%';
$stmt_search = $conn->prepare("SELECT id, nama, kelas, kamar FROM santri WHERE nama LIKE ? LIMIT 10");
$stmt_search->bind_param("s", $likeTerm);
$stmt_search->execute();
$result = $stmt_search->get_result();

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