<?php
require_once __DIR__ . '/../../bootstrap/init.php';

guard('jenis_reward_edit'); 

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

require_once __DIR__ . '/../../layouts/header.php';

$id = (int)$_GET['id'];
$stmt_g = $conn->prepare("SELECT * FROM jenis_reward WHERE id = ?");
$stmt_g->bind_param('i', $id);
$stmt_g->execute();
$data = $stmt_g->get_result()->fetch_assoc();
$stmt_g->close();

if(!$data) {
    echo "<script>alert('Data tidak ditemukan'); window.location='index.php';</script>";
    exit;
}
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
    }
</style>

<div class="content-wrapper">
    <div class="container-fluid">
        
        <div class="header-minimal">
            <div class="header-title">
                <h4><i class="fas fa-edit me-2 text-warning" style="color: #fb8c00 !important;"></i>Edit Data Reward</h4>
                <p>Ubah detail informasi jenis reward atau apresiasi santri.</p>
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
                        <form action="process.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id" value="<?= $data['id'] ?>">
                            
                            <div class="mb-4">
                                <label class="form-label">Nama Reward <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-medal text-muted"></i></span>
                                    <input type="text" name="nama_reward" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($data['nama_reward']) ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Poin Reward (Pengurang) <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-minus-circle text-success" style="color: var(--primary-dark) !important;"></i></span>
                                        <input type="number" name="poin_reward" class="form-control border-start-0 ps-0" value="<?= $data['poin_reward'] ?>" required>
                                        <span class="input-group-text bg-light">Poin</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control shadow-sm" rows="3"><?= htmlspecialchars($data['deskripsi']) ?></textarea>
                            </div>

                            <hr class="my-4" style="opacity: 0.1;">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-light text-muted px-4 py-2 rounded-pill fw-bold">Batal</a>
                                <button type="submit" name="edit_jenis" class="btn btn-submit">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>