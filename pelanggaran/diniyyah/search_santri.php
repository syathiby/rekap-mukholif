<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('pelanggaran_diniyyah_input');
?>

<?php

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