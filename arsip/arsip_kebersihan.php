<?php
require_once __DIR__ . '/../header.php';
guard('arsip_view'); // Izin akses

// 1. Ambil ID Arsip dari URL
$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id <= 0) {
    die("<div class='container my-4 alert alert-danger'>ID Arsip tidak valid.</div>");
}

// 2. Ambil parameter filter dari URL untuk link "Kembali"
$filter_bagian_kembali = $_GET['bagian'] ?? 'semua';
$filter_kamar_kembali = $_GET['kamar'] ?? 'semua';
$filter_kelas_kembali = $_GET['kelas'] ?? 'semua';

// Buat URL lengkap untuk tombol kembali ke arsip_pelanggaran.php
$params_kembali = http_build_query([
    'id' => $arsip_id,
    'bagian' => $filter_bagian_kembali,
    'kamar' => $filter_kamar_kembali,
    'kelas' => $filter_kelas_kembali
]);
$link_kembali = "arsip_pelanggaran.php?" . $params_kembali;

// 3. Ambil detail arsip utamanya
$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
$stmt_arsip->close();

if (!$arsip) {
    die("<div class='container my-4 alert alert-danger'>Arsip tidak ditemukan.</div>");
}

// 4. Kueri Agregasi: Ambil rekap dari tabel arsip_data_pelanggaran_kebersihan
$stmt_data = $conn->prepare("
    SELECT
        kamar,
        COUNT(id) AS total_pelanggaran,
        MAX(tanggal) AS tanggal_terbaru
    FROM
        arsip_data_pelanggaran_kebersihan
    WHERE
        arsip_id = ? AND kamar IS NOT NULL AND kamar != ''
    GROUP BY
        kamar
    ORDER BY
        total_pelanggaran DESC, tanggal_terbaru DESC
");
$stmt_data->bind_param('i', $arsip_id);
$stmt_data->execute();
$rekap_kamar = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Kebersihan: <?= htmlspecialchars($arsip['judul']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --light-bg: #f5f7fa;
            --card-bg: #ffffff;
            --text-dark: #212529;
            --text-light: #6c757d;
            --border-color: #e0e0e0;
            --gold: #f59e0b;
            --silver: #9ca3af;
            --bronze: #a16207;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }
        .header-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border-left: 5px solid var(--primary);
        }
        .header-card h1 {
            margin: 0;
            color: var(--primary);
            font-size: 24px;
            font-weight: 600;
        }
        .table-wrapper {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        table thead th {
            background-color: var(--light-bg);
            font-weight: 600;
            padding: 16px 12px;
            text-align: center;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            vertical-align: middle;
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }
        table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.04);
        }
        .pelanggaran-count {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .pelanggaran-count.banyak {
            background-color: #ffebee;
            color: #c62828;
        }
        .pelanggaran-count.nol {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .rank-icon {
            font-size: 1.8rem;
        }
        .rank-1 .rank-icon { color: var(--gold); }
        .rank-2 .rank-icon { color: var(--silver); }
        .rank-3 .rank-icon { color: var(--bronze); }
        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-weight: 600;
            background-color: #eef2ff;
            color: var(--secondary);
            border-radius: 9999px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-detail:hover {
            background-color: #e0e7ff;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">

    <div class="header-card d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-broom me-2"></i> Rekap Arsip Kebersihan per Kamar</h1>
            <p class="text-muted mb-0">
                Arsip: <strong><?= htmlspecialchars($arsip['judul']) ?></strong> 
                (<?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?>)
            </p>
        </div>
        <a href="<?= $link_kembali ?>" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 10%;">Peringkat</th>
                    <th>Kamar</th>
                    <th>Jumlah Pelanggaran</th>
                    <th>Pelanggaran Terakhir</th>
                    <th style="width: 15%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekap_kamar)): ?>
                    <tr>
                        <td colspan="5" class="text-center p-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="mb-0 fw-bold">Data Bersih</p>
                            <p class="text-muted small">Tidak ada data pelanggaran kebersihan pada arsip ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rekap_kamar as $index => $item): ?>
                        <tr class="<?= ($index < 3) ? 'rank-'.($index + 1) : '' ?>">
                            <td>
                                <?php if ($index < 3): ?>
                                    <i class='fas fa-trophy rank-icon'></i>
                                <?php else: ?>
                                    <span class="fw-bold"><?= $index + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold fs-5">Kamar <?= htmlspecialchars($item['kamar']) ?></td>
                            <td>
                                <?php $count_class = $item['total_pelanggaran'] > 0 ? 'banyak' : 'nol'; ?>
                                <span class='pelanggaran-count <?= $count_class ?>'><?= $item['total_pelanggaran'] ?></span>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span><?= date('d M Y', strtotime($item['tanggal_terbaru'])) ?></span>
                                    <small class="text-muted"><?= date('H:i', strtotime($item['tanggal_terbaru'])) ?> WIB</small>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $detail_link_params = http_build_query([
                                        'arsip_id' => $arsip_id,
                                        'kamar' => $item['kamar'],
                                        'bagian' => $filter_bagian_kembali,
                                        'kamar_filter' => $filter_kamar_kembali,
                                        'kelas' => $filter_kelas_kembali
                                    ]);
                                    $detail_link = "detail-kamar.php?" . $detail_link_params;
                                ?>
                                <a href="<?= $detail_link ?>" class="btn-detail">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>