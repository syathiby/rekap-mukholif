<?php
// Layout is already provided by Controller
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold">
            <span class="text-primary me-2"><i class="fas <?= isset($jp) ? 'fa-edit' : 'fa-plus-circle' ?>"></i></span>
            <?= htmlspecialchars($title) ?>
        </h4>
        <p class="text-muted small mb-0">Manajemen rincian data pelanggaran santri.</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" hx-boost="true">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 col-md-10">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 px-md-5">
                <div class="alert alert-info border-0 rounded-3 d-flex align-items-center" style="background-color: #f0fdf4; color: #166534;">
                    <i class="fas fa-info-circle fs-5 me-3 text-success"></i>
                    <div>
                        <strong class="d-block mb-1">Informasi Form</strong>
                        <span class="small">Pastikan pengisian <b>Poin</b> dan <b>Kategori</b> sesuai dengan pedoman kedisiplinan yang berlaku saat ini.</span>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4 p-md-5 pt-3">
                <form method="POST" action="<?= BASE_URL ?><?= $action ?>" class="needs-validation" novalidate>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark mb-2">
                            Nama Pelanggaran <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_pelanggaran" class="form-control form-control-lg bg-light border-0 shadow-none px-3" 
                               value="<?= htmlspecialchars($jp['nama_pelanggaran'] ?? '') ?>" 
                               placeholder="Contoh: Terlambat Sholat Berjamaah" required autofocus
                               style="border-radius: 10px;">
                        <div class="invalid-feedback">Nama pelanggaran wajib diisi.</div>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-2">
                                Poin Hukuman <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-0 text-warning" style="border-radius: 10px 0 0 10px;">
                                    <i class="fas fa-star"></i>
                                </span>
                                <input type="number" name="poin" class="form-control bg-light border-0 shadow-none fw-bold" 
                                       value="<?= (int)($jp['poin'] ?? 0) ?>" 
                                       min="0" required
                                       style="border-radius: 0 10px 10px 0;">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-2">Kategori</label>
                            <select name="kategori" class="form-select form-select-lg bg-light border-0 shadow-none" style="border-radius: 10px; cursor: pointer;">
                                <?php 
                                $kategori = ['Ringan', 'Sedang', 'Berat'];
                                $currentKat = $jp['kategori'] ?? 'Ringan';
                                foreach($kategori as $k): 
                                ?>
                                    <option value="<?= $k ?>" <?= strtolower($currentKat) === strtolower($k) ? 'selected' : '' ?>><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark mb-2">Bagian / Divisi</label>
                            <select name="bagian" class="form-select form-select-lg bg-light border-0 shadow-none" style="border-radius: 10px; cursor: pointer;">
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
                        </div>
                    </div>

                    <hr class="border-light my-4">
                    
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-light rounded-pill px-4 fw-medium text-muted" hx-boost="true">
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium" style="background-color: #4f46e5; border-color: #4f46e5;">
                            <i class="fas fa-save me-1"></i> Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>
