<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../../init.php';

// 2. Security Gate: Cek apakah user boleh bikin reward baru?
// Kalau gak punya izin 'jenis_reward_create', dia bakal ditendang keluar.
guard('jenis_reward_create');

// 3. Panggil Template Utama
require_once __DIR__ . '/../../header.php';
?>

<style>
    :root {
        --primary-reward: #11998e;
        --secondary-reward: #38ef7d;
    }
    
    .page-header-block {
        background: linear-gradient(135deg, var(--primary-reward), var(--secondary-reward));
        padding: 1.5rem;
        border-radius: 12px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .form-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .form-label {
        font-weight: 600;
        color: #495057;
    }

    .form-text {
        font-size: 0.85em;
        color: #8898aa;
    }

    .btn-submit {
        background: var(--primary-reward);
        border: none;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-submit:hover {
        background: #0d7a71;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>

<div class="content-wrapper" style="padding: 20px;">
    <div class="container-fluid">
        
        <div class="d-flex align-items-center justify-content-between page-header-block">
            <div>
                <h3 class="mb-1 fw-bold"><i class="fas fa-plus-circle me-2"></i>Tambah Reward Baru</h3>
                <p class="mb-0 opacity-75">Tambahkan jenis prestasi atau apresiasi baru ke dalam sistem.</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-light text-success fw-bold shadow-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card form-card bg-white">
                    <div class="card-body p-4 p-md-5">
                        
                        <form action="process.php" method="POST">
                            
                            <div class="mb-4">
                                <label for="nama_reward" class="form-label">Nama Reward / Prestasi <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-medal text-muted"></i></span>
                                    <input type="text" name="nama_reward" id="nama_reward" class="form-control border-start-0 ps-0" placeholder="Contoh: Juara Kelas, Puasa Sunnah, Hafalan Lancar" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="poin_reward" class="form-label">Nilai Poin (Pengurang) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-minus-circle text-success"></i></span>
                                        <input type="number" name="poin_reward" id="poin_reward" class="form-control border-start-0 ps-0" placeholder="0" min="1" required>
                                        <span class="input-group-text bg-light">Poin</span>
                                    </div>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Masukkan angka saja. Poin pelanggaran santri akan berkurang sebanyak angka ini.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="deskripsi" class="form-label">Deskripsi / Keterangan (Opsional)</label>
                                <textarea name="deskripsi" id="deskripsi" class="form-control" rows="4" placeholder="Tuliskan detail kriteria reward ini (jika ada)..."></textarea>
                            </div>

                            <hr class="my-4" style="opacity: 0.1;">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-light text-muted px-4 fw-bold">Batal</a>
                                <button type="submit" name="add_jenis" class="btn btn-success btn-submit text-white px-4">
                                    <i class="fas fa-save me-2"></i> Simpan Data
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>