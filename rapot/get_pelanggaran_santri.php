<?php
// File: rekap-mukholif/rapot/get_pelanggaran_santri.php

// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM'
guard('rapot_create');

// 3. Ambil data dari AJAX
$santri_id = (int)($_POST['santri_id'] ?? 0);
$bulan = $_POST['bulan'] ?? '';
$tahun = (int)($_POST['tahun'] ?? 0);

if (empty($santri_id) || empty($bulan) || empty($tahun)) {
    echo '<div class="alert alert-danger">Error: Santri, Bulan, atau Tahun belum dipilih.</div>';
    exit;
}

// 4. Ambil data pelanggaran
try {
    $sql = "
        SELECT jp.nama_pelanggaran, jp.poin
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? 
          AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(p.tanggal) = ?
          AND jp.poin > 0
        ORDER BY p.tanggal DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $santri_id, $bulan, $tahun);
    $stmt->execute();
    $pelanggaran_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: Gagal mengambil data pelanggaran.</div>';
    exit;
}

// 5. Buat HTML balasan untuk disuntik ke 'create.php'
$total_poin = 0;
$html = '';

if (empty($pelanggaran_list)) {
    $html = '
        <div class="alert alert-success mb-0">
            <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Bersih!</h5>
            <p class="mb-0">Tidak ada poin pelanggaran yang tercatat untuk santri ini di periode yang dipilih.</p>
        </div>
    ';
} else {
    $html .= '<ul class="list-group list-group-flush">';
    foreach ($pelanggaran_list as $pelanggaran) {
        $total_poin += $pelanggaran['poin'];
        $html .= '
            <li class="list-group-item d-flex justify-content-between align-items-center">
                ' . htmlspecialchars($pelanggaran['nama_pelanggaran']) . '
                <span class="badge bg-danger rounded-pill">' . $pelanggaran['poin'] . ' Poin</span>
            </li>
        ';
    }
    $html .= '
        <li class="list-group-item d-flex justify-content-between align-items-center active">
            <strong>Total Poin Pelanggaran</strong>
            <strong>' . $total_poin . ' Poin</strong>
        </li>
    ';
    $html .= '</ul>';
}

echo $html;
?>