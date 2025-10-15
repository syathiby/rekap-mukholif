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

// Ambil input filter dan search dari URL
$filter_kelas = $_GET['kelas'] ?? '';
$filter_kamar = $_GET['kamar'] ?? '';
$search_nama = $_GET['search'] ?? '';

// Ambil daftar kelas & kamar unik untuk dropdown filter (dengan sorting numerik)
$stmt_kelas = $conn->prepare("SELECT DISTINCT santri_kelas FROM arsip_data_santri WHERE arsip_id = ? AND santri_kelas IS NOT NULL AND santri_kelas != '' ORDER BY CAST(santri_kelas AS UNSIGNED) ASC");
$stmt_kelas->bind_param('i', $arsip_id);
$stmt_kelas->execute();
$daftar_kelas = $stmt_kelas->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_kamar = $conn->prepare("SELECT DISTINCT santri_kamar FROM arsip_data_santri WHERE arsip_id = ? AND santri_kamar IS NOT NULL AND santri_kamar != '' ORDER BY CAST(santri_kamar AS UNSIGNED) ASC");
$stmt_kamar->bind_param('i', $arsip_id);
$stmt_kamar->execute();
$daftar_kamar = $stmt_kamar->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung total santri di arsip ini (untuk info 'menampilkan X dari Y')
$stmt_total = $conn->prepare("SELECT COUNT(id) as total FROM arsip_data_santri WHERE arsip_id = ?");
$stmt_total->bind_param('i', $arsip_id);
$stmt_total->execute();
$total_santri_arsip = $stmt_total->get_result()->fetch_assoc()['total'];


// Bangun query dinamis berdasarkan filter
$sql = "
    SELECT
        s.santri_nama,
        s.santri_kelas,
        s.santri_kamar,
        COALESCE(SUM(p.poin), 0) AS total_poin_pelanggaran
    FROM
        arsip_data_santri s
    LEFT JOIN
        arsip_data_pelanggaran p ON s.santri_id = p.santri_id AND s.arsip_id = p.arsip_id
";

$conditions = ['s.arsip_id = ?'];
$params = [$arsip_id];
$types = 'i';

if (!empty($filter_kelas)) {
    $conditions[] = 's.santri_kelas = ?';
    $params[] = $filter_kelas;
    $types .= 's';
}
if (!empty($filter_kamar)) {
    $conditions[] = 's.santri_kamar = ?';
    $params[] = $filter_kamar;
    $types .= 's';
}
if (!empty($search_nama)) {
    $conditions[] = 's.santri_nama LIKE ?';
    $params[] = "%" . $search_nama . "%";
    $types .= 's';
}

$sql .= " WHERE " . implode(' AND ', $conditions);
$sql .= " GROUP BY s.id, s.santri_nama, s.santri_kelas, s.santri_kamar ";
// Urutkan secara numerik untuk kelas dan kamar, lalu nama dan poin
$sql .= " ORDER BY CAST(s.santri_kelas AS UNSIGNED) ASC, CAST(s.santri_kamar AS UNSIGNED) ASC, s.santri_nama ASC, total_poin_pelanggaran DESC";

$stmt_santri = $conn->prepare($sql);
if ($stmt_santri) {
    $stmt_santri->bind_param($types, ...$params);
    $stmt_santri->execute();
    $data_santri = $stmt_santri->get_result();
    $jumlah_hasil = $data_santri->num_rows;
} else {
    // Fallback jika prepare statement gagal
    $data_santri = false;
    $jumlah_hasil = 0;
}

$is_filtered = !empty($filter_kelas) || !empty($filter_kamar) || !empty($search_nama);
?>

<style>
    :root {
        --primary-color: #4f46e5;
        --border-color: #e5e7eb;
        --card-bg: #ffffff;
        --text-dark: #1f2937;
        --text-light: #6b7280;
        --danger-bg: #fee2e2;
        --danger-text: #ef4444;
        --body-bg: #f9fafb;
    }
    body {
        background-color: var(--body-bg);
    }
    .page-header {
        background-color: var(--card-bg);
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border-color);
    }
    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-dark);
    }
    .page-header .text-muted {
        color: var(--text-light) !important;
        font-size: 0.95rem;
    }
    .card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .filter-card .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-dark);
    }
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-light);
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .table td {
        vertical-align: middle;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .poin-badge {
        display: inline-block;
        padding: 0.35em 0.8em;
        font-size: 0.85em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 50rem;
        color: var(--danger-text);
        background-color: var(--danger-bg);
    }
    .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover { background-color: #4338ca; border-color: #4338ca; }
</style>

<div class="container mt-4 mb-5">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="mb-1">Rekap Poin Santri Arsip: <?= htmlspecialchars($meta['judul']) ?></h2>
            <p class="text-muted mb-0">
                <?php if ($is_filtered): ?>
                    Menampilkan <strong><?= $jumlah_hasil ?> dari <?= $total_santri_arsip ?></strong> total santri.
                <?php else: ?>
                    Total <strong><?= $total_santri_arsip ?> santri</strong> terekam dalam arsip ini.
                <?php endif; ?>
            </p>
        </div>
        <a href="view.php?id=<?= $arsip_id ?>" class="btn btn-outline-secondary mt-2 mt-md-0 flex-shrink-0">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 filter-card">
        <div class="card-body">
            <form action="" method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="id" value="<?= $arsip_id ?>">
                <div class="col-lg-3 col-md-6">
                    <label for="kelas" class="form-label">Filter Kelas</label>
                    <select name="kelas" id="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= htmlspecialchars($k['santri_kelas']) ?>" <?= ($filter_kelas == $k['santri_kelas']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['santri_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="kamar" class="form-label">Filter Kamar</label>
                    <select name="kamar" id="kamar" class="form-select">
                        <option value="">Semua Kamar</option>
                         <?php foreach ($daftar_kamar as $km): ?>
                            <option value="<?= htmlspecialchars($km['santri_kamar']) ?>" <?= ($filter_kamar == $km['santri_kamar']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($km['santri_kamar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="search" class="form-label">Cari Nama Santri</label>
                    <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search_nama) ?>" placeholder="Ketik nama...">
                </div>
                <div class="col-lg-3 col-md-6 d-flex mt-3 mt-lg-auto">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                    <a href="arsip_santri.php?id=<?= $arsip_id ?>" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Nama Santri</th>
                            <th class="text-center">Kelas</th>
                            <th class="text-center">Kamar</th>
                            <th class="text-center">Total Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_santri && $data_santri->num_rows > 0): $no = 1; // Penomoran dimulai dari 1 ?>
                            <?php while($row = $data_santri->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= $no++ // Tampilkan nomor lalu increment ?></td>
                                    <td><?= htmlspecialchars($row['santri_nama']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['santri_kelas']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['santri_kamar']) ?></td>
                                    <td class="text-center">
                                        <?php if($row['total_poin_pelanggaran'] > 0): ?>
                                            <span class="poin-badge">
                                                <?= $row['total_poin_pelanggaran'] ?>
                                            </span>
                                        <?php else: ?>
                                            0
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-5 text-muted">
                                <?php if ($is_filtered): ?>
                                    Tidak ada santri yang cocok dengan kriteria filter.
                                <?php else: ?>
                                    Tidak ada data santri di arsip ini.
                                <?php endif; ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>