<?php
$can_create = \App\Helpers\AuthHelper::hasPermission('santri_create');
$can_edit = \App\Helpers\AuthHelper::hasPermission('santri_edit');
$can_delete = \App\Helpers\AuthHelper::hasPermission('santri_delete');

$csrf_token = csrf_generate(); // pastikan fungsi ini dipanggil
?>
<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
    }
    
    .page-title-card, .filter-card, .table-container, .card-action-bulk {
        background: var(--bg-card);
        color: var(--text-main);
        border-radius: 1rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        border: 1px solid var(--border);
    }
    .table-container { overflow: hidden; }
    .table thead {
        background-color: #f1f5f9;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
    }
    .table th { border-bottom-width: 1px; }
    .action-cell { width: 120px; }
    
    .card-action-bulk { padding: 1rem; margin-bottom: 1rem; }

    @media (max-width: 768px) {
        .filter-buttons {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
        }
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-users text-primary me-2"></i>Data Santri
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#infoModal" title="Informasi Penting">
                    <i class="fas fa-info-circle fs-5"></i>
                </button>
            </h3>
            <p class="text-muted mb-0">Kelola data santri secara terpusat</p>
        </div>
        <div class="d-flex align-items-center">
            <span class="badge bg-white border text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                <i class="fas fa-database me-1 text-primary"></i>
                <span id="total-santri"><?php echo number_format($total) . " Santri"; ?></span>
            </span>
        </div>
    </div>
    
    <div class="filter-card p-3 p-md-4 mb-4">
        <!-- Menggunakan HTMX untuk instant filter -->
        <form class="row g-3" method="GET" action="<?= BASE_URL ?>/santri" id="filterForm"
              hx-get="<?= BASE_URL ?>/santri" 
              hx-target="#table-data-wrapper" 
              hx-trigger="submit, input delay:300ms from:input"
              hx-push-url="true"
              hx-indicator="#page-loader">
            <div class="col-12 col-md-4">
                <input class="form-control" type="search" name="nama" placeholder="Cari Nama Santri..." value="<?= htmlspecialchars($filters['nama']) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kelas" placeholder="Filter Kelas..." value="<?= htmlspecialchars($filters['kelas']) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kamar" placeholder="Filter Kamar..." value="<?= htmlspecialchars($filters['kamar']) ?>">
            </div>
            <div class="col-12 col-md-2 filter-buttons">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Cari</button>
                <a href="<?= BASE_URL ?>/santri?reset=1" class="btn btn-outline-secondary" id="resetFilterBtn" title="Reset Filter"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/santri/bulk-delete" id="bulkDeleteForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="card-action-bulk">
            <div class="row g-2 align-items-center">
                <!-- Group Kiri: Tambah & Bulk Input -->
                <div class="col-12 col-md-auto d-flex gap-2 flex-grow-1">
                    <?php if ($can_create): ?>
                        <a href="<?= BASE_URL ?>/santri/create" class="btn btn-success flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-user-plus me-1"></i> Tambah Santri
                        </a>
                        <a href="<?= BASE_URL ?>/santri/bulk-create" class="btn btn-info text-white flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-file-import me-1"></i> Bulk Input
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Group Kanan: Bulk Edit & Hapus -->
                <div class="col-12 col-md-auto d-flex gap-2 justify-content-md-end align-items-center">
                    <div id="selected-count-info" class="d-none me-2"></div>
                    <?php if ($can_edit): ?>
                        <button type="button" class="btn btn-warning flex-grow-1 flex-md-grow-0 fw-medium text-dark" id="bulkEditBtn" disabled>
                            <i class="fas fa-pen-to-square me-1"></i> Bulk Edit
                        </button>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <button type="button" class="btn btn-danger flex-grow-1 flex-md-grow-0 fw-medium" id="bulkDeleteBtn" disabled>
                            <i class="fas fa-user-minus me-1"></i> Hapus Terpilih
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="table-data-wrapper">
            <?php include __DIR__ . '/_table.php'; ?>
        </div>
    </form>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Informasi Penting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="fw-bold">Sebelum mengubah (update) atau menghapus data santri, harap perhatikan hal-hal berikut:</p>
        <ul>
            <li><strong>Pastikan Data Telah Diarsipkan:</strong> Jika Anda menghapus santri sebelum diarsip, data pelanggaran tidak akan terekam dalam arsip.</li>
            <li><strong>Data Permanen:</strong> Data yang dihapus tidak dapat dikembalikan.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Checkbox Pinter (bisa menggunakan alpine.js atau JS vanilla seperti sebelumnya)
    // Di sini kita biarkan HTMX handle AJAX, tapi JS handle Checkbox UI.
    
    const STORAGE_KEY = 'selectedSantriIds'; 
    let rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkEditBtn = document.getElementById('bulkEditBtn');
    const selectedCountInfo = document.getElementById('selected-count-info');
    
    function getStoredIds() {
        const storedIdsJson = sessionStorage.getItem(STORAGE_KEY);
        return storedIdsJson ? new Set(JSON.parse(storedIdsJson)) : new Set();
    }
    
    function saveStoredIds(idsSet) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...idsSet]));
    }
    
    function loadSelections() {
        const selectedIds = getStoredIds();
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value); 
            checkbox.checked = selectedIds.has(checkboxId);
        });
    }

    function handleSelectionChange() {
        const selectedIds = getStoredIds();
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value);
            if (checkbox.checked) selectedIds.add(checkboxId);
            else selectedIds.delete(checkboxId);
        });
        saveStoredIds(selectedIds);
        toggleActionButtons();
    }

    function toggleActionButtons() {
        const selectedIds = getStoredIds();
        const count = selectedIds.size;
        
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = count === 0;
        if (bulkEditBtn) bulkEditBtn.disabled = count === 0;

        if (selectedCountInfo) {
            if (count > 0) {
                selectedCountInfo.innerHTML = `<span class="badge bg-secondary">${count} data terpilih</span>`;
                selectedCountInfo.classList.remove('d-none');
            } else {
                selectedCountInfo.classList.add('d-none');
            }
        }
        
        const allVisible = document.querySelectorAll('.row-checkbox');
        if (selectAllCheckbox && allVisible.length > 0) {
             const checkedVisible = document.querySelectorAll('.row-checkbox:checked').length;
             selectAllCheckbox.checked = allVisible.length === checkedVisible;
        }
    }

    if (bulkEditBtn) {
        bulkEditBtn.addEventListener('click', function() {
            window.location.href = '<?= BASE_URL ?>/santri/bulk-edit';
        });
    }

    document.body.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'selectAll') {
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
            handleSelectionChange();
        }
        if (e.target && e.target.classList.contains('row-checkbox')) {
            handleSelectionChange();
        }
    });

    // Rebind after HTMX swap
    document.body.addEventListener('htmx:afterSwap', function(e) {
        if (e.detail.target.id === 'table-data-wrapper') {
            loadSelections();
            toggleActionButtons();
            
            // update total badge
            const newTotal = e.detail.target.querySelector('#new-total');
            if (newTotal) {
                document.getElementById('total-santri').innerText = newTotal.value + ' Santri';
            }
        }
    });

    loadSelections();
    toggleActionButtons();
    
    if (document.getElementById('resetFilterBtn')) {
        document.getElementById('resetFilterBtn').addEventListener('click', function() {
            sessionStorage.removeItem(STORAGE_KEY);
        });
    }
});
</script>
