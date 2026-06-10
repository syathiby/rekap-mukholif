<?php
// Layout provided by Controller
?>
<style>
/* ====== Bulk Edit JP - Responsive Fix ====== */
@media (max-width: 767.98px) {
    .jp-edit-row {
        display: flex;
        flex-direction: column;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .jp-edit-row .jp-name {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }
    .jp-edit-row .jp-current-info {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 0.75rem;
    }
    .jp-edit-row .jp-fields {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.5rem;
    }
    .jp-edit-row .jp-field-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .jp-edit-row .jp-fields .form-control,
    .jp-edit-row .jp-fields .form-select {
        font-size: 0.85rem;
        padding: 0.4rem 0.5rem;
    }
    .jp-desktop-table { display: none !important; }
    .jp-mobile-cards { display: block !important; }
}
@media (min-width: 768px) {
    .jp-desktop-table { display: block !important; }
    .jp-mobile-cards { display: none !important; }
}
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="mb-1 text-dark fw-bold">
            <span class="text-primary me-2"><i class="fas fa-edit"></i></span>
            Edit Massal Jenis Pelanggaran
        </h4>
        <p class="text-muted small mb-0">Perbarui Poin, Kategori, atau Bagian untuk beberapa pelanggaran sekaligus.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" hx-boost="true">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
        <div class="alert alert-info border-0 rounded-3 d-flex align-items-start gap-3" style="background-color: #eff6ff; color: #1e40af;">
            <i class="fas fa-info-circle fs-5 text-primary mt-1 flex-shrink-0"></i>
            <div>
                <strong class="d-block mb-1">Perhatian!</strong>
                <span class="small">Perubahan poin atau kategori akan langsung berlaku, tetapi <strong>tidak akan mengubah skor histori pelanggaran santri di masa lalu</strong>. Hanya pelanggaran baru yang akan menggunakan poin ini.</span>
            </div>
        </div>
    </div>
    <div class="card-body p-3 p-md-4 pt-2">
        <form method="POST" action="<?= BASE_URL ?>/jenis-pelanggaran/bulk-update">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="ids" value="<?= htmlspecialchars($ids_string) ?>">
            
            <!-- ===== DESKTOP TABLE ===== -->
            <div class="jp-desktop-table table-responsive border rounded-3 mb-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th style="width: 50px;" class="text-center">NO</th>
                            <th>NAMA PELANGGARAN</th>
                            <th style="width: 130px;">POIN BARU</th>
                            <th style="width: 180px;">KATEGORI BARU</th>
                            <th style="width: 180px;">BAGIAN BARU</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php $no = 1; foreach($selected_items as $jp): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($jp['nama_pelanggaran']) ?></div>
                                <div class="text-muted small mt-1 d-flex align-items-center gap-2">
                                    <span>Saat ini:</span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill"><?= (int)$jp['poin'] ?> Poin</span>
                                    <span class="text-muted">•</span>
                                    <span><?= htmlspecialchars($jp['kategori']) ?></span>
                                    <span class="text-muted">•</span>
                                    <span><?= htmlspecialchars($jp['bagian']) ?></span>
                                </div>
                            </td>
                            <td>
                                <input type="number" name="poin[<?= $jp['id'] ?>]" class="form-control bg-light border-0 shadow-none fw-bold text-center" value="<?= (int)$jp['poin'] ?>" min="0" style="border-radius: 8px;">
                            </td>
                            <td>
                                <select name="kategori[<?= $jp['id'] ?>]" class="form-select bg-light border-0 shadow-none" style="border-radius: 8px; cursor: pointer;">
                                    <?php foreach(['Ringan', 'Sedang', 'Berat'] as $k): ?>
                                        <option value="<?= $k ?>" <?= strtolower($jp['kategori']) === strtolower($k) ? 'selected' : '' ?>><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="bagian[<?= $jp['id'] ?>]" class="form-select bg-light border-0 shadow-none" style="border-radius: 8px; cursor: pointer;">
                                    <?php
                                    $currentBagian = $jp['bagian'] ?? 'Kesantrian';
                                    $daftar = $daftar_bagian;
                                    if (!in_array($currentBagian, $daftar)) $daftar[] = $currentBagian;
                                    sort($daftar);
                                    foreach($daftar as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>" <?= strtolower($currentBagian) === strtolower($b) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ===== MOBILE CARDS ===== -->
            <div class="jp-mobile-cards mb-4">
                <?php $no = 1; foreach($selected_items as $jp): ?>
                <div class="jp-edit-row">
                    <div class="jp-name"><?= htmlspecialchars($jp['nama_pelanggaran']) ?></div>
                    <div class="jp-current-info">
                        Saat ini: <strong><?= (int)$jp['poin'] ?> Poin</strong> &bull;
                        <?= htmlspecialchars($jp['kategori']) ?> &bull;
                        <?= htmlspecialchars($jp['bagian']) ?>
                    </div>
                    <div class="jp-fields">
                        <div>
                            <div class="jp-field-label">Poin Baru</div>
                            <input type="number" name="poin[<?= $jp['id'] ?>]" class="form-control form-control-sm bg-light border-0 fw-bold text-center" value="<?= (int)$jp['poin'] ?>" min="0" style="border-radius: 8px;">
                        </div>
                        <div>
                            <div class="jp-field-label">Kategori</div>
                            <select name="kategori[<?= $jp['id'] ?>]" class="form-select form-select-sm bg-light border-0" style="border-radius: 8px;">
                                <?php foreach(['Ringan', 'Sedang', 'Berat'] as $k): ?>
                                    <option value="<?= $k ?>" <?= strtolower($jp['kategori']) === strtolower($k) ? 'selected' : '' ?>><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <div class="jp-field-label">Bagian</div>
                            <select name="bagian[<?= $jp['id'] ?>]" class="form-select form-select-sm bg-light border-0" style="border-radius: 8px;">
                                <?php
                                $currentBagian = $jp['bagian'] ?? 'Kesantrian';
                                $daftarM = $daftar_bagian;
                                if (!in_array($currentBagian, $daftarM)) $daftarM[] = $currentBagian;
                                sort($daftarM);
                                foreach($daftarM as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= strtolower($currentBagian) === strtolower($b) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ===== ACTION BUTTONS ===== -->
            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-2 pt-3 border-top">
                <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-light rounded-pill px-4 fw-medium text-muted" hx-boost="true">
                    <i class="fas fa-times me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium" style="background-color: #4f46e5; border-color: #4f46e5;">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan Massal
                </button>
            </div>
        </form>
    </div>
</div>
