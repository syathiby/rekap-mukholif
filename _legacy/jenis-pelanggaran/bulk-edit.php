<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_edit'); 

// 3. Ambil data ID dari form index.php
$ids = $_POST['ids'] ?? [];

// 4. Validasi: Kalo nggak ada ID yang dikirim, tendang balik
if (empty($ids)) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Anda belum memilih data untuk diedit.'];
    header("Location: index.php");
    exit();
}

// 5. Kita butuh array ini untuk bikin dropdown
$allowed_kategori = ['Ringan', 'Sedang', 'Berat', 'Sangat Berat'];
$allowed_bagian = ['Kesantrian', 'Bahasa', 'Diniyyah', 'Pengabdian', 'Tahfidz'];

// 6. Siapin & eksekusi query buat ngambil data yang mau diedit
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$query = "SELECT * FROM jenis_pelanggaran WHERE id IN ($placeholders) ORDER BY nama_pelanggaran ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$ids);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// 7. Panggil Tampilan
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* CSS Kustom Halaman Jenis Pelanggaran (Bulk Edit) */
    .bulk-form-container .form-control,
    .bulk-form-container .form-select {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.95rem;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
    }
    .bulk-form-container .form-control:focus,
    .bulk-form-container .form-select:focus {
        background-color: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
    .bulk-form-container .form-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.85rem;
        margin-bottom: 0.4rem;
    }
    .item-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: all 0.2s;
    }
    .item-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border-color: #cbd5e1;
    }
    .id-badge {
        background-color: #f1f5f9;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        display: inline-block;
    }
    .btn-submit {
        background: #4f46e5;
        border: none;
        padding: 10px 24px;
        font-weight: 600;
        border-radius: 30px;
        transition: all 0.3s;
        color: white;
    }
    .btn-submit:hover {
        background: #4338ca;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        color: white;
    }
</style>

<div class="container-fluid py-4 px-3 px-md-4 bulk-form-container">
    
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-edit text-primary me-2"></i>Ubah Banyak Jenis Pelanggaran</h3>
            <p class="text-muted mb-0">Mengedit secara massal sejumlah <strong><?= mysqli_num_rows($result) ?></strong> data jenis pelanggaran terpilih.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <form action="process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="bulk_update" value="1">

                <?php if(mysqli_num_rows($result) > 0): ?>
                    
                    <!-- Table Column Headers (Desktop Only) -->
                    <div class="card bg-light border-0 mb-3 d-none d-lg-block" style="border-radius: 10px;">
                        <div class="card-body py-3">
                            <div class="row align-items-center text-secondary fw-bold" style="font-size: 0.85rem;">
                                <div class="col-lg-1 text-center">ID</div>
                                <div class="col-lg-5">Nama Pelanggaran</div>
                                <div class="col-lg-2">Bagian</div>
                                <div class="col-lg-2">Kategori</div>
                                <div class="col-lg-2">Poin</div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $id = $row['id']; ?>
                        
                        <div class="card item-card mb-3 bg-white">
                            <div class="card-body p-3 p-md-4">
                                <input type="hidden" name="ids[]" value="<?= $id; ?>">
                                
                                <div class="row align-items-center g-3">
                                    
                                    <!-- ID Badge -->
                                    <div class="col-lg-1 text-lg-center">
                                        <span class="id-badge">ID: <?= $id ?></span>
                                    </div>
                                    
                                    <!-- Nama Pelanggaran -->
                                    <div class="col-12 col-lg-5">
                                        <label for="nama_<?= $id ?>" class="form-label d-lg-none">Nama Pelanggaran</label>
                                        <input type="text" 
                                               id="nama_<?= $id ?>"
                                               name="nama_pelanggaran[<?= $id; ?>]" 
                                               value="<?= htmlspecialchars($row['nama_pelanggaran']); ?>" 
                                               class="form-control" 
                                               placeholder="Ketik nama pelanggaran..."
                                               required>
                                    </div>

                                    <!-- Bagian -->
                                    <div class="col-12 col-md-5 col-lg-2">
                                        <label for="bagian_<?= $id ?>" class="form-label d-lg-none">Bagian</label>
                                        <select id="bagian_<?= $id ?>" name="bagian[<?= $id; ?>]" class="form-select" required>
                                            <?php foreach ($allowed_bagian as $bagian): ?>
                                                <?php 
                                                    $selected = (strcasecmp($row['bagian'], $bagian) == 0) ? 'selected' : ''; 
                                                ?>
                                                <option value="<?= $bagian; ?>" <?= $selected ?>>
                                                    <?= $bagian; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Kategori -->
                                    <div class="col-12 col-md-4 col-lg-2">
                                        <label for="kategori_<?= $id ?>" class="form-label d-lg-none">Kategori</label>
                                        <select id="kategori_<?= $id ?>" name="kategori[<?= $id; ?>]" class="form-select" required>
                                            <?php foreach ($allowed_kategori as $kategori): ?>
                                                 <?php 
                                                    $selected = (strcasecmp($row['kategori'], $kategori) == 0) ? 'selected' : ''; 
                                                ?>
                                                <option value="<?= $kategori; ?>" <?= $selected ?>>
                                                    <?= $kategori; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Poin -->
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="poin_<?= $id ?>" class="form-label d-lg-none">Poin</label>
                                        <input type="number" 
                                               id="poin_<?= $id ?>"
                                               name="poin[<?= $id; ?>]" 
                                               value="<?= $row['poin']; ?>" 
                                               class="form-control" 
                                               min="0"
                                               required>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <!-- Action Card & Warning Alert -->
                    <div class="card border-0 shadow-sm bg-white mt-4" style="border-radius: 12px;">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                <div class="d-flex align-items-center gap-3 w-100">
                                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0; min-width: 48px;">
                                        <i class="fas fa-exclamation-triangle fs-5"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark">Informasi Perhitungan Ulang Poin</h6>
                                        <p class="mb-0 text-muted small">Mengubah nilai poin pelanggaran akan otomatis memicu perhitungan ulang total poin aktif seluruh santri yang memiliki riwayat pelanggaran tersebut.</p>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 w-100 w-md-auto justify-content-end mt-3 mt-md-0">
                                    <a href="index.php" class="btn btn-light rounded-pill px-4 py-2 fw-semibold text-secondary">
                                        Batal
                                    </a>
                                    <button type="submit" class="btn btn-submit rounded-pill px-4 py-2 fw-semibold">
                                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="alert alert-warning text-center rounded-3">
                        <i class="fas fa-exclamation-circle me-2"></i>Data tidak ditemukan. Silakan kembali dan pilih data terlebih dahulu.
                    </div>
                <?php endif; ?>

            </form>
        </div>
    </div>
</div>

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../layouts/footer.php'; 
?>