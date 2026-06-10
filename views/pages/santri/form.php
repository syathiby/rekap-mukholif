<?php
$isEdit = isset($santri) && !empty($santri);
$action = $isEdit ? BASE_URL . '/santri/' . $santri['id'] : BASE_URL . '/santri';
$title = $isEdit ? 'Edit Data Santri' : 'Tambah Santri Baru';
$desc = $isEdit ? 'Perbarui informasi santri ini.' : 'Lengkapi informasi di bawah untuk pendaftaran santri.';
$icon = $isEdit ? 'fa-user-edit' : 'fa-user-plus';
$iconBg = $isEdit ? 'bg-warning bg-opacity-10 text-warning' : 'bg-primary bg-opacity-10 text-primary';

$nama = $isEdit ? $santri['nama'] : '';
$kelas = $isEdit ? $santri['kelas'] : '';
$kamar = $isEdit ? $santri['kamar'] : '';

$csrf_token = csrf_generate();
?>

<div>
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0" style="border-radius: 1rem;">
                <div class="card-header bg-white border-bottom p-4" style="border-radius: 1rem 1rem 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="<?= $iconBg ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                <i class="fas <?= $icon ?> fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold text-dark"><?= $title ?></h5>
                                <p class="mb-0 text-muted small"><?= $desc ?></p>
                            </div>
                        </div>
                        <?php if($isEdit): ?>
                            <span class="badge bg-light text-secondary border">ID: <?= $santri['id'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <form method="post" action="<?= $action ?>" id="santriForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-4">
                            <label for="nama" class="form-label fw-medium text-secondary">Nama Lengkap</label>
                            <div class="input-group-modern">
                                <i class="fas fa-user"></i>
                                <input type="text" id="nama" name="nama" class="form-control input-modern" 
                                       placeholder="Masukkan nama lengkap santri" required
                                       minlength="3" maxlength="100" value="<?= htmlspecialchars((string)$nama) ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kelas" class="form-label fw-medium text-secondary">Kelas</label>
                            <div class="input-group-modern">
                                <i class="fas fa-graduation-cap"></i>
                                <input type="number" id="kelas" name="kelas" class="form-control input-modern" 
                                       placeholder="Contoh: 7 atau 8" required min="1" value="<?= htmlspecialchars((string)$kelas) ?>">
                            </div>
                            <div class="form-text small text-muted mt-2">Cukup masukkan angkanya saja (misal: 7, 8, 9).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kamar" class="form-label fw-medium text-secondary">Nomor Kamar</label>
                            <div class="input-group-modern">
                                <i class="fas fa-door-open"></i>
                                <input type="number" id="kamar" name="kamar" class="form-control input-modern" 
                                       placeholder="Contoh: 6 atau 12" required min="1" value="<?= htmlspecialchars((string)$kamar) ?>">
                            </div>
                            <div class="form-text small text-muted mt-2">Boleh diisi 1 digit (6) atau 2 digit (06).</div>
                        </div>
                        
                        <hr class="my-4 border-light">

                        <div class="d-flex justify-content-between gap-2">
                            <a href="<?= BASE_URL ?>/santri" class="btn btn-light fw-medium px-4">
                                <i class="fas <?= $isEdit ? 'fa-arrow-left' : 'fa-times' ?> me-2"></i><?= $isEdit ? 'Kembali' : 'Batal' ?>
                            </a>
                            <button type="submit" class="btn <?= $isEdit ? 'btn-warning text-dark' : 'btn-success' ?> fw-medium px-4">
                                <i class="fas fa-save me-2"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Data' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
