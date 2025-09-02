<?php
require_once __DIR__ . '/../header.php';
guard('rekap_view_umum');

// --- LOGIKA PHP (DIRUBAH TOTAL UNTUK SISTEM BARU) ---

// Ambil periode aktif (defaultnya tetap)
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? date('Y-m-d', strtotime('-1 year'));

// Ambil daftar unik untuk filter (tidak ada perubahan)
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");
$bagian_result = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC");
$kategori_result = mysqli_query($conn, "SELECT DISTINCT kategori FROM jenis_pelanggaran WHERE kategori IS NOT NULL AND kategori != '' ORDER BY FIELD(kategori, 'Sangat Berat', 'Berat', 'Sedang', 'Ringan')");

// Ambil filter dari URL (tidak ada perubahan)
$filter_kamar = $_GET['kamar'] ?? null;
$filter_bagian = $_GET['bagian'] ?? null;
$filter_kategori = $_GET['kategori'] ?? null;
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date("Y-m-d");

// --- QUERY UTAMA (DIRUBAH TOTAL) ---
// Tujuannya:
// 1. Tampilkan SEMUA santri.
// 2. Tampilkan `poin_aktif` mereka sebagai skor utama.
// 3. Hitung total pelanggaran & poin HANYA di rentang waktu yang difilter.
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

// Terapkan filter bagian & kategori di dalam subquery
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

// --- BAGIAN INI YANG KITA UBAH ---
// Logika filter baru yang lebih tegas
// Jika ada filter 'bagian' atau 'kategori', HANYA tampilkan santri yang benar-benar punya pelanggaran sesuai filter di periode itu.
if (!empty($filter_bagian) || !empty($filter_kategori)) {
    $sql .= " AND sub.pelanggaran_periode > 0";
} else {
    // Jika tidak ada filter, baru kita pakai logika lama: tampilkan yang punya poin aktif ATAU pelanggaran di periode ini.
    $sql .= " AND (s.poin_aktif > 0 OR sub.pelanggaran_periode > 0)";
}

$sql .= "
ORDER BY s.poin_aktif DESC, total_poin_periode DESC, s.nama ASC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$query = $stmt->get_result();
?>

<!-- Style tidak diubah, tetap pakai style asli dari lu -->
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
    
    /* === TAMBAHKAN INI === */
    .table tbody td {
        vertical-align: middle; /* Kunci utamanya: bikin konten jadi di tengah */
        padding-top: 1rem;      /* Kasih ruang napas di atas */
        padding-bottom: 1rem;   /* Kasih ruang napas di bawah */
    }
    /* === BATAS TAMBAHAN === */

    .rank-icon { font-size: 1.5rem; }
    .rank-1 .rank-icon { color: var(--gold); }
    .rank-2 .rank-icon { color: var(--silver); }
    .rank-3 .rank-icon { color: var(--bronze); }
    .poin-aktif { font-size: 1.25rem; font-weight: 700; color: var(--primary-dark); }
    .btn-detail { background-color: var(--primary-light); color: var(--primary-dark); font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .btn-detail:hover { background-color: var(--primary-dark); color: white; }
    .periode-stats {
        font-size: 0.8rem; font-weight: 500; color: var(--text-light);
        background-color: #f1f5f9; padding: 0.2rem 0.6rem;
        border-radius: 9999px; white-space: nowrap;
    }
</style>

<div class="container py-4">
    <h1 class="page-title mb-4"><i class="fas fa-list-alt me-3"></i>Rekapitulasi Pelanggaran Umum</h1>

    <!-- Card Filter (Tidak ada perubahan di HTML) -->
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
                    <div class="col-lg-2 col-md-4">
                        <label for="bagian" class="form-label">Bagian</label>
                        <select name="bagian" id="bagian" class="form-select">
                            <option value="">Semua</option>
                            <?php mysqli_data_seek($bagian_result, 0); while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                            <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= ($filter_bagian == $b['bagian']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['bagian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="kategori" class="form-label">Kategori</label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">Semua</option>
                            <?php mysqli_data_seek($kategori_result, 0); while ($k = mysqli_fetch_assoc($kategori_result)): ?>
                            <option value="<?= htmlspecialchars($k['kategori']) ?>" <?= ($filter_kategori == $k['kategori']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['kategori']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Cari</button>
                        <a href="umum.php" class="btn btn-secondary" title="Reset Filter"><i class="fas fa-sync-alt"></i></a>
                    </div>
                </div>
                <?php if ($filter_kamar): ?><input type="hidden" name="kamar" value="<?= htmlspecialchars($filter_kamar) ?>"><?php endif; ?>
            </form>
            
            <hr class="my-4">

            <div class="kamar-nav d-flex flex-wrap gap-2">
                <?php
                    $base_link_params = "start_date=$start_date&end_date=$end_date&bagian=" . urlencode($filter_bagian ?? '') . "&kategori=" . urlencode($filter_kategori ?? '');
                ?>
                <a href="?<?= $base_link_params ?>" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
                <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                    <a href="?kamar=<?= urlencode($k['kamar']) ?>&<?= $base_link_params ?>" class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>"><?= htmlspecialchars($k['kamar']) ?></a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Tabel Hasil (DIRUBAH UNTUK MENAMPILKAN DATA BARU) -->
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
                                    $detail_link = "detail.php?id={$row['id']}&start_date=$start_date&end_date=$end_date"
                                                 . "&kamar=" . urlencode($filter_kamar ?? '')
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