<?php
use App\Helpers\AuthHelper;
$can_edit = AuthHelper::hasPermission('jenis_pelanggaran_edit');
$can_delete = AuthHelper::hasPermission('jenis_pelanggaran_delete');
$can_create = AuthHelper::hasPermission('jenis_pelanggaran_create');
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="mb-1 text-dark fw-bold">
            <span class="text-primary me-1"><i class="fas fa-exclamation-triangle"></i></span> 
            Data Jenis Pelanggaran
        </h4>
        <p class="text-muted small mb-0">Daftar klasifikasi pelanggaran dan poin</p>
    </div>
    <div class="d-flex gap-2 w-100 w-md-auto justify-content-end">
        <?php if($can_create): ?>
            <a href="<?= BASE_URL ?>/jenis-pelanggaran/create" class="btn btn-primary shadow-sm text-nowrap" style="background-color: #6366f1; border-color: #6366f1; border-radius: 20px; padding: 8px 20px;" hx-boost="true">
                <i class="fas fa-plus me-1"></i> Tambah Baru
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-4">
        <!-- Filter and Search -->
        <form method="GET" action="<?= BASE_URL ?>/jenis-pelanggaran" class="row g-3 mb-4" hx-boost="true" hx-target="#page-content" hx-push-url="true" id="filterForm">
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-3">
                <div class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-center justify-content-md-start">
                    <?php if($can_edit): ?>
                    <button type="submit" class="btn btn-light border bg-white text-muted shadow-sm rounded-3 text-nowrap" id="btnBulkEdit" disabled>
                        <i class="fas fa-edit me-1"></i> Edit Terpilih
                    </button>
                    <?php endif; ?>
                    
                    <?php if($can_delete): ?>
                    <button type="button" class="btn btn-light border bg-white text-muted shadow-sm rounded-3 text-nowrap" id="btnBulkDelete" onclick="confirmBulkDelete()" disabled>
                        <i class="fas fa-trash-alt me-1"></i> Hapus Terpilih
                    </button>
                    <?php endif; ?>
                </div>
                <div class="text-muted fw-medium text-nowrap text-end w-100 w-md-auto">
                    Total Data: <?= number_format($total_data ?? count($jenis_pelanggaran)) ?>
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
        
        if(btnBulkEdit) {
            btnBulkEdit.disabled = !hasChecked;
            if(hasChecked) {
                btnBulkEdit.classList.remove('text-muted');
                btnBulkEdit.classList.add('text-primary', 'border-primary');
            } else {
                btnBulkEdit.classList.add('text-muted');
                btnBulkEdit.classList.remove('text-primary', 'border-primary');
            }
        }
        
        if(btnBulkDelete) {
            btnBulkDelete.disabled = !hasChecked;
            if(hasChecked) {
                btnBulkDelete.classList.remove('text-muted');
                btnBulkDelete.classList.add('text-danger', 'border-danger');
            } else {
                btnBulkDelete.classList.add('text-muted');
                btnBulkDelete.classList.remove('text-danger', 'border-danger');
            }
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
                    swap: 'outerHTML'
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

