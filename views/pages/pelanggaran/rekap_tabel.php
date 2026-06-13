
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-table me-2 text-primary"></i> Rekap Pelanggaran <?= htmlspecialchars($bagian) ?>
            </h3>
            <p class="text-muted mb-0">Daftar pelanggaran periode <?= htmlspecialchars($awal) ?> s/d <?= htmlspecialchars($akhir) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/pelanggaran" class="btn btn-outline-secondary shadow-sm text-nowrap rounded-pill" hx-boost="true">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <?php if (\App\Helpers\AuthHelper::hasPermission("pelanggaran_" . strtolower($bagian) . "_input")): ?>
            <a href="<?= BASE_URL ?>/pelanggaran/<?= strtolower($bagian) ?>/create" class="btn btn-primary shadow-sm text-nowrap rounded-pill" hx-boost="true">
                <i class="fas fa-plus me-1"></i> Input Pelanggaran
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light rounded px-4 py-3">
            <form method="GET" action="<?= BASE_URL ?>/pelanggaran/<?= strtolower($bagian) ?>" class="row g-3 align-items-end" hx-boost="true">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">Dari Tanggal</label>
                    <input type="date" name="awal" class="form-control" value="<?= htmlspecialchars($awal) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">Sampai Tanggal</label>
                    <input type="date" name="akhir" class="form-control" value="<?= htmlspecialchars($akhir) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="fas fa-filter me-2"></i> Tampilkan Rekap
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle border-bottom mb-0" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3">TANGGAL</th>
                            <th class="py-3">NAMA SANTRI</th>
                            <th class="py-3">KELAS</th>
                            <th class="py-3">KAMAR</th>
                            <th class="py-3">PELANGGARAN</th>
                            <th class="py-3 text-center">POIN</th>
                            <th class="py-3 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pelanggaran)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                    <p class="mb-0 fs-5">Tidak ada data pelanggaran di periode ini.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pelanggaran as $row): ?>
                                <tr>
                                    <td class="px-4"><?= \App\Helpers\FormatHelper::dateIndo($row['tanggal']) ?> <br> <small class="text-muted"><?= date('H:i', strtotime($row['tanggal'])) ?></small></td>
                                    <td class="fw-bold">
                                        <a href="<?= BASE_URL ?>/pelanggaran/<?= strtolower($bagian) ?>/detail/<?= $row['santri_id'] ?>" class="text-decoration-none text-primary" hx-boost="true">
                                            <?= htmlspecialchars($row['santri_nama']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($row['santri_kelas']) ?></td>
                                    <td><?= htmlspecialchars($row['santri_kamar']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                        <?php if ($row['kategori'] === 'Berat'): ?>
                                            <span class="badge bg-danger ms-1">Berat</span>
                                        <?php elseif ($row['kategori'] === 'Sedang'): ?>
                                            <span class="badge bg-warning ms-1">Sedang</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger fs-6"><?= htmlspecialchars($row['poin']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (\App\Helpers\AuthHelper::hasPermission("pelanggaran_" . strtolower($bagian) . "_input")): ?>
                                        <form action="<?= BASE_URL ?>/pelanggaran/delete/<?= $row['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus pelanggaran ini? Poin santri akan otomatis dikembalikan.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" title="Hapus Data">
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

