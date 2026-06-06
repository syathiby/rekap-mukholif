<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_create');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* CSS Kustom Halaman Jenis Pelanggaran (Create) */
    .form-container .form-control,
    .form-container .form-select {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 0.95rem;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
    }
    .form-container .form-control:focus,
    .form-container .form-select:focus {
        background-color: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
    .form-container .form-label {
        font-weight: 600;
        color: #334155;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    .btn-submit {
        background: #4f46e5;
        border: none;
        padding: 10px 28px;
        font-weight: 600;
        border-radius: 30px;
        transition: all 0.3s;
        color: white;
    }
    .btn-submit:hover {
        background: #4338ca;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        color: white;
    }
    .btn-success-custom {
        background: #10b981;
        border: none;
        padding: 10px 28px;
        font-weight: 600;
        border-radius: 30px;
        transition: all 0.3s;
        color: white;
    }
    .btn-success-custom:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
        color: white;
    }
    .nav-tabs {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        border-bottom: 2px solid #f1f5f9;
        gap: 1.5rem;
        overflow-x: auto;
    }
    .nav-tabs .nav-item {
        margin-bottom: -2px;
    }
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #64748b;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.75rem 0.5rem;
        background: none;
        transition: all 0.2s ease;
    }
    .nav-tabs .nav-link:hover {
        color: #0f172a;
    }
    .nav-tabs .nav-link.active {
        color: #4f46e5;
        border-bottom-color: #4f46e5;
        background: none;
    }
    .instruction-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
    }
    textarea#bulk_data {
        min-height: 220px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        line-height: 1.6;
    }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
    
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Jenis Pelanggaran</h3>
            <p class="text-muted mb-0">Tambahkan klasifikasi jenis pelanggaran baru ke dalam sistem.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm form-container bg-white" style="border-radius: 1rem;">
                <div class="card-body p-4 p-md-5">

                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-4" id="violationTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Satu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab">
                                <i class="fas fa-file-excel me-2"></i>Tambah Banyak (Bulk)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="violationTabContent">

                        <!-- === Tambah Satu === -->
                        <div class="tab-pane fade show active" id="single" role="tabpanel">
                            <form action="process.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="mb-4">
                                    <label for="nama_pelanggaran" class="form-label">Nama Pelanggaran <span class="text-danger">*</span></label>
                                    <input type="text" id="nama_pelanggaran" name="nama_pelanggaran" class="form-control" placeholder="Cth: Terlambat mengikuti KBM, Tidak memakai sabuk" required>
                                </div>

                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label for="bagian" class="form-label">Bagian <span class="text-danger">*</span></label>
                                        <select id="bagian" name="bagian" class="form-select" required>
                                            <option value="" disabled selected>-- Pilih Bagian --</option>
                                            <option value="Kesantrian">Kesantrian</option>
                                            <option value="Bahasa">Bahasa</option>
                                            <option value="Diniyyah">Diniyyah</option>
                                            <option value="Pengabdian">Pengabdian</option>
                                            <option value="Tahfidz">Tahfidz</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                                        <select id="kategori" name="kategori" class="form-select" required>
                                            <option value="" disabled selected>-- Pilih Kategori --</option>
                                            <option value="Ringan">Ringan</option>
                                            <option value="Sedang">Sedang</option>
                                            <option value="Berat">Berat</option>
                                            <option value="Sangat Berat">Sangat Berat</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="poin" class="form-label">Poin Pelanggaran <span class="text-danger">*</span></label>
                                        <input type="number" id="poin" name="poin" class="form-control" placeholder="Cth: 5" required min="0">
                                    </div>
                                </div>

                                <hr class="my-4 border-light" style="opacity: 0.1;">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php" class="btn btn-light text-secondary px-4 py-2 rounded-pill fw-semibold">Batal</a>
                                    <button type="submit" name="create_single" class="btn btn-submit">
                                        <i class="fas fa-save me-2"></i>Simpan Data
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- === Tambah Banyak === -->
                        <div class="tab-pane fade" id="bulk" role="tabpanel">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="fas fa-info-circle text-primary fs-5"></i>
                                <h6 class="fw-bold mb-0 text-dark">Petunjuk Pengisian Format Bulk</h6>
                            </div>
                            
                            <div class="instruction-box mb-4">
                                <p class="mb-2 small text-secondary">Salin data dari Excel atau ketik manual dengan format berikut (dipisahkan koma):</p>
                                <code class="d-block mb-3 font-monospace p-2 bg-white rounded border text-dark" style="font-size: 0.85rem;">Nama Pelanggaran,Bagian,Poin,Kategori</code>
                                <p class="mb-1 small text-secondary"><strong>Contoh Baris Masukan:</strong></p>
                                <pre class="mb-0 bg-white p-3 rounded border font-monospace text-secondary" style="font-size: 0.8rem; line-height: 1.5;">Terlambat sholat,Pengabdian,5,Ringan
Tidak membawa kitab,Diniyyah,10,Sedang
Merokok di area pondok,Kesantrian,100,Sangat Berat
Tidak masuk halaqoh,Tahfidz,50,Berat</pre>
                            </div>
                            
                            <form action="process.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="mb-4">
                                    <label for="bulk_data" class="form-label">Data Pelanggaran (Satu per baris) <span class="text-danger">*</span></label>
                                    <textarea name="bulk_data" id="bulk_data" class="form-control" rows="8" placeholder="Terlambat sholat,Pengabdian,5,Ringan&#10;Tidak membawa kitab,Diniyyah,10,Sedang" required></textarea>
                                </div>

                                <hr class="my-4 border-light" style="opacity: 0.1;">

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php" class="btn btn-light text-secondary px-4 py-2 rounded-pill fw-semibold">Batal</a>
                                    <button type="submit" name="create_bulk" class="btn btn-success-custom">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Simpan Semua
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>