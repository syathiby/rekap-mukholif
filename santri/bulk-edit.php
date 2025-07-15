<?php
include '../header.php';
include '../db.php';

// Ambil semua santri
$santri = $conn->query("SELECT * FROM santri ORDER BY nama ASC");
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-users-cog me-2"></i> Bulk Edit Kelas & Kamar Santri</h4>
        <span class="badge bg-light text-primary" id="selectedCount">0 dipilih</span>
      </div>
    </div>
    
    <div class="card-body">
      <!-- 🔍 Search Section -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
            <input type="text" id="searchNama" class="form-control" placeholder="Cari Nama Santri">
          </div>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fas fa-graduation-cap"></i></span>
            <input type="text" id="searchKelas" class="form-control" placeholder="Cari Kelas">
          </div>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fas fa-bed"></i></span>
            <input type="text" id="searchKamar" class="form-control" placeholder="Cari Kamar">
          </div>
        </div>
      </div>

      <form method="post" action="">
        <div class="table-responsive">
          <table class="table table-hover table-striped" id="santriTable">
            <thead class="table-primary">
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
            </tbody>
          </table>
        </div>

        <!-- Bulk Edit Form -->
        <div class="card mt-4 border-primary">
          <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Massal</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label"><i class="fas fa-graduation-cap me-2"></i>Kelas Baru</label>
                <input type="text" name="kelas_baru" class="form-control" placeholder="Contoh: 8A" required>
              </div>
              <div class="col-md-4">
                <label class="form-label"><i class="fas fa-bed me-2"></i>Kamar Baru</label>
                <input type="text" name="kamar_baru" class="form-control" placeholder="Contoh: B2" required>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" name="update" class="btn btn-primary w-100 py-2">
                  <i class="fas fa-save me-2"></i>Simpan Perubahan
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>

      <?php
      if (isset($_POST['update'])) {
          $ids = $_POST['santri_ids'] ?? [];
          $kelasBaru = htmlspecialchars($_POST['kelas_baru']);
          $kamarBaru = htmlspecialchars($_POST['kamar_baru']);

          if (!empty($ids)) {
              $idList = implode(',', array_map('intval', $ids));
              $update = $conn->query("UPDATE santri SET kelas = '$kelasBaru', kamar = '$kamarBaru' WHERE id IN ($idList)");

              if ($update) {
                  echo '<div class="alert alert-success mt-3 d-flex align-items-center">
                          <i class="fas fa-check-circle me-2 fs-4"></i>
                          <div>
                            <h5 class="mb-1">Update Berhasil!</h5>
                            <p class="mb-0">Berhasil update kelas ke <strong>'.$kelasBaru.'</strong> dan kamar ke <strong>'.$kamarBaru.'</strong> untuk '.count($ids).' santri.</p>
                          </div>
                        </div>';
              } else {
                  echo '<div class="alert alert-danger mt-3 d-flex align-items-center">
                          <i class="fas fa-exclamation-circle me-2 fs-4"></i>
                          <div>
                            <h5 class="mb-1">Gagal Update</h5>
                            <p class="mb-0">'.$conn->error.'</p>
                          </div>
                        </div>';
              }
          } else {
              echo '<div class="alert alert-warning mt-3 d-flex align-items-center">
                      <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                      <div>
                        <h5 class="mb-1">Tidak Ada Santri Dipilih</h5>
                        <p class="mb-0">Silakan pilih santri yang ingin diubah terlebih dahulu.</p>
                      </div>
                    </div>';
          }
      }
      ?>
    </div>
  </div>
</div>

<!-- 🔁 Script Select All & Search Filter -->
<script>
  // Update selected count
  function updateSelectedCount() {
    const selected = document.querySelectorAll('input.santri-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selected + ' dipilih';
  }

  // Select all checkbox
  document.getElementById('selectAll').addEventListener('click', function () {
    const checkboxes = document.querySelectorAll('input.santri-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
  });

  // Individual checkbox click
  document.querySelectorAll('input.santri-checkbox').forEach(checkbox => {
    checkbox.addEventListener('click', updateSelectedCount);
  });

  // Filter table
  function filterTable() {
    let inputNama = document.getElementById('searchNama').value.toLowerCase();
    let inputKelas = document.getElementById('searchKelas').value.toLowerCase();
    let inputKamar = document.getElementById('searchKamar').value.toLowerCase();

    let rows = document.querySelectorAll("#santriTable tbody tr");

    rows.forEach(row => {
      let nama = row.cells[1].innerText.toLowerCase();
      let kelas = row.cells[2].innerText.toLowerCase();
      let kamar = row.cells[3].innerText.toLowerCase();

      let matchNama = nama.includes(inputNama);
      let matchKelas = kelas.includes(inputKelas);
      let matchKamar = kamar.includes(inputKamar);

      row.style.display = (matchNama && matchKelas && matchKamar) ? "" : "none";
    });
  }

  // Add debounce to search
  function debounce(func, timeout = 300){
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => { func.apply(this, args); }, timeout);
    };
  }

  const searchInputs = ['searchNama', 'searchKelas', 'searchKamar'];
  searchInputs.forEach(id => {
    document.getElementById(id).addEventListener('keyup', debounce(filterTable));
  });

  // Initialize count
  updateSelectedCount();
</script>

<?php include '../footer.php'; ?>