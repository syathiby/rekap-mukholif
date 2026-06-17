<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('pelanggaran_bahasa_input');

// PERBAIKAN: Gunakan prepared statement, lebih aman dari mysqli_real_escape_string
$term_raw = $_GET['term'] ?? '';
if (strlen($term_raw) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$likeTerm = '%' . $term_raw . '%';
$query = "
    SELECT 
        s.id, 
        s.nama, 
        s.kelas, 
        s.kamar,
        jp.nama_pelanggaran AS level_bahasa
    FROM santri s
    LEFT JOIN pelanggaran p ON s.id = p.santri_id 
        AND p.jenis_pelanggaran_id IN (SELECT id FROM jenis_pelanggaran WHERE bagian = 'Bahasa')
    LEFT JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE s.nama LIKE ? 
    LIMIT 10
";
$stmt_search = $conn->prepare($query);
$stmt_search->bind_param("s", $likeTerm);
$stmt_search->execute();
$result = $stmt_search->get_result();


$santri_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Format level bahasa jika ada
    $level = $row['level_bahasa'];
    if ($level) {
        $level_clean = trim(str_ireplace(['(Bahasa)', '(bahasa)'], '', $level));
    } else {
        $level_clean = 'Bersih';
    }
    
    $santri_data[] = [
        'id'    => $row['id'],
        'value' => $row['nama'], 
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar'],
        'level' => $level_clean
    ];
}

// Mengembalikan data dalam format JSON
header('Content-Type: application/json');
echo json_encode($santri_data);