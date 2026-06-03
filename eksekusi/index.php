<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('eksekusi_manage'); 

// Generate CSRF Token
$csrf_token = csrf_generate();

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
?>

<?php

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// LANGKAH 1: Ambil periode aktif (copy dari dashboard)
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row ? $row['nilai'] : '2000-01-01'; // default biar gak error


// Ambil pelanggaran yang belum dieksekusi
$pelanggaranQuery = mysqli_query($conn, "
    SELECT pk.id AS pelanggaran_id, pk.kamar, pk.tanggal AS tanggal_pelanggaran
    FROM pelanggaran_kebersihan pk
    LEFT JOIN eksekusi_kebersihan ek ON ek.pelanggaran_id = pk.id
    WHERE ek.id IS NULL 
      AND pk.kamar IS NOT NULL 
      AND pk.kamar != ''
      AND pk.tanggal >= '$periode_aktif' -- LANGKAH 2: Tambahkan filter periode_aktif di sini
    ORDER BY
        REGEXP_REPLACE(pk.kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(pk.kamar, '[^0-9]', '') AS UNSIGNED) ASC,
        pk.tanggal ASC
");
?>

<div class="dashboard-wrapper">
    
    <!-- Header Page yang Dilepas dari Card Utama -->
    <div class="d-flex align-items-center mb-4 mt-2 px-1">
        <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; min-width: 56px; min-height: 56px; background: linear-gradient(135deg, #a78bfa, #8b5cf6); color: white;">
            <i class="fas fa-broom fa-xl"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Eksekusi Kebersihan</h3>
            <p class="text-muted mb-0">Kelola dan catat eksekusi sanksi kamar secara kolektif</p>
        </div>
    </div>

    <form action="process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <h5 class="fw-bold mb-3"><i class="fas fa-list-check text-primary me-2"></i>Daftar Pelanggaran (Belum Dieksekusi)</h5>
                
                <div class="card-premium mb-4 border rounded-3">
                    <div class="table-responsive">
                        <table class="table-premium w-100 mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">No</th>
                                    <th class="text-center" style="width: 50px;">
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input class="form-check-input" type="checkbox" id="checkAll" style="cursor: pointer;">
                                        </div>
                                    </th>
                                    <th class="text-nowrap">Kamar</th>
                                    <th class="text-nowrap">Waktu Pelanggaran</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = 1;
                            $adaData = false;
                            while ($row = mysqli_fetch_assoc($pelanggaranQuery)):
                                $adaData = true;
                            ?>
                                <tr>
                                    <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input type="checkbox" name="pelanggaran_id[]" value="<?= $row['pelanggaran_id'] ?>" class="form-check-input check-item border-secondary" style="cursor: pointer;">
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="fw-bold text-dark"><i class="fas fa-door-closed text-primary me-2 opacity-75"></i>Kamar <?= htmlspecialchars($row['kamar']) ?></div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="text-dark small"><i class="far fa-clock me-1 text-warning"></i><?= date('d M Y H:i', strtotime($row['tanggal_pelanggaran'])) ?></div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (!$adaData): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="far fa-smile-beam fa-3x mb-3 text-success opacity-50"></i><br>
                                        Semua pelanggaran kebersihan sudah dieksekusi.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card bg-white border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-body p-3 p-md-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-cog text-secondary me-2"></i>Pengaturan Eksekusi</h6>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark mb-2" style="font-size:0.9rem;">Jenis Hukuman <span class="text-danger">*</span></label>
                                <div class="input-group-modern">
                                    <i class="fas fa-gavel text-primary"></i>
                                    <input type="text" name="jenis_hukuman" class="form-control input-modern" required placeholder="Contoh: Membersihkan selokan">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark mb-2" style="font-size:0.9rem;">Tanggal Eksekusi <span class="text-danger">*</span></label>
                                <div class="input-group-modern">
                                    <i class="far fa-calendar-check text-success"></i>
                                    <input type="datetime-local" name="tanggal" class="form-control input-modern" value="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark mb-2" style="font-size:0.9rem;">Catatan (Opsional)</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-sticky-note text-warning"></i>
                                    <input type="text" name="catatan" class="form-control input-modern" placeholder="Tambahan info...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid d-md-flex justify-content-md-end mb-4">
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded-pill shadow-sm" style="background: var(--primary); border: none;">
                        <i class="fas fa-save me-2"></i>Simpan Eksekusi
                    </button>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const checkAll = document.getElementById('checkAll');
                    const checkItems = document.querySelectorAll('.check-item');
                    if (checkAll) {
                        checkAll.addEventListener('change', function() {
                            checkItems.forEach(item => {
                                item.checked = checkAll.checked;
                            });
                        });
                    }
                });
                </script>

            </form>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; mysqli_close($conn); ?>