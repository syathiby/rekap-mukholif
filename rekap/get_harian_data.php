<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('rekap_view_statistik');
?>

<?php

// Ambil periode aktif dari pengaturan
$q_setting = $conn->prepare("SELECT nilai FROM pengaturan WHERE nama IN ('periode_aktif','periode_mulai') LIMIT 1");
$q_setting->execute();
$periode_awal = $q_setting->get_result()->fetch_assoc()['nilai'] ?? date('Y-m-d');

// Ambil parameter 'days' dari request, pastikan integer, default 7
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

// Tentukan rentang tanggal
$tanggal_akhir = date('Y-m-d');
$mulai_filter = date('Y-m-d', strtotime("-$days days"));
$tanggal_mulai = max($periode_awal, $mulai_filter); // Ambil tanggal yang lebih baru

// === REVISI UTAMA: Gabungkan 2 tabel pelanggaran dengan UNION ALL ===
// Ini mengambil total pelanggaran harian (umum + kebersihan)
$query_str = "
    SELECT tanggal_harian, COUNT(*) AS total
    FROM (
        SELECT DATE(tanggal) AS tanggal_harian FROM pelanggaran
        UNION ALL
        SELECT DATE(tanggal) AS tanggal_harian FROM pelanggaran_kebersihan
    ) AS semua_pelanggaran
    WHERE tanggal_harian >= ? AND tanggal_harian <= ?
    GROUP BY tanggal_harian
";

$stmt = $conn->prepare($query_str);
$stmt->bind_param("ss", $tanggal_mulai, $tanggal_akhir);
$stmt->execute();
$result = $stmt->get_result();

$pelanggaran_per_hari = [];
while ($row = $result->fetch_assoc()) {
    $pelanggaran_per_hari[$row['tanggal_harian']] = $row['total'];
}


// === REVISI LOGIC: Siapkan data lengkap (anti loncat-loncat) ===
$labels = [];
$data = [];
$current_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_akhir);

// Loop dari tanggal mulai sampai tanggal akhir
while ($current_date <= $end_date) {
    $date_key = $current_date->format('Y-m-d');
    
    // Format label sesuai kebutuhan
    $labels[] = $current_date->format('d M'); 
    
    // Cek apakah ada data pelanggaran di tanggal ini, jika tidak, isi 0
    $data[] = $pelanggaran_per_hari[$date_key] ?? 0;
    
    // Lanjut ke hari berikutnya
    $current_date->modify('+1 day');
}


// Output JSON
header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'data'   => $data
]);
?>