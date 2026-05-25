<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('eksekusi_manage'); 

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
        <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm" style="width: 56px; height: 56px; background: linear-gradient(135deg, #a78bfa, #8b5cf6); color: white;">
            <i class="fas fa-broom fa-xl"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Eksekusi Kebersihan</h3>
            <p class="text-muted mb-0">Kelola dan catat eksekusi sanksi kamar secara kolektif</p>
        </div>
    </div>

    <!-- Kotak Utama Konten -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
        <div class="card-body p-4">
            <form action="process.php" method="POST">
                
                <h5 class="fw-bold mb-3"><i class="fas fa-list-check text-primary me-2"></i>Daftar Pelanggaran (Belum Dieksekusi)</h5>
                
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle" style="border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <thead style="background: linear-gradient(90deg, #8b5cf6, #6366f1); color: white;">
                            <tr>
                                <th width="5%" class="text-center border-0 py-3">No</th>
                                <th width="5%" class="text-center border-0 py-3"><i class="fas fa-check-square"></i></th>
                                <th class="border-0 py-3">Kamar</th>
                                <th class="border-0 py-3">Tanggal Pelanggaran</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = 1;
                        $adaData = false;
                        while ($row = mysqli_fetch_assoc($pelanggaranQuery)):
                            $adaData = true;
                        ?>
                            <tr style="transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f5f3ff'" onmouseout="this.style.backgroundColor='transparent'">
                                <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                                <td class="text-center">
                                    <input type="checkbox" name="pelanggaran_id[]" value="<?= $row['pelanggaran_id'] ?>" class="form-check-input border-secondary" style="cursor: pointer; width: 1.25rem; height: 1.25rem;">
                                </td>
                                <td class="fw-bold text-dark"><i class="fas fa-door-closed text-primary me-2 opacity-75"></i><?= htmlspecialchars($row['kamar']) ?></td>
                                <td>
                                    <span class="badge bg-light text-secondary border px-2 py-1"><i class="far fa-clock me-1 text-warning"></i><?= date('d M Y H:i', strtotime($row['tanggal_pelanggaran'])) ?></span>
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

                <div class="row g-4 p-4 rounded-4" style="background: rgba(99, 102, 241, 0.04); border: 1px dashed rgba(99, 102, 241, 0.3);">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-primary mb-2" style="font-size:0.9rem;">Jenis Hukuman <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-primary"><i class="fas fa-gavel"></i></span>
                            <input type="text" name="jenis_hukuman" class="form-control border-start-0 ps-0" required placeholder="Contoh: Membersihkan selokan">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-teal mb-2" style="font-size:0.9rem;">Tanggal Eksekusi <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-teal"><i class="far fa-calendar-check"></i></span>
                            <input type="datetime-local" name="tanggal" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-warning mb-2" style="font-size:0.9rem;">Catatan (Opsional)</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-warning"><i class="fas fa-sticky-note"></i></span>
                            <input type="text" name="catatan" class="form-control border-start-0 ps-0" placeholder="Tambahan info...">
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded-pill shadow" style="background: linear-gradient(90deg, #6366f1, #8b5cf6); border: none;">
                        <i class="fas fa-save me-2"></i>Simpan Eksekusi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; mysqli_close($conn); ?>