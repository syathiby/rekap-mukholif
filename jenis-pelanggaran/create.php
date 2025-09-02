<?php 
require_once __DIR__ . '/../header.php';
guard('jenis_pelanggaran_manage'); 
?>

<style>
    /* Style lu udah keren, jadi gw biarin aja */
    :root {
        --primary-color: #4361ee; --secondary-color: #3f37c9;
        --accent-color: #4895ef; --light-color: #f8f9fa;
        --dark-color: #212529; --success-color: #4cc9f0;
        --danger-color: #f72585; --border-radius: 8px;
        --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .card-header-tabs { margin-bottom: -1rem; }
    .nav-tabs .nav-link { border-bottom-width: 1px; cursor: pointer; }
    .nav-tabs .nav-link.active { 
        background-color: #fff; 
        border-color: #dee2e6 #dee2e6 #fff;
        color: var(--primary-color);
        font-weight: 600;
    }
    textarea#bulk_data {
        min-height: 250px;
        font-family: monospace;
        font-size: 0.9rem;
    }
    .alert-info {
        background-color: #e7f3fe;
        border-color: #d0e7fd;
        color: #0c5460;
    }
</style>

<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <a href="index.php" class="btn btn-sm btn-outline-secondary float-end mt-1">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
                        <i class="fas fa-plus-circle me-1"></i> Tambah Satu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk" aria-selected="false">
                        <i class="fas fa-file-excel me-1"></i> Tambah Banyak (Bulk)
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content pt-3" id="myTabContent">
                
                <!-- TAB 1: FORM TAMBAH SATU (Sudah Benar) -->
                <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">
                    <h5 class="mb-3">Form Tambah Jenis Pelanggaran</h5>
                    <form action="process.php" method="POST">
                        
                        <div class="mb-3">
                            <label for="nama_pelanggaran" class="form-label">Nama Pelanggaran</label>
                            <input type="text" id="nama_pelanggaran" name="nama_pelanggaran" class="form-control" placeholder="Cth: Terlambat mengikuti KBM" required>
                        </div>

                        <div class="mb-3">
                            <label for="bagian" class="form-label">Bagian</label>
                            <select id="bagian" name="bagian" class="form-control" required>
                                <option value="" disabled selected>-- Pilih Bagian --</option>
                                <option value="Kesantrian">Kesantrian</option>
                                <option value="Bahasa">Bahasa</option>
                                <option value="Diniyyah">Diniyyah</option>
                                <option value="Pengabdian">Pengabdian</option>
                            </select>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label for="poin" class="form-label">Poin</label>
                                <input type="number" id="poin" name="poin" class="form-control" placeholder="Cth: 5" required min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select id="kategori" name="kategori" class="form-control" required>
                                    <option value="" disabled selected>-- Pilih Kategori --</option>
                                    <option value="Ringan">Ringan</option>
                                    <option value="Sedang">Sedang</option>
                                    <option value="Berat">Berat</option>
                                    <option value="Sangat Berat">Sangat Berat</option>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <button type="submit" name="create_single" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> Simpan Data
                        </button>
                    </form>
                </div>

                <!-- ======================================================= -->
                <!-- === TAB 2: FORM BULK CREATE (YANG KEMARIN KEHAPUS) === -->
                <!-- ======================================================= -->
                <div class="tab-pane fade" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
                    <h5 class="mb-3">Form Tambah Banyak Pelanggaran</h5>
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Petunjuk Format</h6>
                        <p class="mb-1">Salin data dari Excel atau ketik manual dengan format berikut (dipisahkan koma):</p>
                        <code>Nama Pelanggaran,Bagian,Poin,Kategori</code>
                        <hr class="my-2">
                        <p class="mb-1 small"><strong>Contoh:</strong></p>
                        <pre class="mb-0 small">Terlambat sholat,Pengabdian,5,Ringan
Tidak membawa kitab,Diniyyah,10,Sedang
Merokok di area pondok,Kesantrian,100,Sangat Berat</pre>
                    </div>
                    <form action="process.php" method="POST">
                        <div class="mb-3">
                            <label for="bulk_data" class="form-label">Data Pelanggaran (satu baris per data)</label>
                            <textarea name="bulk_data" id="bulk_data" class="form-control" rows="8" placeholder="Paste data dari Excel di sini..."></textarea>
                        </div>
                        <hr>
                        <button type="submit" name="create_bulk" class="btn btn-success w-100">
                            <i class="fas fa-cloud-upload-alt me-1"></i> Proses & Simpan Semua
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>