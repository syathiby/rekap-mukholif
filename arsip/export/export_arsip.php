<?php 
// 1. Panggil Inisialisasi Aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Proteksi Halaman
guard('arsip_export'); 

// Generate CSRF token untuk keamanan ekspor
$csrf_token = csrf_generate();

// ID Arsip jika diakses dari view.php?id=X
$selected_arsip_id = (int)($_GET['id'] ?? 0);

// Ambil semua data arsip untuk ditampilkan di dropdown
$arsip_list = [];
$sql = "SELECT id, judul, tanggal_mulai, tanggal_selesai FROM arsip ORDER BY tanggal_mulai DESC";
$result = $conn->query($sql);

if ($result) {
    $arsip_list = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} else {
    die("Error saat mengambil daftar arsip: " . $conn->error);
}

// Panggil Header Layout
require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --primary: #4f46e5;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
    }
    body { background: var(--bg); font-family: 'Poppins', sans-serif; }
    .page-container { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
    .btn-kembali {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        background-color: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
    }
    .btn-kembali:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
        text-decoration: none;
    }
</style>

<div class="page-container">
    
    <!-- Top Header -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas fa-file-excel text-success"></i> Ekspor Data Arsip
            </h2>
            <p class="text-muted small mb-0">Unduh seluruh lembar kerja arsip dalam satu file Excel (.xlsx) komprehensif.</p>
        </div>
        <div>
            <?php if ($selected_arsip_id > 0): ?>
                <a href="../view.php?id=<?= $selected_arsip_id ?>" class="btn-kembali">
                    <i class="fas fa-arrow-left"></i> Kembali ke Arsip
                </a>
            <?php else: ?>
                <a href="../index.php" class="btn-kembali">
                    <i class="fas fa-arrow-left"></i> Kembali ke Gudang
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($arsip_list) > 0): ?>
        
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <i class="fas fa-info-circle fa-2x text-success"></i>
                    <div>
                        <h6 class="fw-bold text-success-emphasis mb-0">Paket Ekspor Arsip Terpadu</h6>
                        <small class="text-muted">File Excel mencakup 6 Sheet Eksekutif: Rekap Santri, Rekap Kamar, Kebersihan Kamar, Pelanggaran Umum, Riwayat Bahasa, dan Data Reward.</small>
                    </div>
                </div>
                
                <form action="process_export_arsip.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="mb-4">
                        <label for="arsip_id" class="form-label fw-bold text-dark">Pilih Periode Arsip yang Ingin Diekspor:</label>
                        <select class="form-select form-select-lg rounded-3 fs-6" id="arsip_id" name="arsip_id" required>
                            <option value="" <?= ($selected_arsip_id === 0) ? 'selected' : '' ?> disabled>-- Klik untuk memilih arsip --</option>
                            <?php foreach ($arsip_list as $arsip): ?>
                                <option value="<?= htmlspecialchars($arsip['id']) ?>" <?= ($selected_arsip_id === (int)$arsip['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($arsip['judul']) ?> (<?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid pt-2">
                        <button type="submit" class="btn btn-success btn-lg rounded-pill fw-bold py-3 shadow-sm">
                            <i class="fas fa-file-download me-2"></i> Download File Excel (.xlsx)
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        
        <div class="card border-0 shadow-sm rounded-4 text-center p-5">
            <div class="card-body">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <h5 class="fw-bold text-dark">Belum Ada Data Arsip</h5>
                <p class="text-muted small mb-0">Belum ada catatan arsip yang tersimpan di sistem. Buat arsip terlebih dahulu melalui proses Tutup Buku.</p>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../../layouts/footer.php';
?>
