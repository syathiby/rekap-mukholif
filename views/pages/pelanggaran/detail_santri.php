
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-user-clock me-2 text-primary"></i> Detail Pelanggaran <?= htmlspecialchars($bagian) ?>
            </h3>
            <p class="text-muted mb-0">Riwayat pelanggaran atas nama <strong><?= htmlspecialchars($santri['nama']) ?></strong></p>
        </div>
        <a href="<?= BASE_URL ?>/pelanggaran/<?= strtolower($bagian) ?>" class="btn btn-outline-secondary rounded-pill" hx-boost="true">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Rekap
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="row g-4 mb-4">
        <!-- Profile Card -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-user fa-3x"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($santri['nama']) ?></h4>
                    <p class="text-muted mb-3"><?= htmlspecialchars($santri['nis']) ?></p>
                    
                    <ul class="list-group list-group-flush text-start">
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Kelas</span>
                            <span class="fw-semibold"><?= htmlspecialchars($santri['kelas']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Kamar</span>
                            <span class="fw-semibold"><?= htmlspecialchars($santri['kamar']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Status</span>
                            <?php if ($santri['status'] === 'Aktif'): ?>
                                <span class="badge bg-success rounded-pill px-3">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill px-3"><?= htmlspecialchars($santri['status']) ?></span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between border-bottom-0 pb-0">
                            <span class="text-muted">Total Poin Saat Ini</span>
                            <span class="badge bg-danger rounded-pill px-3 fs-6"><?= htmlspecialchars($santri['poin_aktif']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="col-12 col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title fw-bold mb-0">Riwayat Kejadian</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3">TANGGAL</th>
                                    <th class="py-3">PELANGGARAN</th>
                                    <th class="py-3 text-center">POIN</th>
                                    <th class="py-3 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pelanggaran)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                            <p class="mb-0 fs-5">Santri ini bersih dari pelanggaran <?= htmlspecialchars($bagian) ?>.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pelanggaran as $row): ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="fw-bold text-dark"><?= \App\Helpers\FormatHelper::dateIndo($row['tanggal']) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($row['tanggal'])) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                                <?php if ($row['kategori'] === 'Berat'): ?>
                                                    <span class="badge bg-danger ms-1">Berat</span>
                                                <?php elseif ($row['kategori'] === 'Sedang'): ?>
                                                    <span class="badge bg-warning ms-1">Sedang</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">+<?= htmlspecialchars($row['poin']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (\App\Helpers\AuthHelper::hasPermission("pelanggaran_" . strtolower($bagian) . "_input")): ?>
                                                <form action="<?= BASE_URL ?>/pelanggaran/delete/<?= $row['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus riwayat ini? Poin akan otomatis dikurangi dari total poin santri.');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" title="Hapus Riwayat">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
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
    </div>
</div>

