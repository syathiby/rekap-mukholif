<?php
// 1. Init & Guard
require_once __DIR__ . '/../../init.php';
guard('rekap_view_kesantrian');
require_once __DIR__ . '/../../header.php';

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

<style>
    /* Soft Blue Theme */
    :root { --theme-primary: #3b82f6; --theme-soft: #eff6ff; --theme-dark: #1e3a8a; }
    
    .profile-card {
        background: linear-gradient(135deg, var(--theme-primary), var(--theme-dark));
        color: white; border-radius: 16px; padding: 2rem;
        box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.3);
    }
    .avatar-circle {
        width: 80px; height: 80px; background: rgba(255,255,255,0.2);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; border: 3px solid rgba(255,255,255,0.5);
    }
    .stat-box {
        background: rgba(255,255,255,0.1); border-radius: 12px; padding: 10px 20px;
        text-align: center; border: 1px solid rgba(255,255,255,0.2);
    }
    .table-custom thead th { background-color: var(--theme-soft); color: var(--theme-dark); border: none; }
    .table-custom tbody tr:hover { background-color: #f8fafc; }
</style>

<div class="container my-4">
    
    <!-- Tombol Kembali -->
    <a href="rekap.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-light text-primary fw-bold mb-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Kembali ke Rekap
    </a>

    <!-- Profil Santri -->
    <div class="profile-card mb-4 d-flex flex-wrap align-items-center gap-4">
        <div class="avatar-circle">
            <i class="fas fa-user"></i>
        </div>
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-1"><?= htmlspecialchars($santri['nama']) ?></h3>
            <p class="mb-0 opacity-75">Kelas <?= $santri['kelas'] ?> • Kamar <?= $santri['kamar'] ?></p>
        </div>
        <div class="d-flex gap-3">
            <div class="stat-box">
                <span class="d-block small opacity-75">Total Pelanggaran</span>
                <span class="fw-bold fs-4"><?= count($list_pelanggaran) ?></span>
            </div>
            <div class="stat-box">
                <span class="d-block small opacity-75">Total Poin</span>
                <span class="fw-bold fs-4 text-warning"><?= $total_poin ?></span>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-4 border-start border-4 border-primary ps-3">
                Riwayat Pelanggaran (<?= date('d M', strtotime($start_date)) ?> - <?= date('d M', strtotime($end_date)) ?>)
            </h5>
            
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Pelanggaran</th>
                            <th class="text-center">Poin</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_pelanggaran)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Tidak ada data pelanggaran.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list_pelanggaran as $row): ?>
                            <tr>
                                <td class="text-muted fw-bold">
                                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_pelanggaran']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger rounded-pill px-3">+<?= $row['poin'] ?></span>
                                </td>
                                <td class="small text-muted">
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

<?php require_once __DIR__ . '/../../footer.php'; ?>