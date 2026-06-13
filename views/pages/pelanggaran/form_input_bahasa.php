
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-edit me-2 text-primary"></i> Input Pelanggaran <?= htmlspecialchars($bagian) ?>
            </h3>
            <p class="text-muted mb-0">Catat pelanggaran untuk satu atau banyak santri sekaligus.</p>
        </div>
        <a href="<?= BASE_URL ?>/pelanggaran" class="btn btn-outline-secondary rounded-pill" hx-boost="true">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <form method="POST" action="<?= BASE_URL ?>/pelanggaran/<?= strtolower($bagian) ?>/store" id="formPelanggaran" hx-boost="true">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        
        <div class="row g-4">
            <!-- Left Panel: Input Details -->
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top: 80px; z-index: 1;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold mb-0">Detail Pelanggaran</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Jenis Pelanggaran <span class="text-danger">*</span></label>
                            <select name="jenis_pelanggaran_id" class="form-select select2" required>
                                <option value="" disabled selected>-- Pilih Jenis Pelanggaran --</option>
                                <option value="clear" class="text-success fw-bold">✨ BERSIHKAN PELANGGARAN BAHASA (Reset)</option>
                                
                                <?php foreach ($jenisPelanggaran as $jp): ?>
                                    <option value="<?= $jp['id'] ?>">
                                        <?= htmlspecialchars($jp['nama_pelanggaran']) ?> (Poin: <?= $jp['poin'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Tanggal & Waktu Kejadian <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="tanggal" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        
                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow-sm" id="btnSubmit">
                                <i class="fas fa-save me-2"></i> Simpan Pelanggaran
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel: Santri Selection -->
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title fw-bold mb-0">
                            Pilih Santri <span id="selectedCount" class="badge bg-primary ms-2 rounded-pill">0 Terpilih</span>
                        </h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="selectAll" style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label ms-2 cursor-pointer pt-1" for="selectAll">Pilih Semua</label>
                        </div>
                    </div>
                    
                    <div class="card-body p-0 border-bottom">
                        <div class="p-3 bg-light">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" 
                                       placeholder="Cari nama atau NIS santri..." 
                                       hx-get="<?= BASE_URL ?>/pelanggaran/search-santri" 
                                       hx-trigger="keyup changed delay:300ms, search" 
                                       hx-target="#santriList" 
                                       hx-indicator="#searchIndicator"
                                       name="q">
                            </div>
                        </div>
                    </div>
                    
                    <div class="position-relative">
                        <div id="searchIndicator" class="htmx-indicator position-absolute w-100 h-100 bg-white bg-opacity-75 d-flex justify-content-center align-items-center" style="z-index: 5;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        
                        <!-- List of Santri fetched initially via HTMX as well -->
                        <div class="list-group list-group-flush overflow-auto" id="santriList" style="max-height: 600px;" 
                             hx-get="<?= BASE_URL ?>/pelanggaran/search-santri" 
                             hx-trigger="load">
                            <div class="p-4 text-center text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div> Memuat daftar santri...
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </form>
</div>

<!-- Select2 CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    const form = document.getElementById('formPelanggaran');
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectedCountSpan = document.getElementById('selectedCount');
    const santriListContainer = document.getElementById('santriList');

    // Update selected count
    function updateCount() {
        const checkedCount = document.querySelectorAll('.santri-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount + ' Terpilih';
        if (checkedCount > 0) {
            selectedCountSpan.classList.replace('bg-secondary', 'bg-primary');
        } else {
            selectedCountSpan.classList.replace('bg-primary', 'bg-secondary');
        }
    }

    // Handle Select All
    selectAllCheckbox.addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('.santri-checkbox');
        checkboxes.forEach(cb => {
            // Only check visible ones (filtered by search)
            if (cb.closest('.list-group-item').style.display !== 'none') {
                cb.checked = e.target.checked;
            }
        });
        updateCount();
    });

    // Event delegation for dynamically loaded checkboxes via HTMX
    santriListContainer.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('santri-checkbox')) {
            updateCount();
            
            // Uncheck "Select All" if some are unchecked
            const visibleCheckboxes = Array.from(document.querySelectorAll('.santri-checkbox')).filter(cb => cb.closest('.list-group-item').style.display !== 'none');
            const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
        }
    });
    
    // When HTMX finishes loading new santri list, uncheck select all and recount
    document.body.addEventListener('htmx:afterSwap', function(event) {
        if (event.detail.target.id === 'santriList') {
            selectAllCheckbox.checked = false;
            updateCount();
        }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
        const checkedCount = document.querySelectorAll('.santri-checkbox:checked').length;
        if (checkedCount === 0) {
            e.preventDefault();
            alert('Silakan pilih minimal satu santri untuk diberi pelanggaran!');
        }
    });
});
</script>

<style>
.hover-bg-light:hover {
    background-color: #f8f9fa;
}
.cursor-pointer {
    cursor: pointer;
}
/* HTMX indicator style */
.htmx-indicator {
    opacity: 0;
    transition: opacity 200ms ease-in;
    pointer-events: none;
}
.htmx-request .htmx-indicator{
    opacity: 1;
}
.htmx-request.htmx-indicator{
    opacity: 1;
}
</style>

