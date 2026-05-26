<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Proteksi Halaman
guard('backup_restore_manage');

// Hitung ukuran database
$q_size = $conn->query("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$size_bytes = $q_size->fetch_assoc()['size'] ?? 0;
$size_mb = round($size_bytes / 1024 / 1024, 2);
if ($size_mb < 0.01) $size_mb = 0.01;
$size_gz = round($size_mb * 0.15, 2); // Estimasi GZIP 15% dari asli
if ($size_gz < 0.01) $size_gz = 0.01;

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    .br-container { max-width: 1100px; margin: 0 auto; font-family: 'Inter', sans-serif; }
    
    .card-panel {
        background: #ffffff;
        border-radius: 12px;
        padding: 2rem;
        height: 100%;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    
    .card-export { border-top: 4px solid #10b981; }
    .card-import { border-top: 4px solid #f59e0b; }
    
    .icon-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 1.5rem;
    }
    
    .icon-circle {
        width: 48px; height: 48px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
    }
    .icon-export-circle { background: #d1fae5; color: #10b981; }
    .icon-import-circle { background: #fef3c7; color: #f59e0b; }
    
    .title-text { font-weight: 700; color: #1e293b; margin: 0; font-size: 1.25rem; }
    .subtitle-text { font-size: 0.85rem; margin: 0; }
    .subtitle-export { color: #10b981; font-weight: 600; }
    .subtitle-import { color: #f59e0b; font-weight: 600; }
    
    .desc-text { color: #64748b; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem; }
    
    .size-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .size-header { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; }
    .size-header span:first-child { color: #64748b; }
    .size-header span:last-child { color: #10b981; font-weight: 700; }
    
    .progress-bar-custom {
        height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden;
    }
    .progress-fill {
        height: 100%; background: repeating-linear-gradient(45deg, #10b981, #10b981 10px, #34d399 10px, #34d399 20px);
        width: 100%;
    }
    
    /* Format Selector Radio */
    .format-label-title { font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 8px; display: block; }
    .format-selector { display: flex; gap: 10px; margin-bottom: 1.5rem; }
    .format-option {
        flex: 1;
        position: relative;
    }
    .format-option input { position: absolute; opacity: 0; cursor: pointer; }
    .format-box {
        border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px;
        text-align: center; cursor: pointer; transition: 0.2s;
        background: #fff;
    }
    .format-box .fmt-title { font-weight: 700; color: #1e293b; display: block; font-size: 1rem; }
    .format-box .fmt-desc { font-size: 0.7rem; color: #64748b; }
    
    .format-option input:checked ~ .format-box {
        border-color: #10b981; background: #10b981;
    }
    .format-option input:checked ~ .format-box .fmt-title,
    .format-option input:checked ~ .format-box .fmt-desc { color: #fff; }
    
    .btn-action-export { background: #10b981; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; width: 100%; transition: 0.2s; }
    .btn-action-export:hover { background: #059669; color: white; }
    
    .btn-action-import { background: #f59e0b; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; width: 100%; transition: 0.2s; }
    .btn-action-import:hover { background: #d97706; color: white; }
    
    .file-drop-area {
        position: relative;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        width: 100%; padding: 30px 20px;
        border: 2px dashed #fcd34d; border-radius: 8px;
        transition: 0.2s; background-color: #fffbeb;
        margin-bottom: 1.5rem; text-align: center;
    }
    .file-drop-area:hover, .file-drop-area.is-active { background-color: #fef3c7; border-color: #f59e0b; }
    .file-msg { font-size: 0.95rem; color: #b45309; font-weight: 600; margin-bottom: 5px; }
    .file-msg-sub { font-size: 0.8rem; color: #d97706; }
    .file-input { position: absolute; left: 0; top: 0; height: 100%; width: 100%; cursor: pointer; opacity: 0; }
    
    .warning-box {
        color: #b45309; font-size: 0.85rem; line-height: 1.5; margin-bottom: 1.5rem;
    }
    
    .features-section {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-top: 2rem;
    }
    .feat-title { font-weight: 700; color: #1e293b; margin-bottom: 1rem; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
    .feat-item { display: flex; gap: 12px; }
    .feat-icon { color: #3b82f6; font-size: 1.25rem; margin-top: 2px; }
    .feat-text h6 { font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 3px; }
    .feat-text p { font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.4; }
</style>

<div class="container-fluid py-4 px-3 br-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="../index.php" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-arrow-left text-secondary"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Backup & Restore</h4>
                <p class="text-muted small mb-0">Kelola salinan data sistem Anda</p>
            </div>
        </div>
        <div class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-database text-secondary me-2"></i> Ukuran DB: <?= $size_mb ?> MB
        </div>
    </div>

    <?php display_flash_message(); ?>

    <div class="row g-4">
        <!-- Kolom Export (Backup) -->
        <div class="col-lg-6">
            <div class="card-panel card-export">
                <div class="icon-header">
                    <div class="icon-circle icon-export-circle"><i class="fas fa-cloud-download-alt"></i></div>
                    <div>
                        <h3 class="title-text">Backup Data (Ekspor)</h3>
                        <p class="subtitle-text subtitle-export">Kompresi GZIP Aktif</p>
                    </div>
                </div>
                
                <p class="desc-text">Sistem akan mengunduh salinan database Anda. Format terkompresi lebih hemat ruang dan diproses secara streaming untuk menjamin kelancaran pada database besar.</p>
                
                <div class="size-box">
                    <div class="size-header">
                        <span>Estimasi Ukuran:</span>
                        <span id="estSizeLabel">~<?= $size_gz ?> MB (Terkompresi)</span>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill"></div>
                    </div>
                </div>
                
                <form action="export.php" method="post" id="exportForm">
                    <span class="format-label-title"><i class="fas fa-cog me-1"></i> Pilih Format File:</span>
                    <div class="format-selector">
                        <label class="format-option">
                            <input type="radio" name="format" value="gz" checked onchange="updateEstimasi('gz')">
                            <div class="format-box">
                                <span class="fmt-title"><i class="fas fa-file-archive me-1"></i> .sql.gz</span>
                                <span class="fmt-desc">Kompresi (Rekomendasi)</span>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="format" value="sql" onchange="updateEstimasi('sql')">
                            <div class="format-box">
                                <span class="fmt-title"><i class="fas fa-file-code me-1"></i> .sql</span>
                                <span class="fmt-desc">SQL Mentah (Teks)</span>
                            </div>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-action-export" id="btnExport">
                        <i class="fas fa-download me-2"></i> <span id="btnExportText">Download Backup (.sql.gz)</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Kolom Import (Restore) -->
        <div class="col-lg-6">
            <div class="card-panel card-import">
                <div class="icon-header">
                    <div class="icon-circle icon-import-circle"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div>
                        <h3 class="title-text">Restore Data (Impor)</h3>
                        <p class="subtitle-text subtitle-import">Mendukung SQL & GZIP</p>
                    </div>
                </div>
                
                <p class="warning-box">
                    <strong>PERHATIAN:</strong> Seluruh data saat ini akan <strong>dihapus dan diganti</strong> dengan data dari file backup. Pastikan Anda memiliki cadangan jika diperlukan.
                </p>
                
                <form action="import.php" method="post" enctype="multipart/form-data" id="importForm" class="mt-auto">
                    <span class="format-label-title">Pilih File Backup (.sql atau .sql.gz)</span>
                    <div class="file-drop-area" id="dropArea">
                        <span class="file-msg" id="fileMsg">Choose File</span>
                        <span class="file-msg-sub" id="fileMsgSub">No file chosen</span>
                        <input class="file-input" type="file" name="sql_file" accept=".sql,.gz" required id="sqlInput">
                    </div>
                    
                    <button type="submit" class="btn-action-import" id="btnRestore">
                        <i class="fas fa-upload me-2"></i> Mulai Restore Data
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bagian Fitur Pintar -->
    <div class="features-section">
        <h5 class="feat-title"><i class="fas fa-info-circle text-secondary"></i> Mengapa Fitur Ini Lebih Pintar?</h5>
        <div class="row g-4 mt-1">
            <div class="col-md-4">
                <div class="feat-item">
                    <div class="feat-icon"><i class="fas fa-microchip"></i></div>
                    <div class="feat-text">
                        <h6>Hemat Memori</h6>
                        <p>Memproses data secara bertahap (streaming), aman untuk database berukuran besar.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feat-item">
                    <div class="feat-icon"><i class="fas fa-compress-arrows-alt"></i></div>
                    <div class="feat-text">
                        <h6>Kompresi Otomatis</h6>
                        <p>Mengurangi ukuran file unduhan secara signifikan menggunakan algoritma GZIP.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feat-item">
                    <div class="feat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="feat-text">
                        <h6>Integritas Data</h6>
                        <p>Menggunakan mode transaksi untuk mencegah kerusakan data jika terjadi interupsi.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const sizeGz = <?= $size_gz ?>;
    const sizeSql = <?= $size_mb ?>;

    function updateEstimasi(format) {
        const lbl = document.getElementById('estSizeLabel');
        const btnTxt = document.getElementById('btnExportText');
        
        if (format === 'gz') {
            lbl.innerHTML = '~' + sizeGz + ' MB (Terkompresi)';
            btnTxt.innerHTML = 'Download Backup (.sql.gz)';
        } else {
            lbl.innerHTML = '~' + sizeSql + ' MB (Mentah)';
            btnTxt.innerHTML = 'Download Backup (.sql)';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sqlInput = document.getElementById('sqlInput');
        const fileMsg = document.getElementById('fileMsg');
        const fileMsgSub = document.getElementById('fileMsgSub');
        const importForm = document.getElementById('importForm');
        const dropArea = document.getElementById('dropArea');
        
        sqlInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileMsg.innerHTML = '<i class="fas fa-file-check me-2"></i>File Terpilih';
                fileMsgSub.innerHTML = this.files[0].name;
                dropArea.style.backgroundColor = '#d1fae5';
                dropArea.style.borderColor = '#10b981';
                fileMsg.style.color = '#059669';
                fileMsgSub.style.color = '#10b981';
            } else {
                fileMsg.innerHTML = 'Choose File';
                fileMsgSub.innerHTML = 'No file chosen';
                dropArea.style.backgroundColor = '';
                dropArea.style.borderColor = '';
                fileMsg.style.color = '';
                fileMsgSub.style.color = '';
            }
        });

        importForm.addEventListener('submit', function(e) {
            if (!confirm('PERINGATAN BAHAYA!\n\nProses restore akan menghapus SELURUH data yang ada saat ini dan menggantinya dengan data dari file backup.\n\nApakah Anda benar-benar yakin ingin melanjutkan?')) {
                e.preventDefault();
            } else {
                const btn = document.getElementById('btnRestore');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
                btn.style.opacity = '0.7';
                btn.style.pointerEvents = 'none';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
