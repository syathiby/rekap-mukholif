<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('arsip_view'); 
require_once __DIR__ . '/../../layouts/header.php';

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

$search_nama = $_GET['search'] ?? '';

// Bangun query
$sql = "
    SELECT r.*
    FROM arsip_data_rapot r
    WHERE r.arsip_id = ?
";

$conditions = [];
$params = [$arsip_id];
$types = 'i';

if (!empty($search_nama)) {
    $sql .= " AND r.santri_nama LIKE ?";
    $params[] = "%" . $search_nama . "%";
    $types .= 's';
}

$sql .= " ORDER BY CAST(r.santri_kamar AS UNSIGNED) ASC, r.santri_nama ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data_rapot = $stmt->get_result();
    $jumlah_hasil = $data_rapot->num_rows;
} else {
    $data_rapot = false;
    $jumlah_hasil = 0;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="fas fa-file-alt text-primary me-2"></i> Arsip Rapot Kepengasuhan</h4>
            <p class="text-muted mb-0">Arsip: <?= htmlspecialchars($meta['judul']); ?> | Periode: <?= date('d M Y', strtotime($meta['tanggal_mulai'])); ?> - <?= date('d M Y', strtotime($meta['tanggal_selesai'])); ?></p>
        </div>
        <a href="../view.php?id=<?= $arsip_id ?>" class="btn btn-outline-secondary mt-2 mt-md-0 flex-shrink-0">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- Filter & Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <input type="hidden" name="id" value="<?= $arsip_id ?>">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Cari nama santri..." value="<?= htmlspecialchars($search_nama) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter me-2"></i>Filter</button>
                    <?php if (!empty($search_nama)): ?>
                    <a href="arsip_rapot.php?id=<?= $arsip_id ?>" class="btn btn-outline-secondary"><i class="fas fa-sync-alt"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3" style="width: 5%">No</th>
                            <th class="py-3" style="width: 25%">Nama Santri</th>
                            <th class="py-3" style="width: 15%">Kamar</th>
                            <th class="py-3" style="width: 20%">Periode Rapot</th>
                            <th class="py-3 text-center" style="width: 15%">Poin Pelanggaran</th>
                            <th class="py-3 text-center" style="width: 15%">Poin Reward</th>
                            <th class="px-4 py-3 text-center" style="width: 5%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_rapot && $jumlah_hasil > 0): ?>
                            <?php $no = 1; while ($row = $data_rapot->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 text-muted"><?= $no++ ?></td>
                                <td><div class="fw-medium text-dark"><?= htmlspecialchars($row['santri_nama'] ?? 'Santri Dihapus') ?></div></td>
                                <td><span class="badge bg-light text-dark border"><i class="fas fa-door-closed me-1 text-muted"></i> <?= htmlspecialchars($row['santri_kamar'] ?? '-') ?></span></td>
                                <td><?= htmlspecialchars($row['bulan']) ?> <?= htmlspecialchars($row['tahun']) ?></td>
                                <td class="text-center"><span class="badge bg-danger rounded-pill px-3"><?= $row['total_poin_pelanggaran_saat_itu'] ?></span></td>
                                <td class="text-center"><span class="badge bg-success rounded-pill px-3"><?= $row['total_poin_reward_saat_itu'] ?></span></td>
                                <td class="px-4 text-center">
                                    <button class="btn btn-sm btn-light border" title="Lihat Detail (Tidak Tersedia Di Arsip)" disabled><i class="fas fa-eye text-muted"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted"><div class="mb-3"><i class="fas fa-folder-open fa-3x text-light"></i></div><p class="mb-0">Tidak ada data rapot di arsip ini.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
