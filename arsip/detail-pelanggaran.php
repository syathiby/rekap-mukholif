<?php
require_once __DIR__ . '/../header.php';
guard('arsip_view');

// 1. Ambil ID dari URL, harus ada ID arsip dan ID santri
$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$santri_id = (int)($_GET['santri_id'] ?? 0);

if ($arsip_id < 1 || $santri_id < 1) {
    die('Parameter tidak valid. Pastikan ID Arsip dan ID Santri ada.');
}

// -- BARIS BARU DIMULAI DARI SINI --
// 2. Ambil parameter filter dari URL untuk link "Kembali"
$filter_bagian_kembali = $_GET['bagian'] ?? 'semua';
$filter_kamar_kembali = $_GET['kamar'] ?? 'semua';
$filter_kelas_kembali = $_GET['kelas'] ?? 'semua';

// Buat URL lengkap untuk tombol kembali ke arsip_pelanggaran.php
$params_kembali = http_build_query([
    'id' => $arsip_id, // Parameter di arsip_pelanggaran.php adalah 'id'
    'bagian' => $filter_bagian_kembali,
    'kamar' => $filter_kamar_kembali,
    'kelas' => $filter_kelas_kembali
]);
$link_kembali = "arsip_pelanggaran.php?" . $params_kembali;
// -- BARIS BARU SELESAI --

// 3. Ambil meta data arsipnya
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta_arsip = $stmt_meta->get_result()->fetch_assoc();
if (!$meta_arsip) {
    die('Arsip tidak ditemukan.');
}

// 4. Ambil data santri dari tabel arsip (data historis)
$stmt_santri = $conn->prepare("SELECT * FROM arsip_data_santri WHERE arsip_id = ? AND santri_id = ?");
$stmt_santri->bind_param('ii', $arsip_id, $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) {
    die('Data santri tidak ditemukan di dalam arsip ini.');
}

// 5. Ambil semua data pelanggaran santri tersebut di arsip ini
$stmt_pelanggaran = $conn->prepare(
    "SELECT * FROM arsip_data_pelanggaran WHERE arsip_id = ? AND santri_id = ? ORDER BY tanggal DESC"
);
$stmt_pelanggaran->bind_param('ii', $arsip_id, $santri_id);
$stmt_pelanggaran->execute();
$pelanggaran_result = $stmt_pelanggaran->get_result();

// 6. Hitung total poin & jumlah pelanggaran untuk statistik
$total_poin = 0;
$total_pelanggaran = $pelanggaran_result->num_rows;
$data_pelanggaran_array = [];
while ($row = $pelanggaran_result->fetch_assoc()) {
    $total_poin += $row['poin'];
    $data_pelanggaran_array[] = $row; // Simpan ke array biar bisa di-loop lagi nanti
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggaran: <?= htmlspecialchars($santri['santri_nama']) ?></title>
    
    <style>
        /* CSS tetap sama, tidak ada perubahan */
        :root {
            --primary: #10b981; --primary-light: #d1fae5; --primary-dark: #059669;
            --light-bg: #f8fafc; --card-bg: #ffffff; --border-color: #e2e8f0;
            --text-dark: #1e293b; --text-light: #64748b; --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
        .page-title { color: var(--text-dark); font-weight: 700; }
        .card { box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .stat-card {
            background-color: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 0.75rem; padding: 1.5rem; text-align: center;
        }
        .stat-card .icon { font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2.25rem; font-weight: 700; color: var(--text-dark); }
        .stat-card .label { font-size: 0.9rem; color: var(--text-light); font-weight: 500; }
        .table th { background-color: #f1f5f9; }
    </style>
</head>
<body>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title mb-1">Detail Pelanggaran Santri</h1>
            <p class="text-muted mb-0">Arsip: <strong><?= htmlspecialchars($meta_arsip['judul']); ?></strong></p>
        </div>
        <a href="<?= $link_kembali ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center g-4">
                <div class="col-md-5">
                    <h3 class="fw-bold"><?= htmlspecialchars($santri['santri_nama']) ?></h3>
                    <p class="text-muted mb-0">
                        Kelas: <strong><?= htmlspecialchars($santri['santri_kelas']) ?></strong> / Kamar: <strong><?= htmlspecialchars($santri['santri_kamar']) ?></strong>
                    </p>
                </div>
                <div class="col-md-7">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                                <div class="value"><?= $total_pelanggaran ?></div>
                                <div class="label">Total Pelanggaran</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                                <div class="value text-danger"><?= $total_poin ?></div>
                                <div class="label">Total Poin</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-list-ul me-2 text-primary"></i>Riwayat Pelanggaran di Arsip Ini</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tanggal & Waktu</th>
                        <th>Jenis Pelanggaran</th>
                        <th class="text-center">Bagian</th>
                        <th class="text-center">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_pelanggaran > 0): ?>
                        <?php foreach($data_pelanggaran_array as $row): ?>
                            <tr>
                                <td><?= date('d M Y, H:i', strtotime($row['tanggal'])); ?></td>
                                <td><?= htmlspecialchars($row['jenis_pelanggaran_nama']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary-emphasis rounded-pill">
                                        <?= htmlspecialchars($row['bagian']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-danger fs-5"><?= $row['poin'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center p-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>
                                Bersih! Tidak ada data pelanggaran untuk santri ini di arsip ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>