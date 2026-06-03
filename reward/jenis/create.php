<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Security Gate
guard('jenis_reward_create');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// 3. Panggil Template Utama
require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --primary-dark: #145c38;
        --primary-soft: #e6f4ea;
        --text-main: #344767;
        --text-muted: #8392ab;
        --radius-std: 12px;
    }
    
    body {
        color: var(--text-main);
    }

    .header-minimal {
        background: #fff;
        padding: 20px 24px;
        border-radius: var(--radius-std);
        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        margin-bottom: 24px;
        border-left: 5px solid var(--primary-dark);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .header-title h4 { font-weight: 700; color: var(--text-main); margin: 0; font-size: 1.25rem; }
    .header-title p { color: var(--text-muted); margin: 0; font-size: 0.85rem; }

    .form-card {
        border: none;
        border-radius: var(--radius-std);
        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.02);
    }

    .form-label {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.95rem;
    }

    .form-control, .form-select {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 10px 15px;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        background: #fff;
        border-color: var(--primary-dark);
        box-shadow: 0 0 0 3px rgba(20, 92, 56, 0.1);
    }

    .form-text {
        font-size: 0.8em;
        color: var(--text-muted);
    }

    .btn-submit {
        background: var(--primary-dark);
        border: none;
        padding: 10px 24px;
        font-weight: 600;
        border-radius: 30px;
        transition: all 0.3s;
        color: white;
    }
    
    .btn-submit:hover {
        background: #0b3d23;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(20, 92, 56, 0.2);
        color: white;
    }

    .nav-tabs {
        display: flex !important;
        flex-wrap: nowrap !important;
        flex-direction: row !important;
        border-bottom: 2px solid #f1f1f1;
        overflow-x: auto;
    }

    .nav-tabs .nav-item {
        flex: 1 1 auto;
        text-align: center;
    }

    .nav-tabs .nav-link {
        font-weight: 600;
        color: var(--text-muted);
        border: none;
        border-bottom: 2px solid transparent;
        padding: 10px 20px;
        background: none;
        transition: all 0.2s;
        width: 100%;
        text-align: center;
        white-space: nowrap;
    }
    .nav-tabs .nav-link:hover {
        color: var(--text-main);
        border-bottom-color: #e9ecef;
    }
    .nav-tabs .nav-link.active {
        color: var(--primary-dark);
        border-color: var(--primary-dark);
        background: none;
    }

    .content-wrapper {
        padding: 24px;
    }

    /* Responsif HP */
    @media (max-width: 768px) {
        .content-wrapper { 
            padding: 8px !important; 
        }
        .header-minimal { 
            padding: 15px !important; 
            margin-bottom: 16px !important;
        }
        .form-card .card-body { 
            padding: 1.25rem !important; 
        }
        .nav-tabs .nav-link {
            padding: 10px 12px !important;
            font-size: 0.85rem !important;
        }
    }
</style>

<div class="content-wrapper">
    <div class="container-fluid">
        
        <div class="header-minimal">
            <div class="header-title">
                <h4><i class="fas fa-plus-circle me-2 text-success" style="color: var(--primary-dark) !important;"></i>Tambah Reward Baru</h4>
                <p>Tambahkan jenis prestasi atau apresiasi ke dalam sistem.</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm px-4 py-2 rounded-pill shadow-sm">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card form-card bg-white">
                    <div class="card-body p-4 p-md-5">

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-4" id="rewardTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">Tambah Satu</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab">Tambah Banyak</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="rewardTabContent">

                            <!-- === Tambah Satu === -->
                            <div class="tab-pane fade show active" id="single" role="tabpanel">
                                <form action="process.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <div class="mb-4">
                                        <label for="nama_reward" class="form-label">Nama Reward / Prestasi <span class="text-danger">*</span></label>
                                        <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-medal text-muted"></i></span>
                                            <input type="text" name="nama_reward" id="nama_reward" class="form-control border-start-0 ps-0" placeholder="Contoh: Juara Kelas, Puasa Sunnah" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="poin_reward" class="form-label">Nilai Poin (Pengurang) <span class="text-danger">*</span></label>
                                            <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-minus-circle text-success" style="color: var(--primary-dark) !important;"></i></span>
                                                <input type="number" name="poin_reward" id="poin_reward" class="form-control border-start-0 ps-0" placeholder="0" min="1" required>
                                                <span class="input-group-text bg-light">Poin</span>
                                            </div>
                                            <div class="form-text mt-2">
                                                <i class="fas fa-info-circle me-1"></i> Poin pelanggaran santri akan berkurang sebanyak angka ini.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="deskripsi" class="form-label">Deskripsi / Keterangan (Opsional)</label>
                                        <textarea name="deskripsi" id="deskripsi" class="form-control shadow-sm" rows="3" placeholder="Tuliskan detail kriteria..."></textarea>
                                    </div>

                                    <hr class="my-4" style="opacity: 0.1;">

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="index.php" class="btn btn-light text-muted px-4 py-2 rounded-pill fw-bold">Batal</a>
                                        <button type="submit" name="add_jenis" class="btn btn-submit">
                                            <i class="fas fa-save me-2"></i>Simpan Data
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- === Tambah Banyak === -->
                            <div class="tab-pane fade" id="bulk" role="tabpanel">
                                <form action="process.php" method="POST" id="bulkForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <p class="text-muted mb-3">
                                        <i class="fas fa-info-circle me-1"></i> Masukkan beberapa reward sekaligus. Setiap baris = 1 reward.<br>
                                        Format: <code>Nama,Poin,Deskripsi (opsional)</code><br>
                                        Contoh: <code>Juara Kelas,10,Juara 1 lomba akademik</code>
                                    </p>

                                    <div class="mb-4">
                                        <label class="form-label">Daftar Reward (Satu per baris) <span class="text-danger">*</span></label>
                                        <textarea name="bulk_input" class="form-control" rows="8" placeholder="Juara Olimpiade,15,Juara tingkat provinsi&#10;Hafalan Lancar,5,Menyelesaikan juz 30" required></textarea>
                                    </div>

                                    <hr class="my-4" style="opacity: 0.1;">

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="index.php" class="btn btn-light text-muted px-4 py-2 rounded-pill fw-bold">Batal</a>
                                        <button type="submit" name="add_bulk" class="btn btn-submit">
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
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>