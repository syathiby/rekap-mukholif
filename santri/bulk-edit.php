<?php
// BAGIAN 1: LOGIKA RUANG MESIN (TIDAK ADA PERUBAHAN)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_edit'); 

// Logika proses form HANYA jika ada request POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $ids = $_POST['santri_ids'] ?? [];
    $kelasBaru_input = trim($_POST['kelas_baru'] ?? '');
    $kamarBaru_input = trim($_POST['kamar_baru'] ?? '');

    if (!empty($ids) && (!empty($kelasBaru_input) || !empty($kamarBaru_input))) {
        
        $set_clauses = []; 
        $params = [];      
        $types = '';       

        if (!empty($kelasBaru_input)) {
            $set_clauses[] = "kelas = ?";
            $params[] = intval($kelasBaru_input);
            $types .= 'i';
        }

        if (!empty($kamarBaru_input)) {
            $set_clauses[] = "kamar = ?";
            $params[] = intval($kamarBaru_input);
            $types .= 'i';
        }

        $query = "UPDATE santri SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $stmt = $conn->prepare($query);

        $id_penampung = 0;
        $types .= 'i'; 
        
        $all_params_ref = [];
        foreach ($params as $key => $value) {
            $all_params_ref[$key] = &$params[$key];
        }
        $all_params_ref[] = &$id_penampung;

        $stmt->bind_param($types, ...$all_params_ref);

        $updated_count = 0;
        foreach ($ids as $id) {
            $id_penampung = intval($id);
            if ($stmt->execute()) {
                $updated_count++;
            }
        }
        $stmt->close();
        
        if ($updated_count > 0) {
            $_SESSION['success_message'] = "Berhasil update data untuk $updated_count santri!";
        } else {
            $_SESSION['error_message'] = "Terjadi kesalahan, tidak ada data santri yang diperbarui.";
        }
    } else {
        $_SESSION['error_message'] = "Pilih santri dan isi minimal salah satu kolom (kelas atau kamar)!";
    }
    
    header("Location: bulk-edit.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN
require_once __DIR__ . '/../header.php';

// Ambil semua santri
$santri = $conn->query("SELECT * FROM santri ORDER BY nama ASC");
?>

<style>
    /* ================================
       REVISI CSS UNTUK TAMPILAN BARU
       ================================
    */
    /* [PENTING] Ini yang bikin tabel bisa scroll atas-bawah */
    .table-wrapper {
        max-height: 60vh; /* Batasi tinggi tabel, sesuaikan angkanya jika perlu */
        overflow-y: auto; /* Tampilkan scrollbar vertikal jika konten melebihi max-height */
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }
    .table-wrapper thead th {
        position: sticky; /* Bikin header tabel nempel di atas saat di-scroll */
        top: 0;
        z-index: 2; /* Pastikan header di atas konten tbody */
    }

    /* [DIHAPUS] Kita tidak butuh footer yang nempel lagi */
    /*
    .card-footer {
        position: sticky;
        bottom: 0;
    }
    */
    
    /* Responsive umum, tetap dipertahankan */
    @media (max-width: 767.98px) {
        .card-body {
            padding: 1rem;
        }
        .card-header h4, .card-header .h4 {
            font-size: 1.1rem;
        }
        .search-filters .form-control {
            margin-bottom: 0.75rem;
        }
        .card-footer .row > div {
            margin-bottom: 0.75rem;
        }
        .card-footer .row > div:last-child {
            margin-bottom: 0;
        }
    }
</style>

<div class="container-fluid p-3 p-md-4">
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

            <form method="post" action="bulk-edit.php" id="mainForm">
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
                                <?php while ($row = $santri->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="santri_ids[]" value="<?= $row['id'] ?>" class="form-check-input santri-checkbox" form="mainForm">
                                    </td>
                                    <td><?= htmlspecialchars($row['nama']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['kelas']) ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($row['kamar']) ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                                <tr id="noResultsRow" style="display: none;">
                                    <td colspan="4" class="text-center text-muted py-4"><h5>Tidak ada santri yang cocok.</h5></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-footer bg-light">
            <form method="post" action="bulk-edit.php" id="updateForm">
                <div id="selectedIdsContainer"></div> 
                
                <div class="row align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-graduation-cap me-1"></i>Set Kelas Baru</label>
                        <input type="number" name="kelas_baru" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-bed me-1"></i>Set Kamar Baru</label>
                        <input type="number" name="kamar_baru" class="form-control" placeholder="Kosongkan jika tidak diubah">
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
    // ... (deklarasi variabel search, dll tetap sama)
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.santri-checkbox');
    const selectedCountBadge = document.getElementById('selectedCount');
    const searchInputs = { nama: document.getElementById('searchNama'), kelas: document.getElementById('searchKelas'), kamar: document.getElementById('searchKamar') };
    const tableRows = document.querySelectorAll("#santriTable tbody tr:not(#noResultsRow)");
    const noResultsRow = document.getElementById('noResultsRow');
    const updateForm = document.getElementById('updateForm');
    const selectedIdsContainer = document.getElementById('selectedIdsContainer');

    function syncSelectedIds() {
        // [BARU] Fungsi untuk menyalin ID yang dicentang ke form bawah
        selectedIdsContainer.innerHTML = ''; // Kosongkan dulu
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
        // ... (fungsi ini tetap sama)
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
        syncSelectedIds(); // [PANGGIL FUNGSI BARU] Panggil sinkronisasi setiap ada perubahan
    }

    // ... (event listener untuk filter dan select all tetap sama)
    selectAllCheckbox.addEventListener('click', function () {
        tableRows.forEach(row => {
            if (!row.hidden) {
                const cb = row.querySelector('.santri-checkbox');
                if(cb) cb.checked = this.checked;
            }
        });
        updateSelectedCount();
    });
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', updateSelectedCount);
    });

    function filterTable() {
        const filters = { nama: searchInputs.nama.value.toLowerCase(), kelas: searchInputs.kelas.value.toLowerCase(), kamar: searchInputs.kamar.value.toLowerCase() };
        let visibleRows = 0;
        tableRows.forEach(row => {
            const nama = row.cells[1].innerText.toLowerCase();
            const kelas = row.cells[2].innerText.toLowerCase();
            const kamar = row.cells[3].innerText.toLowerCase();
            const isVisible = nama.includes(filters.nama) && kelas.includes(filters.kelas) && kamar.includes(filters.kamar);
            row.hidden = !isVisible;
            if(isVisible) visibleRows++;
        });
        noResultsRow.hidden = visibleRows > 0;
        updateSelectedCount();
    }
    
    Object.values(searchInputs).forEach(input => {
        input.addEventListener('keyup', filterTable);
    });

    // Panggil saat pertama kali load
    updateSelectedCount();
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>