<?php 
require_once __DIR__ . '/../header.php';
guard('arsip_manage'); 
?>

<?php

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

// Ambil filter dari URL
$filter_bagian = $_GET['bagian'] ?? 'semua';
$filter_kamar = $_GET['kamar'] ?? 'semua';

// Ambil SEMUA bagian/kategori unik yang ada di arsip ini untuk filter
$stmt_bagian = $conn->prepare("SELECT DISTINCT bagian FROM arsip_data_pelanggaran WHERE arsip_id = ? ORDER BY bagian ASC");
$stmt_bagian->bind_param('i', $arsip_id);
$stmt_bagian->execute();
$bagian_result = $stmt_bagian->get_result();

// Ambil SEMUA kamar unik (hanya untuk tipe umum)
$stmt_kamar = $conn->prepare("SELECT DISTINCT santri_kamar FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum' AND santri_kamar IS NOT NULL AND santri_kamar != '' ORDER BY CAST(santri_kamar AS UNSIGNED) ASC");
$stmt_kamar->bind_param('i', $arsip_id);
$stmt_kamar->execute();
$kamar_result = $stmt_kamar->get_result();


// =======================================================
// === QUERY DINAMIS BERDASARKAN FILTER ===
// =======================================================

// --- Query untuk Kartu Ringkasan (Top Santri & Jenis) ---
$params_summary = [$arsip_id];
$types_summary = "i";
$where_summary_clause = ($filter_bagian !== 'semua') ? " AND bagian = ?" : "";
if ($filter_bagian !== 'semua') {
    $params_summary[] = $filter_bagian;
    $types_summary .= "s";
}

// Tambahkan "AND santri_nama != 'N/A'" untuk memfilter data invalid
$sql_top_santri = "SELECT santri_nama, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum' AND santri_nama != 'N/A' $where_summary_clause GROUP BY santri_nama ORDER BY total DESC LIMIT 3";
$stmt_summary_santri = $conn->prepare($sql_top_santri);
$stmt_summary_santri->bind_param($types_summary, ...$params_summary);
$stmt_summary_santri->execute();
$top_santri = $stmt_summary_santri->get_result();

$sql_top_jenis = "SELECT jenis_pelanggaran_nama, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? $where_summary_clause GROUP BY jenis_pelanggaran_nama ORDER BY total DESC LIMIT 3";
$stmt_summary_jenis = $conn->prepare($sql_top_jenis);
$stmt_summary_jenis->bind_param($types_summary, ...$params_summary);
$stmt_summary_jenis->execute();
$top_jenis = $stmt_summary_jenis->get_result();


// --- Query untuk Tabel Utama ---
$total_data_query = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ?");
$total_data_query->bind_param('i', $arsip_id);
$total_data_query->execute();
$total_data_terekam = $total_data_query->get_result()->fetch_assoc()['total'];

if ($filter_bagian === 'Kebersihan') {
    // Tampilan khusus rekap kebersihan per kamar
    $sql_data = "SELECT santri_kamar, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND bagian = 'Kebersihan' GROUP BY santri_kamar ORDER BY total DESC";
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->bind_param('i', $arsip_id);
} else {
    // Tampilan detail untuk semua bagian lainnya
    $sql_data = "SELECT * FROM arsip_data_pelanggaran WHERE arsip_id = ?";
    $params_data = [$arsip_id];
    $types_data = "i";

    if ($filter_bagian !== 'semua') {
        $sql_data .= " AND bagian = ?";
        $params_data[] = $filter_bagian;
        $types_data .= "s";
    }
    if ($filter_kamar !== 'semua') {
        $sql_data .= " AND santri_kamar = ?";
        $params_data[] = $filter_kamar;
        $types_data .= "s";
    }
    $sql_data .= " ORDER BY tanggal DESC";
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->bind_param($types_data, ...$params_data);
}

