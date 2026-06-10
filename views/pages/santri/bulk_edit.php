<?php
$csrf_token = csrf_generate();
?>
<style>
    .table-wrapper {
        max-height: 60vh; 
        overflow-y: auto; 
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }
    .table-wrapper thead th {
        position: sticky; 
        top: 0;
        z-index: 2; 
    }
    @media (max-width: 767.98px) {
        .card-body { padding: 1rem; }
        .card-header h4, .card-header .h4 { font-size: 1.1rem; }
        .search-filters .form-control { margin-bottom: 0.75rem; }
        .card-footer .row > div { margin-bottom: 0.75rem; }
        .card-footer .row > div:last-child { margin-bottom: 0; }
    }
</style>

<div>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0 d-none d-md-block"><i class="fas fa-users-cog me-2"></i> Bulk Edit Kelas & Kamar</h4>
                <h5 class="mb-0 d-block d-md-none"><i class="fas fa-users-cog me-2"></i> Bulk Edit</h5>
                <span class="badge bg-light text-primary" id="selectedCount">0 dipilih</span>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row search-filters">
                <div class="col-12 col-md-4">
                    <input type="text" id="searchNama" class="form-control" placeholder="Cari Nama Santri...">
                </div>
                <div class="col-12 col-md-4">
                    <input type="text" id="searchKelas" class="form-control" placeholder="Filter Kelas...">
                </div>
                <div class="col-12 col-md-4">
                    <input type="text" id="searchKamar" class="form-control" placeholder="Filter Kamar...">
                </div>
            </div>

            <div class="table-wrapper mt-3">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="santriTable">
                        <thead class="table-primary">
                            <tr>
                                <th width="50px" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Nama Santri</th>
                                <th width="100px">Kelas</th>
                                <th width="100px">Kamar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($santri as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="santri_ids_dummy[]" value="<?= $row['id'] ?>" class="form-check-input santri-checkbox" <?= in_array($row['id'], $selected_ids ?? []) ? 'checked' : '' ?>>
                                </td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['kelas']) ?></span></td>
                                <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($row['kamar']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="4" class="text-center text-muted py-4"><h5>Tidak ada santri yang cocok.</h5></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-footer bg-light">
            <form method="post" action="<?= BASE_URL ?>/santri/bulk-edit" id="updateForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div id="selectedIdsContainer"></div> 
                
                <div class="row align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-graduation-cap me-1"></i>Set Kelas Baru</label>
                        <input type="number" name="kelas_baru" class="form-control" placeholder="Kosongkan jika tidak diubah" min="1">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-bed me-1"></i>Set Kamar Baru</label>
                        <input type="number" name="kamar_baru" class="form-control" placeholder="Kosongkan jika tidak diubah" min="1">
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-grid">
                            <button type="submit" name="update" class="btn btn-primary py-2">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.santri-checkbox');
    const selectedCountBadge = document.getElementById('selectedCount');
    const searchInputs = { 
        nama: document.getElementById('searchNama'), 
        kelas: document.getElementById('searchKelas'), 
        kamar: document.getElementById('searchKamar') 
    };
    const tableRows = document.querySelectorAll("#santriTable tbody tr:not(#noResultsRow)");
    const noResultsRow = document.getElementById('noResultsRow');
    const updateForm = document.getElementById('updateForm');
    const selectedIdsContainer = document.getElementById('selectedIdsContainer');

    function syncSelectedIds() {
        selectedIdsContainer.innerHTML = '';
        checkboxes.forEach(cb => {
            if (cb.checked) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'santri_ids[]';
                hiddenInput.value = cb.value;
                selectedIdsContainer.appendChild(hiddenInput);
            }
        });
    }

    function updateSelectedCount() {
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => !cb.closest('tr').hidden);
        const selectedVisible = visibleCheckboxes.filter(cb => cb.checked).length;
        selectedCountBadge.textContent = selectedVisible + ' dipilih';
        if (visibleCheckboxes.length > 0) {
            selectAllCheckbox.checked = selectedVisible === visibleCheckboxes.length;
            selectAllCheckbox.indeterminate = selectedVisible > 0 && selectedVisible < visibleCheckboxes.length;
        } else {
             selectAllCheckbox.checked = false;
             selectAllCheckbox.indeterminate = false;
        }
        syncSelectedIds(); 
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function () {
            tableRows.forEach(row => {
                if (!row.hidden) {
                    const cb = row.querySelector('.santri-checkbox');
                    if(cb) cb.checked = this.checked;
                }
            });
            updateSelectedCount();
        });
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', updateSelectedCount);
    });

    function filterTable() {
        const filters = { 
            nama: searchInputs.nama.value.toLowerCase(), 
            kelas: searchInputs.kelas.value.toLowerCase(), 
            kamar: searchInputs.kamar.value.toLowerCase() 
        };
        let visibleRows = 0;
        tableRows.forEach(row => {
            const nama = row.cells[1].innerText.toLowerCase();
            const kelas = row.cells[2].innerText.toLowerCase();
            const kamar = row.cells[3].innerText.toLowerCase();
            const isVisible = nama.includes(filters.nama) && kelas.includes(filters.kelas) && kamar.includes(filters.kamar);
            row.hidden = !isVisible;
            if(isVisible) visibleRows++;
        });
        if (noResultsRow) {
            noResultsRow.style.display = visibleRows > 0 ? 'none' : 'table-row';
        }
        updateSelectedCount();
    }
    
    Object.values(searchInputs).forEach(input => {
        input.addEventListener('keyup', filterTable);
    });

    // Tidak ada yang dipilih saat pertama buka — user memilih sendiri
    updateSelectedCount();
});
</script>
