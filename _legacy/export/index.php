<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('export_laporan'); 

// 3. Generate CSRF Token untuk proteksi form keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 4. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
?>

<?php
// Ambil daftar kamar unik dari database
$kamar_list = [];
$sql_kamar = "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC";
$result_kamar = $conn->query($sql_kamar);
if ($result_kamar && $result_kamar->num_rows > 0) {
    while ($row = $result_kamar->fetch_assoc()) {
        $kamar_list[] = $row['kamar'];
    }
}
?>

<style>
    /* Styling Premium & Scoped untuk Halaman Export */
    #export-index-page {
        padding: 1rem 0;
    }
    
    #export-index-page .page-header {
        margin-bottom: 2rem;
    }

    #export-index-page .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #0f172a;
    }

    #export-index-page .page-subtitle {
        font-size: 0.875rem;
        color: #64748b;
    }

    #export-index-page .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 2rem;
    }

    #export-index-page .card-premium-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #f1f5f9;
    }

    #export-index-page .card-premium-title {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    #export-index-page .card-premium-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    #export-index-page .icon-indigo {
        background-color: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
    }

    #export-index-page .icon-green {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    #export-index-page .card-premium-body {
        padding: 2rem;
    }

    #export-index-page .form-label-custom {
        font-weight: 600;
        color: #475569;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    #export-index-page .form-control-custom, 
    #export-index-page .form-select-custom {
        border-radius: 0.75rem;
        border: 1px solid #cbd5e1;
        padding: 0.65rem 1rem;
        font-size: 0.9rem;
        background-color: #f8fafc;
        transition: all 0.2s;
    }

    #export-index-page .form-control-custom:focus, 
    #export-index-page .form-select-custom:focus {
        background-color: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    #export-index-page .btn-premium {
        padding: 0.75rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.25s ease;
        border: none;
    }

    #export-index-page .btn-indigo {
        background-color: #4f46e5;
        color: #ffffff;
    }

    #export-index-page .btn-indigo:hover {
        background-color: #4338ca;
        color: #ffffff;
        transform: translateY(-2px);
    }

    #export-index-page .btn-green {
        background-color: #10b981;
        color: #ffffff;
    }

    #export-index-page .btn-green:hover {
        background-color: #059669;
        color: #ffffff;
        transform: translateY(-2px);
    }

    #export-index-page .btn-gray {
        background-color: #475569;
        color: #ffffff;
    }

    #export-index-page .btn-gray:hover {
        background-color: #334155;
        color: #ffffff;
        transform: translateY(-2px);
    }

    #export-index-page .btn-blue {
        background-color: #2563eb;
        color: #ffffff;
    }

    #export-index-page .btn-blue:hover {
        background-color: #1d4ed8;
        color: #ffffff;
        transform: translateY(-2px);
    }

    #export-index-page .master-buttons-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
    }

    #export-index-page .master-btn-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #f8fafc;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
        transition: all 0.2s ease;
    }

    #export-index-page .master-btn-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }

    #export-index-page .master-btn-card h6 {
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        color: #1e293b;
        font-size: 0.95rem;
    }

    #export-index-page .master-btn-card p {
        margin: 0;
        color: #64748b;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    #export-index-page .input-group-text-custom {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #64748b;
        border-radius: 0.75rem 0 0 0.75rem;
    }

    @media (max-width: 768px) {
        #export-index-page .card-premium-header {
            padding: 1.25rem;
        }

        #export-index-page .card-premium-body {
            padding: 1.25rem;
        }
    }
</style>