$stmt_data->execute();
$data_pelanggaran = $stmt_data->get_result();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Arsip: <?= htmlspecialchars($meta['judul']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
            --danger: #ef4444; --warning: #f59e0b; --success: #10b981;
            --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
            --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; color: var(--text-dark); }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1rem; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .page-header a { text-decoration: none; color: var(--secondary); font-weight: 500; }
        .page-header a:hover { color: var(--primary); }
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--text-dark); }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .summary-card h5 {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
            letter-spacing: 0.05em;
        }
        .summary-card .stat {
            font-size: 4rem;
            font-weight: 700;
        }
        .summary-card ul { list-style: none; padding: 0; margin: 0; }
        .summary-card li { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px dashed var(--border-color); }
        .summary-card li:last-child { border-bottom: none; }
        
        .summary-card-highlight { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; position: relative; overflow: hidden; }
        .summary-card-highlight h5 { color: rgba(255,255,255,0.8); }
        .summary-card-highlight .icon { font-size: 5rem; position: absolute; right: -20px; bottom: -20px; opacity: 0.15; transition: font-size 0.3s, right 0.3s; }
        
        .filter-nav { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .filter-nav a { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 9999px; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .filter-nav a { color: var(--secondary); }
        .filter-nav a:hover { background-color: var(--primary-light); color: var(--primary-dark); border-color: var(--primary-light); }
        .filter-nav .active { background-color: var(--primary); color: white; border-color: var(--primary); }
        
        .filter-nav-secondary { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
        .filter-nav-secondary a { background-color: #f1f5f9; border-color: transparent; color: var(--secondary); }
        .filter-nav-secondary a:hover { background-color: #e2e8f0; color: var(--text-dark); }
        .filter-nav-secondary .active { background-color: var(--secondary); color: white; border-color: var(--secondary); }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; }

        @media (max-width: 768px) {
            .container { padding: 1.5rem 0.75rem; }
            .page-title { font-size: 1.5rem; }
            .summary-grid { gap: 1rem; }
            .summary-card .stat {
                font-size: 3rem;
            }
            .summary-card-highlight .icon { font-size: 4rem; right: -15px; bottom: -15px; }
            th, td { padding: 0.75rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header mb-4"><a href="index.php"><i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Arsip</a></div>
    <h1 class="page-title mb-1"><?= htmlspecialchars($meta['judul']); ?></h1>
    <p class="text-muted">Periode: <?= date('d M Y', strtotime($meta['tanggal_mulai'])); ?> - <?= date('d M Y', strtotime($meta['tanggal_selesai'])); ?></p>
    
    <div class="summary-grid my-4">
        <div class="card summary-card-highlight"><div class="card-body"><i class="fas fa-database icon"></i><h5>Total Data Terekam</h5><p class="stat"><?= $total_data_terekam ?></p></div></div>
        <div class="card summary-card"><div class="card-body"><h5>Top Santri Pelanggar</h5><ul>
            <?php while($s = $top_santri->fetch_assoc()): ?>
                <li><span><?= htmlspecialchars($s['santri_nama']) ?></span> <span class="badge bg-danger rounded-pill"><?= $s['total'] ?></span></li>
            <?php endwhile; if($top_santri->num_rows == 0) echo "<li>Tidak ada data</li>"; ?>
        </ul></div></div>
        <div class="card summary-card"><div class="card-body"><h5>Top Kategori Pelanggaran</h5><ul>
            <?php while($j = $top_jenis->fetch_assoc()): ?>
                <li><span><?= htmlspecialchars($j['jenis_pelanggaran_nama']) ?></span> <span class="badge bg-warning text-dark rounded-pill"><?= $j['total'] ?></span></li>
            <?php endwhile; if($top_jenis->num_rows == 0) echo "<li>Tidak ada data</li>"; ?>
        </ul></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data Arsip</h5>
            <div class="filter-nav d-flex flex-wrap gap-2">
                <a href="?id=<?= $arsip_id ?>&bagian=semua" class="<?= $filter_bagian == 'semua' ? 'active' : '' ?>">Semua Bagian</a>
                <?php mysqli_data_seek($bagian_result, 0); while($b = $bagian_result->fetch_assoc()): ?>
                    <a href="?id=<?= $arsip_id ?>&bagian=<?= urlencode($b['bagian']) ?>" class="<?= $filter_bagian == $b['bagian'] ? 'active' : '' ?>"><?= htmlspecialchars($b['bagian']) ?></a>
                <?php endwhile; ?>
            </div>

            <?php if ($filter_bagian !== 'Kebersihan'): ?>
            <div class="filter-nav filter-nav-secondary d-flex flex-wrap gap-2 mt-3">
                <a href="?id=<?= $arsip_id ?>&bagian=<?= urlencode($filter_bagian) ?>&kamar=semua" class="<?= $filter_kamar == 'semua' ? 'active' : '' ?>">Semua Kamar</a>
                <?php mysqli_data_seek($kamar_result, 0); while($k = $kamar_result->fetch_assoc()): ?>
                    <a href="?id=<?= $arsip_id ?>&bagian=<?= urlencode($filter_bagian) ?>&kamar=<?= urlencode($k['santri_kamar']) ?>" class="<?= $filter_kamar == $k['santri_kamar'] ? 'active' : '' ?>">Kamar <?= htmlspecialchars($k['santri_kamar']) ?></a>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <div class="table-responsive mt-4">
                <table>
                    <?php if ($filter_bagian === 'Kebersihan'): ?>
                        <thead><tr><th>#</th><th>Kamar</th><th class="text-center">Jumlah Pelanggaran</th></tr></thead>
                    <?php else: ?>
                        <thead><tr><th>Tanggal</th><th>Santri</th><th>Kelas/Kamar</th><th>Jenis Pelanggaran</th><th class="text-center">Bagian</th><th class="text-center">Poin</th></tr></thead>
                    <?php endif; ?>
                    
                    <tbody>
                        <?php if($data_pelanggaran->num_rows > 0): ?>
                            <?php if ($filter_bagian === 'Kebersihan'): $no=1; ?>
                                <?php while($row = $data_pelanggaran->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><strong>Kamar <?= htmlspecialchars($row['santri_kamar']) ?></strong></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark rounded-pill fs-6"><?= $row['total'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <?php while($row = $data_pelanggaran->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d M Y, H:i', strtotime($row['tanggal'])); ?></td>
                                    <?php if ($row['tipe'] === 'Kebersihan'): ?>
                                        <td>-</td>
                                        <td>Kamar <?= htmlspecialchars($row['santri_kamar']) ?></td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars($row['santri_nama']) ?></td>
                                        <td><span class="d-block"><?= htmlspecialchars($row['santri_kelas']) ?></span><small class="text-muted">Kamar <?= htmlspecialchars($row['santri_kamar']) ?></small></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($row['jenis_pelanggaran_nama']) ?></td>
                                    <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary-emphasis rounded-pill"><?= htmlspecialchars($row['bagian']) ?></span></td>
                                    <td class="text-center"><span class="fw-bold <?= $row['poin'] > 0 ? 'text-danger' : '' ?>"><?= $row['poin'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php $colspan = ($filter_bagian === 'Kebersihan') ? 3 : 6; ?>
                            <tr><td colspan="<?= $colspan ?>" class="text-center p-5 text-muted">Tidak ada data untuk filter ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
</body>
</html>