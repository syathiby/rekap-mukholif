<?php
include '../db.php';

// ambil setting periode (support 2 nama: periode_aktif / periode_mulai)
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama IN ('periode_aktif','periode_mulai') LIMIT 1");
$periode_awal = ($row = mysqli_fetch_assoc($q)) ? $row['nilai'] : date('Y-m-d');

// ambil range dari request (default 7 hari)
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

// tentuin tanggal mulai (paling baru antara periode_awal vs interval filter)
$mulai_filter = date('Y-m-d', strtotime("-$days days"));
$tanggal_mulai = ($periode_awal > $mulai_filter) ? $periode_awal : $mulai_filter;

// query data
$q = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM pelanggaran 
    WHERE DATE(tanggal) >= '$tanggal_mulai'
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");

// siapin data buat chart
$labels = [];
$data   = [];
while ($row = mysqli_fetch_assoc($q)) {
    // label tanggal ditampilkan sesuai range
    $labels[] = ($days >= 365) 
        ? date('M Y', strtotime($row['tanggal'])) // kalau setahun → bulan/tahun
        : date('d M', strtotime($row['tanggal'])); // kalau < setahun → tanggal/bulan

    $data[] = $row['total'];
}

// output JSON
header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'data'   => $data
]);