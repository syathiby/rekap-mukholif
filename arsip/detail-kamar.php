<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php';

// 1. Ambil semua parameter dari URL
$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$kamar = $_GET['kamar'] ?? '';

if ($arsip_id <= 0 || empty($kamar)) {
    die("<div class='container my-4 alert alert-danger'>Parameter tidak valid.</div>");
}

// 2. Ambil parameter filter dari URL untuk link "Kembali" nanti
$filter_bagian_kembali = $_GET['bagian'] ?? 'semua';
$filter_kamar_kembali = $_GET['kamar_filter'] ?? 'semua';
$filter_kelas_kembali = $_GET['kelas'] ?? 'semua';

// Buat URL lengkap untuk tombol kembali ke arsip_kebersihan.php
$params_kembali = http_build_query([
    'id' => $arsip_id,
    'bagian' => $filter_bagian_kembali,
    'kamar' => $filter_kamar_kembali,
    'kelas' => $filter_kelas_kembali
]);
$link_kembali = "arsip_kebersihan.php?" . $params_kembali;

// 3. Ambil detail arsip utamanya
$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
$stmt_arsip->close();

if (!$arsip) {
    die("<div class='container my-4 alert alert-danger'>Arsip tidak ditemukan.</div>");
}

// 4. Ambil SEMUA data pelanggaran kebersihan untuk kamar ini di arsip ini
$stmt_data = $conn->prepare("
    SELECT * FROM arsip_data_pelanggaran_kebersihan 
    WHERE arsip_id = ? AND kamar = ? 
    ORDER BY tanggal DESC
");
$stmt_data->bind_param('is', $arsip_id, $kamar);
$stmt_data->execute();
$pelanggaran_list = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();
$total_pelanggaran = count($pelanggaran_list);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kebersihan Kamar <?= htmlspecialchars($kamar) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --danger: #f72585;
            --light-bg: #f5f7fa;
            --card-bg: #ffffff;
            --text-dark: #212529;
            --text-light: #6c757d;
            --border-color: #e0e0e0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
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
        .stat-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card .icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--danger);
            background-color: #ffebee;
        }
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--danger);
            line-height: 1;
        }
        .stat-card .stat-label {
            font-weight: 500;
            color: var(--text-dark);
        }
        .table-wrapper {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        table thead th {
            background-color: var(--light-bg);
            font-weight: 600;
            padding: 16px 15px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        table tbody td {
            padding: 14px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top; /* <<< Ganti ke top biar timeline rapi */
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }
        /* --== STYLE BARU UNTUK TIMELINE ==-- */
        .timeline-item {
            position: relative;
            padding-left: 25px; /* Kasih ruang buat garis & ikon */
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 5px;
            bottom: -20px; /* Bikin garisnya lebih panjang ke bawah */
            width: 2px;
            background-color: var(--primary-light);
        }
        tr:last-child .timeline-item::before {
            display: none; /* Hilangin garis di item terakhir */
        }
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 5px;
            font-size: 1rem;
            color: var(--primary);
            background-color: var(--card-bg); /* Biar nutupin garis */
            padding: 0 3px;
        }
        /* --== AKHIR STYLE TIMELINE ==-- */

        .no-data-placeholder {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            background-color: #fafafa;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">

    <div class="header-card d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1><i class="fas fa-search-plus me-2"></i> Detail Pelanggaran Kamar <?= htmlspecialchars($kamar) ?></h1>
            <p class="text-muted mb-0">
                Arsip: <strong><?= htmlspecialchars($arsip['judul']) ?></strong> 
            </p>
        </div>
        <a href="<?= $link_kembali ?>" class="btn btn-light mt-2 mt-md-0">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>
    
    <div class="stat-card mb-4">
        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-value"><?= $total_pelanggaran ?></div>
            <div class="stat-label">Total Pelanggaran Tercatat</div>
        </div>
    </div>

    <div class="table-wrapper table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width: 25%;">Tanggal & Waktu</th>
                    <th style="width: 55%;">Catatan Pelanggaran</th>
                    <th>Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pelanggaran_list)): ?>
                    <tr>
                        <td colspan="3" class="text-center p-5">
                            <div class="no-data-placeholder p-4 d-inline-block">
                                <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                                <p class="mb-0 fw-bold">Tidak Ada Rincian Pelanggaran</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pelanggaran_list as $item): ?>
                        <tr>
                            <td>
                                <div class="timeline-item">
                                    <i class="fas fa-calendar-alt timeline-icon"></i>
                                    <div>
                                        <span class="fw-bold d-block"><?= date('d M Y', strtotime($item['tanggal'])) ?></span>
                                        <small class="text-muted"><?= date('H:i', strtotime($item['tanggal'])) ?> WIB</small>
                                    </div>
                                </div>
                            </td>
                            <td><?= empty($item['catatan']) ? '<span class="text-muted fst-italic">Tidak ada catatan spesifik.</span>' : nl2br(htmlspecialchars($item['catatan'])) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($item['dicatat_oleh_nama'] ?? 'N/A') ?></td>
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