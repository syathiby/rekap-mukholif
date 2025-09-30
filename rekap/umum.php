<?php
require_once __DIR__ . '/../header.php';
guard('rekap_view_umum');

// --- LOGIKA PHP ---

// Ambil periode aktif
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? date('Y-m-d', strtotime('-1 year'));

// Ambil filter dari URL
$filter_kamar = $_GET['kamar'] ?? null;
$filter_bagian = $_GET['bagian'] ?? null;
$filter_kategori = $_GET['kategori'] ?? null;
$filter_jp = $_GET['jenis_pelanggaran'] ?? null; // ✅ PERUBAHAN: Ganti filter nama jadi jenis pelanggaran
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date("Y-m-d");

// ✅ PERUBAHAN: Query untuk filter dropdown sekarang jadi dinamis berdasarkan rentang tanggal
$params_for_filters = [$start_date, $end_date];
$types_for_filters = "ss";

// Ambil daftar Bagian yang ADA PELANGGARANNYA di periode ini
$bagian_sql = "SELECT DISTINCT jp.bagian FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian IS NOT NULL AND jp.bagian != '' ORDER BY jp.bagian ASC";
$bagian_stmt = $conn->prepare($bagian_sql);
$bagian_stmt->bind_param($types_for_filters, ...$params_for_filters);
$bagian_stmt->execute();
$bagian_result = $bagian_stmt->get_result();

// Ambil daftar Kategori yang ADA PELANGGARANNYA di periode ini
$kategori_sql = "SELECT DISTINCT jp.kategori FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.kategori IS NOT NULL AND jp.kategori != '' ORDER BY FIELD(jp.kategori, 'Sangat Berat', 'Berat', 'Sedang', 'Ringan')";
$kategori_stmt = $conn->prepare($kategori_sql);
$kategori_stmt->bind_param($types_for_filters, ...$params_for_filters);
$kategori_stmt->execute();
$kategori_result = $kategori_stmt->get_result();

// ✅ TAMBAHAN: Ambil daftar Jenis Pelanggaran yang ADA di periode ini
$jp_sql = "SELECT DISTINCT jp.id, jp.nama_pelanggaran FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? ORDER BY jp.nama_pelanggaran ASC";
$jp_stmt = $conn->prepare($jp_sql);
$jp_stmt->bind_param($types_for_filters, ...$params_for_filters);
$jp_stmt->execute();
$jp_result = $jp_stmt->get_result();

// Ambil daftar Kamar (ini tetap statis karena semua kamar harus muncul)
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");


// --- QUERY UTAMA ---
$sql = "
SELECT 
    s.id, 
    s.nama, 
    s.kelas, 
    s.kamar,
    s.poin_aktif,
    COALESCE(sub.pelanggaran_periode, 0) AS total_pelanggaran_periode,
    COALESCE(sub.poin_periode, 0) AS total_poin_periode
FROM santri s
LEFT JOIN (
    SELECT 
        p.santri_id,
        COUNT(p.id) AS pelanggaran_periode,
        SUM(jp.poin) AS poin_periode
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE DATE(p.tanggal) BETWEEN ? AND ?
      AND p.jenis_pelanggaran_id != 3
";

$params = [$start_date, $end_date];
$types = "ss";

// ✅ PERUBAHAN: Terapkan filter jenis pelanggaran di dalam subquery
if ($filter_jp) {
    $sql .= " AND p.jenis_pelanggaran_id = ?";
    $params[] = $filter_jp;
    $types .= "i";
}
if ($filter_bagian) {
    $sql .= " AND jp.bagian = ?";
    $params[] = $filter_bagian;
    $types .= "s";
}
if ($filter_kategori) {
    $sql .= " AND jp.kategori = ?";
    $params[] = $filter_kategori;
    $types .= "s";
}

$sql .= "
    GROUP BY p.santri_id
) AS sub ON s.id = sub.santri_id
WHERE 1=1
";

// Terapkan filter kamar di query utama
if ($filter_kamar) {
    $sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}

// Logika filter baru yang lebih tegas
// ✅ PERUBAHAN: Logika diperbarui untuk menyertakan filter jenis pelanggaran
if (!empty($filter_bagian) || !empty($filter_kategori) || !empty($filter_jp)) {
    $sql .= " AND sub.pelanggaran_periode > 0";
} else {
    $sql .= " AND (s.poin_aktif > 0 OR sub.pelanggaran_periode > 0)";
}

$sql .= "
ORDER BY s.poin_aktif DESC, total_pelanggaran_periode DESC, s.nama ASC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$query = $stmt->get_result();
?>

