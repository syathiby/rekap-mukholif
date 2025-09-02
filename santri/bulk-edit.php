<?php
// BAGIAN 1: LOGIKA RUANG MESIN
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('santri_edit'); 

// Logika proses form HANYA jika ada request POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $ids = $_POST['santri_ids'] ?? [];
    $kelasBaru = $_POST['kelas_baru'];
    $kamarBaru = $_POST['kamar_baru'];

    if (!empty($ids) && !empty($kelasBaru) && !empty($kamarBaru)) {
        // ✅ FIX KEAMANAN: Menggunakan prepared statements untuk mencegah SQL Injection
        $query = "UPDATE santri SET kelas = ?, kamar = ? WHERE id = ?";
        $stmt = $conn->prepare($query);

        $updated_count = 0;
        foreach ($ids as $id) {
            $id = intval($id); // Pastikan ID adalah integer
            $stmt->bind_param("ssi", $kelasBaru, $kamarBaru, $id);
            if ($stmt->execute()) {
                $updated_count++;
            }
        }
        $stmt->close();
        
        $_SESSION['success_message'] = "Berhasil update data untuk $updated_count santri!";
    } else {
        $_SESSION['error_message'] = "Pilih santri dan isi kelas/kamar baru terlebih dahulu!";
    }
    
    // Redirect untuk mencegah re-submit form saat refresh
    header("Location: bulk-edit.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN
require_once __DIR__ . '/../header.php';

// Ambil semua santri
$santri = $conn->query("SELECT * FROM santri ORDER BY nama ASC");
?>

<style>
    /* Style untuk membuat tabel bisa di-scroll di mobile */
    .table-responsive {
        max-height: 60vh; /* Batasi tinggi tabel biar ga kepanjangan */
    }
    .card-footer {
        position: sticky;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.95);
        border-top: 1px solid #dee2e6;
        z-index: 10;
    }
     @media (max-width: 767px) {
        .card-body {
            padding: 1rem;
        }
        .search-filters .col-md-4 {
            margin-bottom: 0.75rem;
        }
    }
</style>

<div class="container mt-4 mb-5">
    <form method="post" action="bulk-edit.php">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-users-cog me-2"></i> Bulk Edit Kelas & Kamar</h4>
                    <span class="badge bg-light text-primary" id="selectedCount">0 dipilih</span>
                </div>
            </div>
            
            <div class="card-body">
                <!-- 🔍 Search Section -->
                <div class="row g-3 mb-4 search-filters">
                    <div class="col-md-4">
                        <input type="text" id="searchNama" class="form-control" placeholder="Cari Nama Santri...">
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="searchKelas" class="form-control" placeholder="Filter Kelas...">
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="searchKamar" class="form-control" placeholder="Filter Kamar...">
                    </div>
                </div>

                <!-- ✅ FIX SCROLL: div ini membuat tabel bisa di-scroll horizontal di mobile -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="santriTable">
                        <thead class="table-primary sticky-top">
                            <tr>
                                <th width="50px" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Nama Santri</th>
                                <th width="120px">Kelas</th>
                                <th width="120px">Kamar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $santri->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="santri_ids[]" value="<?= $row['id'] ?>" class="form-check-input santri-checkbox">
                                </td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($row['kelas']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark"><?= htmlspecialchars($row['kamar']) ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                             <tr id="noResultsRow" style="display: none;">
                                <td colspan="4" class="text-center text-muted py-4">
                                    <h5>Tidak ada santri yang cocok dengan filter.</h5>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bulk Edit Form -->
            <div class="card-footer">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-graduation-cap me-1"></i>Set Kelas Baru</label>
                        <input type="text" name="kelas_baru" class="form-control" placeholder="Contoh: 8A">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="fas fa-bed me-1"></i>Set Kamar Baru</label>
                        <input type="text" name="kamar_baru" class="form-control" placeholder="Contoh: B2">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="update" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 🔁 Script Select All & Search Filter -->
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

    function updateSelectedCount() {
        // ✅ FIX: Hanya hitung checkbox yang terlihat (tidak di-filter)
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => !cb.closest('tr').hidden);
        const selectedVisible = visibleCheckboxes.filter(cb => cb.checked).length;
        selectedCountBadge.textContent = selectedVisible + ' dipilih';

        // Update status selectAll
        if (visibleCheckboxes.length > 0) {
            selectAllCheckbox.checked = selectedVisible === visibleCheckboxes.length;
            selectAllCheckbox.indeterminate = selectedVisible > 0 && selectedVisible < visibleCheckboxes.length;
        } else {
             selectAllCheckbox.checked = false;
             selectAllCheckbox.indeterminate = false;
        }
    }

    // ✅ FIX: Select All hanya berlaku untuk item yang terlihat
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

            const isVisible = nama.includes(filters.nama) && 
                              kelas.includes(filters.kelas) && 
                              kamar.includes(filters.kamar);
            
            row.hidden = !isVisible;
            if(isVisible) visibleRows++;
        });
        
        noResultsRow.hidden = visibleRows > 0;
        updateSelectedCount(); // Hitung ulang setelah filter
    }
    
    Object.values(searchInputs).forEach(input => {
        input.addEventListener('keyup', filterTable);
    });

    // Initial count
    updateSelectedCount();
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>