<div id="export-index-page" class="container-fluid">
    <!-- Header -->
    <div class="page-header d-flex flex-column gap-1">
        <h3 class="page-title mb-0"><i class="fas fa-file-excel text-success me-2"></i>Ekspor Laporan & Data</h3>
        <p class="page-subtitle mb-0">Unduh data santri, pelanggaran, dan reward langsung ke format Excel (.xlsx)</p>
    </div>

    <!-- CARD 1: LAPORAN PELANGGARAN LENGKAP -->
    <div class="card card-premium">
        <div class="card-premium-header">
            <div class="card-premium-title">
                <div class="card-premium-icon-wrapper icon-indigo">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0" style="font-size: 1.1rem; color: #1e293b;">Laporan Pelanggaran Lengkap (4-in-1)</h5>
                    <small class="text-muted">Unduh rekap detail santri, pelanggaran umum, rekap per kamar, dan kebersihan sekaligus.</small>
                </div>
            </div>
        </div>
        <div class="card-premium-body">
            <form action="process-export.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label form-label-custom">Tanggal Mulai</label>
                        <div class="input-group">
                            <span class="input-group-text input-group-text-custom border-end-0"><i class="far fa-calendar-alt"></i></span>
                            <input type="date" name="tanggal_mulai" required class="form-control form-control-custom border-start-0" value="<?= PERIODE_AKTIF ?>">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label form-label-custom">Tanggal Selesai</label>
                        <div class="input-group">
                            <span class="input-group-text input-group-text-custom border-end-0"><i class="far fa-calendar-alt"></i></span>
                            <input type="date" name="tanggal_selesai" required class="form-control form-control-custom border-start-0" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-4">
                        <label for="kamar" class="form-label form-label-custom">Filter Kamar</label>
                        <div class="input-group">
                            <span class="input-group-text input-group-text-custom border-end-0"><i class="fas fa-bed"></i></span>
                            <select id="kamar" name="kamar" class="form-select form-select-custom border-start-0">
                                <option value="semua">Semua Kamar</option>
                                <?php foreach ($kamar_list as $kamar_item) : ?>
                                    <option value="<?php echo htmlspecialchars($kamar_item); ?>"><?php echo htmlspecialchars($kamar_item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" name="export" class="btn btn-premium btn-indigo">
                        <i class="fas fa-download"></i>
                        Unduh Laporan Lengkap
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD 2: EXPORT DATA MASTER (RAW) -->
    <div class="card card-premium">
        <div class="card-premium-header">
            <div class="card-premium-title">
                <div class="card-premium-icon-wrapper icon-green">
                    <i class="fas fa-database"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0" style="font-size: 1.1rem; color: #1e293b;">Export Data Master (RAW)</h5>
                    <small class="text-muted">Unduh salinan data mentah dari database untuk keperluan arsip atau analisis eksternal.</small>
                </div>
            </div>
        </div>
        <div class="card-premium-body">
            <form action="process-export.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="master-buttons-grid">
                    <!-- Button Card 1: Santri -->
                    <div class="master-btn-card">
                        <div>
                            <h6>Data Induk Santri</h6>
                            <p class="text-muted">Seluruh profil santri aktif beserta sisa akumulasi poin aktif mereka saat ini.</p>
                        </div>
                        <button type="submit" name="export_santri" class="btn btn-premium btn-green w-100">
                            <i class="fas fa-users"></i> Ekspor Data Santri
                        </button>
                    </div>

                    <!-- Button Card 2: Pelanggaran -->
                    <div class="master-btn-card">
                        <div>
                            <h6>Data Jenis Pelanggaran</h6>
                            <p class="text-muted">Data master jenis pelanggaran, kategori bobot poin, dan pembagian pengasuhannya.</p>
                        </div>
                        <button type="submit" name="export_jenis_pelanggaran" class="btn btn-premium btn-gray w-100">
                            <i class="fas fa-exclamation-triangle"></i> Ekspor Jenis Pelanggaran
                        </button>
                    </div>

                    <!-- Button Card 3: Reward -->
                    <div class="master-btn-card">
                        <div>
                            <h6>Data Jenis Reward</h6>
                            <p class="text-muted">Data master jenis apresiasi/reward beserta bobot poin pengurang hukuman.</p>
                        </div>
                        <button type="submit" name="export_jenis_reward" class="btn btn-premium btn-blue w-100">
                            <i class="fas fa-trophy"></i> Ekspor Jenis Reward
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>