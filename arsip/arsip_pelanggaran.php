<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php';

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
$filter_kelas = $_GET['kelas'] ?? 'semua';

// Ambil data unik untuk dropdown filter (logika ini tetap sama)
$bagian_result = $conn->query("SELECT DISTINCT bagian FROM arsip_data_pelanggaran WHERE arsip_id = $arsip_id AND tipe = 'Umum' ORDER BY bagian ASC");
$kamar_result = $conn->query("SELECT DISTINCT santri_kamar FROM arsip_data_santri WHERE arsip_id = $arsip_id AND santri_kamar IS NOT NULL AND santri_kamar != '' ORDER BY CAST(santri_kamar AS UNSIGNED) ASC");
$kelas_result = $conn->query("SELECT DISTINCT santri_kelas FROM arsip_data_santri WHERE arsip_id = $arsip_id AND santri_kelas IS NOT NULL AND santri_kelas != '' ORDER BY CAST(santri_kelas AS UNSIGNED) ASC");

// --- QUERY UTAMA YANG DIUBAH TOTAL ---
// Kita kelompokkan data pelanggaran berdasarkan santri
$sql_data = "
    SELECT
        santri_id,
        santri_nama,
        santri_kelas,
        santri_kamar,
        COUNT(id) AS total_pelanggaran,
        SUM(poin) AS total_poin
    FROM
        arsip_data_pelanggaran
    WHERE
        arsip_id = ? AND tipe = 'Umum'
";
$params_data = [$arsip_id];
$types_data = "i";

// Terapkan filter
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
if ($filter_kelas !== 'semua') {
    $sql_data .= " AND santri_kelas = ?";
    $params_data[] = $filter_kelas;
    $types_data .= "s";
}

// Kelompokkan dan urutkan
$sql_data .= "
    GROUP BY santri_id, santri_nama, santri_kelas, santri_kamar
    HAVING total_pelanggaran > 0
    ORDER BY total_poin DESC, total_pelanggaran DESC, santri_nama ASC
";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$data_rekap = $stmt_data->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Arsip: <?= htmlspecialchars($meta['judul']) ?></title>
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
            --light-bg: #f8fafc; --card-bg: #ffffff; --border-color: #e2e8f0;
            --text-dark: #1e293b; --text-light: #64748b; --danger: #ef4444;
            --info: #3b82f6; --secondary: #64748b; --gold: #f59e0b;
            --silver: #9ca3af; --bronze: #a16207;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
        .form-select {
            padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.375rem;
            -webkit-appearance: none; appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 16px 12px;
        }
        .btn-outline-info { color: var(--info); border-color: var(--info); }
        .btn-outline-info:hover { background-color: var(--info); color: white; }
        .btn-detail { background-color: var(--primary-light); color: var(--primary-dark); font-weight: 600; text-decoration: none; transition: all 0.2s; }
        .btn-detail:hover { background-color: var(--primary-dark); color: white; }
        .periode-stats { font-size: 0.8rem; font-weight: 500; color: var(--text-light); background-color: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 9999px; white-space: nowrap; }
        .rank-icon { font-size: 1.5rem; }
        .rank-1 .rank-icon { color: var(--gold); }
        .rank-2 .rank-icon { color: var(--silver); }
        .rank-3 .rank-icon { color: var(--bronze); }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row g-3 mb-4 align-items-center">
        <div class="col-md-6">
            <h1 class="h3 mb-1 fw-bold">Detail Arsip: <?= htmlspecialchars($meta['judul']); ?></h1>
            <p class="text-muted mb-0">Periode: <?= date('d M Y', strtotime($meta['tanggal_mulai'])); ?> - <?= date('d M Y', strtotime($meta['tanggal_selesai'])); ?></p>
        </div>
        <div class="col-md-6 d-flex justify-content-start justify-content-md-end gap-2">
            <a href="view.php?id=<?= $arsip_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Kembali</span></a>
            <?php
                // Bikin query string dari filter yang lagi aktif untuk dioper
                $filter_params = http_build_query([
                    'bagian' => $filter_bagian,
                    'kamar' => $filter_kamar,
                    'kelas' => $filter_kelas
                ]);
            ?>
            <a href="arsip_kebersihan.php?id=<?= $arsip_id ?>&<?= $filter_params ?>" class="btn btn-outline-info"><i class="fas fa-broom"></i> <span class="d-none d-sm-inline">Arsip Kebersihan</span></a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data Pelanggaran</h5>
            <form method="get" id="filterForm" class="row g-3">
                <input type="hidden" name="id" value="<?= $arsip_id ?>">
                <div class="col-md-4">
                    <label for="bagian" class="form-label small">Bagian</label>
                    <select name="bagian" id="bagian" class="form-select">
                        <option value="semua">Semua Bagian</option>
                        <?php mysqli_data_seek($bagian_result, 0); while($b = $bagian_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= ($filter_bagian == $b['bagian']) ? 'selected' : '' ?>><?= htmlspecialchars($b['bagian']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="kamar" class="form-label small">Kamar</label>
                    <select name="kamar" id="kamar" class="form-select">
                        <option value="semua">Semua Kamar</option>
                        <?php mysqli_data_seek($kamar_result, 0); while($k = $kamar_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($k['santri_kamar']) ?>" <?= ($filter_kamar == $k['santri_kamar']) ? 'selected' : '' ?>><?= htmlspecialchars($k['santri_kamar']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="kelas" class="form-label small">Kelas</label>
                    <select name="kelas" id="kelas" class="form-select">
                        <option value="semua">Semua Kelas</option>
                        <?php mysqli_data_seek($kelas_result, 0); while($k = $kelas_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($k['santri_kelas']) ?>" <?= ($filter_kelas == $k['santri_kelas']) ? 'selected' : '' ?>><?= htmlspecialchars($k['santri_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
            
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th class="text-center">Peringkat</th>
                            <th>Santri</th>
                            <th class="text-center">Statistik di Arsip Ini</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($data_rekap->num_rows > 0): ?>
                            <?php $no = 1; while($row = $data_rekap->fetch_assoc()): ?>
                            <tr class="rank-<?= $no ?>">
                                <td class="text-center">
                                    <?php if ($no <= 3): ?><i class="fas fa-trophy rank-icon"></i><?php else: ?><span class="fw-bold fs-5"><?= $no ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['santri_nama']) ?></div>
                                    <small class="text-muted">Kls: <?= htmlspecialchars($row['santri_kelas']) ?> | Kmr: <?= htmlspecialchars($row['santri_kamar']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="periode-stats">
                                        <?= $row['total_pelanggaran'] ?> Pelanggaran (+<?= $row['total_poin'] ?> Poin)
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    // Bikin query string dari filter yang lagi aktif untuk dioper
                                    $filter_params = http_build_query([
                                        'bagian' => $filter_bagian,
                                        'kamar' => $filter_kamar,
                                        'kelas' => $filter_kelas
                                    ]);
                                    ?>
                                    <a href="detail-pelanggaran.php?arsip_id=<?= $arsip_id ?>&santri_id=<?= $row['santri_id'] ?>&<?= $filter_params ?>" class="btn btn-sm btn-detail rounded-pill px-3">
                                        <i class="fas fa-info-circle me-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php $no++; endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center p-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>
                                Tidak ada data pelanggaran untuk filter ini.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const filterInputs = filterForm.querySelectorAll('select');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => filterForm.submit());
    });
});
</script>

<?php include '../footer.php'; ?>
</body>
</html>