<style>
    :root {
        --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
        --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
        --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        --gold: #f59e0b; --silver: #9ca3af; --bronze: #a16207;
    }
    body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
    .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
    .page-title { color: var(--text-dark); font-weight: 700; }
    .kamar-nav a { padding: 0.5rem 1rem; border-radius: 9999px; text-decoration: none; color: var(--secondary); font-weight: 500; transition: all 0.2s; }
    .kamar-nav a:hover { background-color: var(--primary-light); color: var(--primary-dark); }
    .kamar-nav .active { background-color: var(--primary); color: white; }
    .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
    .table tbody td { vertical-align: middle; padding-top: 1rem; padding-bottom: 1rem; }
    .rank-icon { font-size: 1.5rem; }
    .rank-1 .rank-icon { color: var(--gold); }
    .rank-2 .rank-icon { color: var(--silver); }
    .rank-3 .rank-icon { color: var(--bronze); }
    .poin-aktif { font-size: 1.25rem; font-weight: 700; color: var(--primary-dark); }
    .btn-detail { background-color: var(--primary-light); color: var(--primary-dark); font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .btn-detail:hover { background-color: var(--primary-dark); color: white; }
    .periode-stats { font-size: 0.8rem; font-weight: 500; color: var(--text-light); background-color: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 9999px; white-space: nowrap; }
</style>

<div class="container py-4">
    <h1 class="page-title mb-4"><i class="fas fa-list-alt me-3"></i>Rekapitulasi Pelanggaran Umum</h1>

    <!-- ✅ PERUBAHAN: Card Filter dengan layout dan input baru -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label for="start_date" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="end_date" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="bagian" class="form-label">Bagian</label>
                        <select name="bagian" id="bagian" class="form-select">
                            <option value="">Semua</option>
                            <?php while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                            <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= ($filter_bagian == $b['bagian']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['bagian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kategori" class="form-label">Kategori</label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">Semua</option>
                            <?php while ($k = mysqli_fetch_assoc($kategori_result)): ?>
                            <option value="<?= htmlspecialchars($k['kategori']) ?>" <?= ($filter_kategori == $k['kategori']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['kategori']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-8">
                        <label for="jenis_pelanggaran" class="form-label">Cari Jenis Pelanggaran Spesifik</label>
                        <select name="jenis_pelanggaran" id="jenis_pelanggaran" class="form-select">
                            <option value="">-- Semua Pelanggaran di Periode Ini --</option>
                            <?php while ($jp = mysqli_fetch_assoc($jp_result)): ?>
                            <option value="<?= $jp['id'] ?>" <?= ($filter_jp == $jp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($jp['nama_pelanggaran']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Cari</button>
                        <a href="umum.php" class="btn btn-secondary" title="Reset Filter"><i class="fas fa-sync-alt"></i></a>
                    </div>
                </div>
                <?php if ($filter_kamar): ?><input type="hidden" name="kamar" value="<?= htmlspecialchars($filter_kamar) ?>"><?php endif; ?>
            </form>
            
            <hr class="my-4">

            <div class="kamar-nav d-flex flex-wrap gap-2">
                <?php
                    // ✅ PERUBAHAN: Link filter kamar sekarang membawa parameter jenis pelanggaran
                    $base_link_params = "start_date=$start_date&end_date=$end_date"
                                        . "&jenis_pelanggaran=" . urlencode($filter_jp ?? '')
                                        . "&bagian=" . urlencode($filter_bagian ?? '') 
                                        . "&kategori=" . urlencode($filter_kategori ?? '');
                ?>
                <a href="?<?= $base_link_params ?>" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
                <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                    <a href="?kamar=<?= urlencode($k['kamar']) ?>&<?= $base_link_params ?>" class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>"><?= htmlspecialchars($k['kamar']) ?></a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Tabel Hasil -->
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="text-center">Peringkat</th>
                        <th>Santri</th>
                        <th class="text-center">Poin Aktif</th>
                        <th class="text-center">Statistik Periode Ini</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) === 0): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Tidak ada data pelanggaran ditemukan dengan filter ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr class="rank-<?= $no ?>">
                            <td class="text-center">
                                <?php if ($no <= 3): ?><i class="fas fa-trophy rank-icon"></i><?php else: ?><span class="fw-bold fs-5"><?= $no ?></span><?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['nama']) ?></div>
                                <small class="text-muted">Kls: <?= htmlspecialchars($row['kelas']) ?> | Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
                            </td>
                            <td class="text-center"><span class="poin-aktif"><?= $row['poin_aktif'] ?></span></td>
                            <td class="text-center">
                                <?php if($row['total_pelanggaran_periode'] > 0): ?>
                                    <span class="periode-stats">
                                        <?= $row['total_pelanggaran_periode'] ?> Pelanggaran (+<?= $row['total_poin_periode'] ?> Poin)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                    // ✅ PERUBAHAN: Link detail sekarang membawa parameter jenis pelanggaran
                                    $detail_link = "detail.php?id={$row['id']}&start_date=$start_date&end_date=$end_date"
                                                   . "&kamar=" . urlencode($filter_kamar ?? '')
                                                   . "&jenis_pelanggaran=" . urlencode($filter_jp ?? '')
                                                   . "&bagian=" . urlencode($filter_bagian ?? '')
                                                   . "&kategori=" . urlencode($filter_kategori ?? '');
                                ?>
                                <a href="<?= $detail_link ?>" class="btn btn-sm btn-detail rounded-pill px-3"><i class="fas fa-info-circle me-1"></i> Detail</a>
                            </td>
                        </tr>
                        <?php $no++; endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>