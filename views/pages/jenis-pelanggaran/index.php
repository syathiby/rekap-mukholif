<?php
use App\Helpers\AuthHelper;
$can_edit = AuthHelper::hasPermission('jenis_pelanggaran_edit');
$can_delete = AuthHelper::hasPermission('jenis_pelanggaran_delete');
$can_create = AuthHelper::hasPermission('jenis_pelanggaran_create');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bolder text-dark mb-1">
            <i class="fas fa-exclamation-triangle text-primary me-2"></i>Data Jenis Pelanggaran
        </h3>
        <p class="text-muted mb-0">Daftar klasifikasi pelanggaran dan poin</p>
    </div>
    <div class="d-flex align-items-center">
        <span class="badge bg-white border text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
            <i class="fas fa-database me-1 text-primary"></i>
            <span id="total-data">Total Data: <?= number_format($total_data ?? count($jenis_pelanggaran)) ?></span>
        </span>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-4">
        <!-- Filter and Search -->
        <form method="GET" action="<?= BASE_URL ?>/jenis-pelanggaran" class="row g-3 mb-4" hx-boost="true" hx-target="#bulkForm" hx-select="#bulkForm" hx-swap="outerHTML" hx-push-url="true" id="filterForm">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-medium">Cari Nama Pelanggaran</label>
                <input type="text" name="q" class="form-control bg-light border-0" placeholder="Ketik di sini..." value="<?= htmlspecialchars($search ?? '') ?>" onkeyup="submitFilterDelay()">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-medium">Filter Bagian</label>
                <select name="bagian" class="form-select bg-light border-0" onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true}))">
                    <option value="">-- Semua Bagian --</option>
                    <?php foreach($daftar_bagian as $bgn): ?>
                        <option value="<?= htmlspecialchars($bgn) ?>" <?= ($bagian_filter === $bgn) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bgn) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-medium">Filter Kategori</label>
                <select name="kategori" class="form-select bg-light border-0" onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true}))">
                    <option value="">-- Semua Kategori --</option>
                    <option value="Ringan" <?= ($kategori_filter === 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                    <option value="Sedang" <?= ($kategori_filter === 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                    <option value="Berat" <?= ($kategori_filter === 'Berat') ? 'selected' : '' ?>>Berat</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="btn btn-link text-muted text-decoration-none w-100 text-start" hx-boost="true">
                    <i class="fas fa-sync-alt me-1"></i> Reset
                </a>
            </div>
        </form>

        <hr class="text-light my-4">

        <!-- Form for Bulk Edit & Delete -->
        <form id="bulkForm" method="POST" action="<?= BASE_URL ?>/jenis-pelanggaran/bulk-edit" hx-boost="true">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="d-flex flex-nowrap justify-content-between align-items-center mb-3 gap-3 overflow-x-auto pb-2" style="-webkit-overflow-scrolling: touch;">
            <!-- Kiri: Tambah -->
            <div class="d-flex flex-nowrap gap-2">
                <?php if($can_create): ?>
                    <a href="<?= BASE_URL ?>/jenis-pelanggaran/create" class="btn btn-success shadow-sm text-nowrap" style="border-radius: 20px; padding: 8px 20px;" hx-boost="true">
                        <i class="fas fa-plus me-1"></i> Tambah Baru
                    </a>
                <?php endif; ?>
            </div>

            <!-- Kanan: Bulk Edit & Hapus Terpilih -->
            <div class="d-flex flex-nowrap gap-2 align-items-center">
                <?php if($can_edit): ?>
                <button type="submit" class="btn btn-warning text-dark shadow-sm text-nowrap" id="btnBulkEdit" disabled style="border-radius: 20px; padding: 8px 20px;">
                    <i class="fas fa-edit me-1"></i> Edit Terpilih
                </button>
                <?php endif; ?>
                
                <?php if($can_delete): ?>
                <button type="button" class="btn btn-danger shadow-sm text-nowrap" id="btnBulkDelete" onclick="confirmBulkDelete()" disabled style="border-radius: 20px; padding: 8px 20px;">
                    <i class="fas fa-trash-alt me-1"></i> Hapus Terpilih
                </button>
                <?php endif; ?>
            </div>
        </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr class="text-muted small">
                            <?php if($can_edit || $can_delete): ?>
                            <th style="width: 40px;" class="text-center">
                                <input class="form-check-input" type="checkbox" id="checkAll">
                            </th>
                            <?php endif; ?>
                            <th style="width: 50px;">NO</th>
                            <th>NAMA PELANGGARAN</th>
                            <th>BAGIAN</th>
                            <th class="text-center">POIN</th>
                            <th class="text-center">KATEGORI</th>
                            <?php if($can_edit || $can_delete): ?>
                            <th class="text-center">AKSI</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($jenis_pelanggaran)): ?>
                            <tr>
                                <td colspan="<?= ($can_edit || $can_delete) ? 7 : 6 ?>" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fs-1 mb-3 text-light"></i>
                                    <p class="mb-0">Tidak ada data jenis pelanggaran ditemukan.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach($jenis_pelanggaran as $jp): ?>
                            <tr>
                                <?php if($can_edit || $can_delete): ?>
                                <td class="text-center">
                                    <input class="form-check-input row-checkbox" type="checkbox" name="ids[]" value="<?= $jp['id'] ?>">
                                </td>
                                <?php endif; ?>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($jp['nama_pelanggaran']) ?></div>
                                    <div class="text-muted small mt-1">
                                        ID: <?= $jp['id'] ?>
                                        <?php if($jp['id'] <= 5): ?>
                                            <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">Default</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark"><?= strtoupper(htmlspecialchars($jp['bagian'])) ?></span>
                                </td>
                                <td class="text-center fw-bold fs-6">
                                    <?= (int)$jp['poin'] ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $kat = strtolower($jp['kategori']);
                                        $badgeColor = '#0dcaf0'; // Ringan (Cyan)
                                        $textColor = '#000';
                                        if ($kat === 'sedang') {
                                            $badgeColor = '#ffc107'; // Sedang (Yellow)
                                        } elseif ($kat === 'berat') {
                                            $badgeColor = '#dc3545'; // Berat (Red)
                                            $textColor = '#fff';
                                        }
                                    ?>
                                    <span class="badge" style="background-color: <?= $badgeColor ?>; color: <?= $textColor ?>; border-radius: 4px; padding: 6px 12px; font-weight: 500;">
                                        <?= htmlspecialchars($jp['kategori']) ?>
                                    </span>
                                </td>
                                
                                <?php if($can_edit || $can_delete): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <?php if($can_edit): ?>
                                            <a href="<?= BASE_URL ?>/jenis-pelanggaran/edit/<?= $jp['id'] ?>" class="btn btn-sm" style="background-color: #e0e7ff; color: #4f46e5; border-radius: 6px;" hx-boost="true" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($can_delete): ?>
                                            <button type="button" class="btn btn-sm" style="background-color: #ffe4e6; color: #e11d48; border-radius: 6px;" onclick="confirmDelete(<?= $jp['id'] ?>)" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
    var filterTimeout = null;
    function submitFilterDelay() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            document.getElementById('filterForm').dispatchEvent(new Event('submit', {cancelable: true}));
        }, 500);
    }

        document.addEventListener('DOMContentLoaded', function() {
        // Event delegation untuk checkbox agar tetap berfungsi setelah HTMX swap
        document.body.addEventListener('change', function(e) {
            if (e.target.id === 'checkAll') {
                const rowCheckboxes = document.querySelectorAll('.row-checkbox');
                rowCheckboxes.forEach(cb => cb.checked = e.target.checked);
                updateBulkActions();
            } else if (e.target.classList.contains('row-checkbox')) {
                const checkAll = document.getElementById('checkAll');
                const rowCheckboxes = document.querySelectorAll('.row-checkbox');
                if (!e.target.checked && checkAll) checkAll.checked = false;
                const allChecked = Array.from(rowCheckboxes).every(c => c.checked);
                if (allChecked && rowCheckboxes.length > 0 && checkAll) checkAll.checked = true;
                updateBulkActions();
            }
        });
    });

    // Panggil updateBulkActions saat htmx swap selesai untuk me-reset state tombol
    document.addEventListener('htmx:afterSwap', function(e) {
        if (e.detail.target.id === 'page-content' || e.detail.target.tagName.toLowerCase() === 'body') {
            updateBulkActions();
        }
    });

    function updateBulkActions() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        const hasChecked = count > 0;
        const btnBulkEdit = document.getElementById('btnBulkEdit');
        const btnBulkDelete = document.getElementById('btnBulkDelete');
        
        if (btnBulkEdit) {
            btnBulkEdit.disabled = !hasChecked;
            btnBulkEdit.style.opacity = hasChecked ? '1' : '0.55';
        }
        
        if (btnBulkDelete) {
            btnBulkDelete.disabled = !hasChecked;
            btnBulkDelete.style.opacity = hasChecked ? '1' : '0.55';
        }
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Aturan?',
            text: "Data yang sudah pernah digunakan oleh santri TIDAK DAPAT dihapus. Lanjutkan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                htmx.ajax('POST', `<?= BASE_URL ?>/jenis-pelanggaran/delete/${id}`, {
                    target: 'body',
                    swap: 'outerHTML',
                    values: { csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>' }
                });
            }
        });
    }

    function confirmBulkDelete() {
        Swal.fire({
            title: 'Hapus Terpilih?',
            text: "Aturan yang sudah dipakai santri akan dilewati secara otomatis.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Terpilih!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('bulkForm');
                form.action = '<?= BASE_URL ?>/jenis-pelanggaran/bulk-delete';
                form.dispatchEvent(new Event('submit', {cancelable: true}));
            }
        });
    }
</script>

