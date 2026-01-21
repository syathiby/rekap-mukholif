<?php
// File: rekap-mukholif/rapot/get_reward_santri.php

// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' (Sesuaikan guard-nya kalau beda)
guard('rapot_create');

// 3. Ambil data dari AJAX
$santri_id = (int)($_POST['santri_id'] ?? 0);
$bulan = $_POST['bulan'] ?? '';
$tahun = (int)($_POST['tahun'] ?? 0);

if (empty($santri_id) || empty($bulan) || empty($tahun)) {
    echo '<div class="alert alert-danger">Error: Data tidak lengkap.</div>';
    exit;
}

// 4. Ambil data reward dari tabel daftar_reward & jenis_reward
try {
    $sql = "
        SELECT jr.nama_reward, jr.poin_reward
        FROM daftar_reward dr
        JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE dr.santri_id = ? 
          AND MONTH(dr.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(dr.tanggal) = ?
        ORDER BY dr.tanggal DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $santri_id, $bulan, $tahun);
    $stmt->execute();
    $reward_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: Gagal mengambil data reward.</div>';
    exit;
}

// 5. Buat HTML balasan
$total_poin_reward = 0;
$html = '';

if (empty($reward_list)) {
    $html = '
        <div class="alert alert-warning mb-0">
            <h5 class="alert-heading"><i class="fas fa-info-circle"></i> Kosong</h5>
            <p class="mb-0">Belum ada reward yang tercatat untuk santri ini di periode ini.</p>
        </div>
    ';
} else {
    $html .= '<ul class="list-group list-group-flush">';
    foreach ($reward_list as $reward) {
        $total_poin_reward += $reward['poin_reward'];
        $html .= '
            <li class="list-group-item d-flex justify-content-between align-items-center">
                ' . htmlspecialchars($reward['nama_reward']) . '
                <span class="badge bg-success rounded-pill">+' . $reward['poin_reward'] . ' Poin</span>
            </li>
        ';
    }
    $html .= '
        <li class="list-group-item d-flex justify-content-between align-items-center bg-light text-success">
            <strong>Total Poin Reward</strong>
            <strong>+' . $total_poin_reward . ' Poin</strong>
        </li>
    ';
    $html .= '</ul>';
}

echo $html;
?>