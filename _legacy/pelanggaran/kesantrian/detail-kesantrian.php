<?php
// 1. Init & Guard
require_once __DIR__ . '/../../bootstrap/init.php';
guard('rekap_view_kesantrian');
require_once __DIR__ . '/../../layouts/header.php';

// 2. Tangkap Parameter
$santri_id = $_GET['santri_id'] ?? null;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

if (!$santri_id) {
    die("<script>alert('Data santri tidak ditemukan'); window.history.back();</script>");
}

// 3. Query Profil Santri
$stmt_santri = $conn->prepare("SELECT * FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();

// 4. Query Riwayat Pelanggaran (Kesantrian)
$stmt_history = $conn->prepare("
    SELECT p.*, jp.nama_pelanggaran, jp.poin, u.nama_lengkap as pencatat
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    LEFT JOIN users u ON p.dicatat_oleh = u.id
    WHERE p.santri_id = ? 
      AND jp.bagian = 'Kesantrian'
      AND DATE(p.tanggal) BETWEEN ? AND ?
    ORDER BY p.tanggal DESC
");
$stmt_history->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_history->execute();
$history = $stmt_history->get_result();

// Hitung Total Poin Periode Ini
$total_poin = 0;
$list_pelanggaran = [];
while ($row = $history->fetch_assoc()) {
    $total_poin += $row['poin'];
    $list_pelanggaran[] = $row;
}
?>

<div class="container my-4">
    
    <!-- Tombol Kembali -->
    <a href="rekap.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-white text-primary fw-bold mb-4 shadow-sm border">
        <i class="fas fa-arrow-left me-2"></i>Kembali ke Rekap
    </a>

    <!-- Profil Santri -->
    <div class="pro-card p-4 mb-4 d-flex flex-wrap align-items-center gap-4">
        <div class="icon-circle bg-primary bg-opacity-10 text-primary" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 2.5rem;">
            <i class="fas fa-user"></i>
        </div>
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($santri['nama']) ?></h3>
            <p class="mb-0 text-secondary">Kelas <?= htmlspecialchars($santri['kelas']) ?> • Kamar <?= htmlspecialchars($santri['kamar']) ?></p>
        </div>
        <div class="d-flex gap-3">
            <div class="text-center p-3 rounded-3 bg-light border">
                <span class="d-block small text-secondary text-uppercase fw-bold" style="letter-spacing: 0.5px;">Total Pelanggaran</span>
                <span class="fw-bold fs-3 text-dark"><?= count($list_pelanggaran) ?></span>
            </div>
            <div class="text-center p-3 rounded-3 bg-light border">
                <span class="d-block small text-secondary text-uppercase fw-bold" style="letter-spacing: 0.5px;">Total Poin</span>
                <span class="fw-bold fs-3 text-warning"><?= $total_poin ?></span>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="pro-card mb-5 p-0 overflow-hidden">
        <div class="p-4 border-bottom bg-light">
            <h6 class="fw-bold text-dark mb-0 text-uppercase" style="letter-spacing: 0.5px;">
                <i class="fas fa-history text-primary me-2"></i> Riwayat Pelanggaran 
                <span class="fw-normal text-secondary ms-2">(<?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>)</span>
            </h6>
        </div>
            
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-secondary bg-white" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <tr>
                        <th class="py-3 ps-4">Tanggal</th>
                        <th class="py-3">Jenis Pelanggaran</th>
                        <th class="text-center py-3">Poin</th>
                        <th class="py-3 pe-4">Dicatat Oleh</th>
                    </tr>
                </thead>
                    <tbody>
                        <?php if (empty($list_pelanggaran)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Tidak ada data pelanggaran.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list_pelanggaran as $row): ?>
                            <tr>
                                <td class="text-muted fw-bold py-3 ps-4">
                                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_pelanggaran']) ?></div>
                                </td>
                                <td class="text-center py-3">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3">+<?= $row['poin'] ?></span>
                                </td>
                                <td class="small text-muted py-3 pe-4">
                                    <i class="fas fa-user-edit me-1"></i> <?= htmlspecialchars($row['pencatat'] ?? 'Sistem') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>