<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_bahasa'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';
?>

<?php

$bagian = 'Bahasa';

// =======================================================
// BAGIAN 1: TANGKAP "SURAT TUGAS" DARI URL
// =======================================================
// ... (LOGIKA PHP KAMU AMAN, GW GAK SENTUH) ...
if (!isset($_GET['santri_id']) || !isset($_GET['start_date']) || !isset($_GET['end_date'])) {
    die("<div class='container my-4'><div class='alert alert-danger'>Informasi tidak lengkap untuk menampilkan detail.</div></div>");
}
$santri_id = (int)$_GET['santri_id'];
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

// =======================================================
// BAGIAN 2: AMBIL DATA DARI DATABASE
// =======================================================
// ... (LOGIKA PHP KAMU AMAN, GW GAK SENTUH) ...
$stmt_santri = mysqli_prepare($conn, "SELECT nama, kelas, kamar FROM santri WHERE id = ?");
mysqli_stmt_bind_param($stmt_santri, "i", $santri_id);
mysqli_stmt_execute($stmt_santri);
$santri = mysqli_stmt_get_result($stmt_santri)->fetch_assoc();
if (!$santri) {
    die("<div class='container my-4'><div class='alert alert-danger'>Data santri tidak ditemukan.</div></div>");
}
$sql_detail = "
    SELECT 
        p.tanggal,
        jp.nama_pelanggaran,
        jp.poin,
        jp.kategori
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE p.santri_id = ?
      AND jp.bagian = ?
      AND DATE(p.tanggal) BETWEEN ? AND ?
    ORDER BY p.tanggal DESC
";
$stmt_detail = mysqli_prepare($conn, $sql_detail);
mysqli_stmt_bind_param($stmt_detail, "isss", $santri_id, $bagian, $start_date, $end_date);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
$detail_list = mysqli_fetch_all($result_detail, MYSQLI_ASSOC);
$total_pelanggaran = count($detail_list);
$total_poin = array_sum(array_column($detail_list, 'poin'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggaran: <?= htmlspecialchars($santri['nama']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Pakai style yang sama persis kayak halaman rekap biar konsisten */
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
            --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
            --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .page-title { color: var(--text-dark); font-weight: 700; }
        .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .table td { vertical-align: middle; }
        .stat-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: white; }
        .stat-label { font-size: 1rem; color: rgba(255, 255, 255, 0.8); }

        /* =============================================================
        === PERBAIKAN 1: TAMBAHAN CSS RESPONSIVE ===
        =============================================================
        */
        @media (max-width: 767.98px) {
            .page-title {
                font-size: 1.75rem; /* Kecilin font judul di HP */
            }
            .profile-card-info {
                text-align: center; /* Bikin info santri center di HP */
            }
        }
    </style>
</head>
<body>
<div class="container py-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-start align-items-md-center mb-4">
        <a href="javascript:history.back()" class="btn btn-outline-secondary mb-3 mb-md-0"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        
        <h1 class="page-title mb-0 w-100 w-md-auto text-center text-md-end">Detail Pelanggaran Bahasa</h1>
    </div>


    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 profile-card-info text-md-start">
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($santri['nama']) ?></h3>
                    <p class="text-muted mb-0">Kelas: <strong><?= htmlspecialchars($santri['kelas']) ?></strong> | Kamar: <strong><?= htmlspecialchars($santri['kamar']) ?></strong></p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-light text-dark fs-6 p-2 d-block d-md-inline-block text-center text-wrap">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Periode: <?= date('d M Y', strtotime($start_date)) ?> s/d <?= date('d M Y', strtotime($end_date)) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card stat-card text-white p-3">
                <div class="card-body text-center">
                    <div class="stat-number"><?= $total_pelanggaran ?></div>
                    <div class="stat-label">Total Pelanggaran</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card text-white p-3">
                <div class="card-body text-center">
                    <div class="stat-number"><?= $total_poin ?></div>
                    <div class="stat-label">Total Poin</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
             <h5 class="card-title fw-bold mb-0"><i class="fas fa-list-ul me-2"></i>Rincian Pelanggaran</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th>Tanggal & Waktu</th>
                        <th>Nama Pelanggaran</th>
                        <th class="text-center">Kategori</th>
                        <th class="text-center">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detail_list)): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Tidak ada pelanggaran pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detail_list as $index => $row): ?>
                        <tr>
                            <td class="fw-bold"><?= $index + 1 ?></td>
                            <td><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                            <td class="text-center">
                                <?php 
                                    $badge_class = 'bg-secondary';
                                    if ($row['kategori'] == 'Ringan') $badge_class = 'bg-success';
                                    if ($row['kategori'] == 'Sedang') $badge_class = 'bg-warning text-dark';
                                    if ($row['kategori'] == 'Berat') $badge_class = 'bg-danger';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $row['kategori'] ?></span>
                            </td>
                            <td class="text-center fw-bold fs-5 text-danger"><?= $row['poin'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
</body>
</html>