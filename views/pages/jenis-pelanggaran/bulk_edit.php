<?php
// Layout provided by Controller
?>
<div class="d-flex justify-content-between align-items-center mb-4">
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
        <div class="alert alert-info border-0 rounded-3 d-flex align-items-center" style="background-color: #eff6ff; color: #1e40af;">
            <i class="fas fa-info-circle fs-5 me-3 text-primary"></i>
            <div>
                <strong class="d-block mb-1">Perhatian!</strong>
                <span class="small">Perubahan poin atau kategori akan langsung berlaku, tetapi <strong>tidak akan mengubah skor histori pelanggaran santri di masa lalu</strong>. Hanya pelanggaran baru yang akan menggunakan poin ini.</span>
            </div>
        </div>
    </div>
    <div class="card-body p-4 pt-2">
        <form method="POST" action="<?= BASE_URL ?>/jenis-pelanggaran/bulk-update">
            <input type="hidden" name="ids" value="<?= htmlspecialchars($ids_string) ?>">
            
            <div class="table-responsive border rounded-3 mb-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th style="width: 50px;" class="text-center">NO</th>
                            <th>NAMA PELANGGARAN</th>
                            <th style="width: 150px;">POIN BARU</th>
                            <th style="width: 200px;">KATEGORI BARU</th>
                            <th style="width: 200px;">BAGIAN BARU</th>
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
                                    <?php 
                                    $kategori = ['Ringan', 'Sedang', 'Berat'];
                                    foreach($kategori as $k): 
                                    ?>
                                        <option value="<?= $k ?>" <?= strtolower($jp['kategori']) === strtolower($k) ? 'selected' : '' ?>><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="bagian[<?= $jp['id'] ?>]" class="form-select bg-light border-0 shadow-none" style="border-radius: 8px; cursor: pointer;">
                                    <?php 
                                    $currentBagian = $jp['bagian'] ?? 'Kesantrian';
                                    $daftar = $daftar_bagian;
                                    if (!in_array($currentBagian, $daftar)) {
                                        $daftar[] = $currentBagian;
                                    }
                                    sort($daftar);
                                    foreach($daftar as $b): 
                                    ?>
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

            <div class="d-flex justify-content-end gap-3 mt-2">
                <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-light rounded-pill px-4 fw-medium text-muted" hx-boost="true">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium" style="background-color: #4f46e5; border-color: #4f46e5;">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan Massal
                </button>
            </div>
        </form>
    </div>
</div>
