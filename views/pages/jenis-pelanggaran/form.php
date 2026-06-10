<?php
// Layout is already provided by Controller
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
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

<!-- Tabs (Only for Create, not Edit) -->
<?php if(!isset($jp)): ?>
<ul class="nav nav-pills nav-fill flex-column flex-sm-row mb-4 gap-2" id="pills-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active rounded-pill fw-semibold w-100" id="pills-single-tab" data-bs-toggle="pill" data-bs-target="#pills-single" type="button" role="tab" aria-selected="true">
        <i class="fas fa-plus-circle me-1"></i> Tambah Satu
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link rounded-pill fw-semibold w-100" id="pills-bulk-tab" data-bs-toggle="pill" data-bs-target="#pills-bulk" type="button" role="tab" aria-selected="false">
        <i class="fas fa-file-excel me-1"></i> Tambah Banyak (Bulk)
    </button>
  </li>
</ul>
<?php endif; ?>

<div class="row">
    <!-- Menggunakan col-12 agar form membentang penuh dan terasa lebih "lega" dan balance -->
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
            
            <div class="tab-content" id="pills-tabContent">
                
                <!-- Tab Tambah Satu -->
                <div class="tab-pane fade show active" id="pills-single" role="tabpanel" aria-labelledby="pills-single-tab">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 px-md-5">
                        <div class="alert alert-info border-0 rounded-3 d-flex align-items-center mb-0" style="background-color: #f0fdf4; color: #166534;">
                            <i class="fas fa-info-circle fs-5 me-3 text-success"></i>
                            <div>
                                <strong class="d-block mb-1">Informasi Form</strong>
                                <span class="small">Pastikan pengisian <b>Poin</b> dan <b>Kategori</b> sesuai dengan pedoman kedisiplinan yang berlaku saat ini.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 p-md-5 pt-4">
                        <form method="POST" action="<?= BASE_URL ?><?= $action ?>" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark mb-2">
                                    Nama Pelanggaran <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama_pelanggaran" class="form-control form-control-lg bg-light border-0 shadow-none px-4" 
                                       value="<?= htmlspecialchars($jp['nama_pelanggaran'] ?? '') ?>" 
                                       placeholder="Contoh: Terlambat Sholat Berjamaah" required autofocus
                                       style="border-radius: 12px;">
                                <div class="invalid-feedback">Nama pelanggaran wajib diisi.</div>
                            </div>
                            
                            <div class="row g-4 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark mb-2">
                                        Poin Hukuman <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light border-0 text-warning px-3" style="border-radius: 12px 0 0 12px;">
                                            <i class="fas fa-star"></i>
                                        </span>
                                        <input type="number" name="poin" class="form-control bg-light border-0 shadow-none fw-bold" 
                                               value="<?= (int)($jp['poin'] ?? 0) ?>" 
                                               min="0" required
                                               style="border-radius: 0 12px 12px 0;">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark mb-2">Kategori</label>
                                    <select name="kategori" class="form-select form-select-lg bg-light border-0 shadow-none px-4" style="border-radius: 12px; cursor: pointer;">
                                        <?php 
                                        $kategori = ['Ringan', 'Sedang', 'Berat', 'Sangat Berat'];
                                        $currentKat = $jp['kategori'] ?? 'Ringan';
                                        foreach($kategori as $k): 
                                        ?>
                                            <option value="<?= $k ?>" <?= strtolower($currentKat) === strtolower($k) ? 'selected' : '' ?>><?= $k ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark mb-2">Bagian / Divisi</label>
                                    <select name="bagian" class="form-select form-select-lg bg-light border-0 shadow-none px-4" style="border-radius: 12px; cursor: pointer;">
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
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-medium" style="background-color: #4f46e5; border-color: #4f46e5;">
                                    <i class="fas fa-save me-1"></i> Simpan Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab Tambah Banyak (Hanya muncul jika bukan edit mode) -->
                <?php if(!isset($jp)): ?>
                <div class="tab-pane fade" id="pills-bulk" role="tabpanel" aria-labelledby="pills-bulk-tab">
                    <div class="card-body p-4 p-md-5">
                        
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-info-circle text-primary fs-5"></i>
                            <h6 class="fw-bold mb-0 text-dark">Petunjuk Pengisian Format Bulk</h6>
                        </div>
                        
                        <div class="p-4 rounded-3 mb-4" style="background-color: #f8fafc; border: 1px dashed #cbd5e1;">
                            <p class="mb-2 small text-secondary">Salin data dari Excel atau ketik manual dengan format koma (CSV) per baris:</p>
                            <code class="d-block mb-3 font-monospace p-2 bg-white rounded border text-dark" style="font-size: 0.85rem;">Nama Pelanggaran, Bagian, Poin, Kategori</code>
                            <p class="mb-1 small text-secondary"><strong>Contoh Baris Masukan:</strong></p>
                            <pre class="mb-0 bg-white p-3 rounded border font-monospace text-secondary" style="font-size: 0.8rem; line-height: 1.6;">Terlambat sholat,Kesantrian,5,Ringan
Tidak membawa kitab,Diniyyah,10,Sedang
Merokok di area pondok,Kesantrian,100,Sangat Berat
Tidak masuk halaqoh,Tahfidz,50,Berat</pre>
                        </div>
                        
                        <form method="POST" action="<?= BASE_URL ?>/jenis-pelanggaran/bulk-store" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark mb-2">
                                    Data Pelanggaran (Satu per baris) <span class="text-danger">*</span>
                                </label>
                                <textarea name="bulk_data" class="form-control bg-light border-0 shadow-none p-4 font-monospace" rows="8" 
                                          placeholder="Terlambat sholat,Kesantrian,5,Ringan&#10;Tidak membawa kitab,Diniyyah,10,Sedang" 
                                          required style="border-radius: 12px; font-size: 0.9rem; line-height: 1.6;"></textarea>
                                <div class="invalid-feedback">Teks bulk tidak boleh kosong.</div>
                            </div>

                            <hr class="border-light my-4">

                            <div class="d-flex justify-content-end gap-3">
                                <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-light rounded-pill px-4 fw-medium text-muted" hx-boost="true">
                                    Batal
                                </a>
                                <button type="submit" name="create_bulk" class="btn btn-success rounded-pill px-4 py-2 shadow-sm fw-medium" style="background-color: #10b981; border-color: #10b981;">
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Simpan Semua (Bulk)
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
// Validasi form Bootstrap bawaan
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
