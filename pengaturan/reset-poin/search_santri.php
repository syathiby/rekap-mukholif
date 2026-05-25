<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';

// Fitur autocomplete: Cari santri yang punya poin > 0 berdasarkan nama
$term = isset($_GET['term']) ? $_GET['term'] : '';

$response = array();

if (!empty($term)) {
    // Cari santri yang namanya mengandung kata kunci dan poin_aktif > 0
    $query = "SELECT id, nama as value, kelas, kamar, poin_aktif FROM santri WHERE nama LIKE ? AND poin_aktif > 0 ORDER BY nama ASC LIMIT 10";
    $stmt = $conn->prepare($query);
    $search_term = '%' . $term . '%';
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $response[] = array(
            'id' => $row['id'],
            'value' => $row['value'],
            'kelas' => $row['kelas'] ?? '-',
            'kamar' => $row['kamar'] ?? '-',
            'poin' => $row['poin_aktif']
        );
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
