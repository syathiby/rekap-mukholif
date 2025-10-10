<?php
require_once __DIR__ . '/../header.php';
guard('arsip_export');

// Ambil semua data arsip untuk ditampilkan di dropdown (versi MySQLi)
$arsip_list = [];
$sql = "SELECT id, judul, tanggal_mulai, tanggal_selesai FROM arsip ORDER BY tanggal_mulai DESC";
$result = $conn->query($sql);

if ($result) {
    $arsip_list = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} else {
    die("Error saat mengambil daftar arsip: " . $conn->error);
}
?>

<style>
    /* Fix untuk konten yang tidak mau full-width */
    .main-content-override {
        width: 100% !important;
        max-width: none !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content-override">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">📦 Ekspor Data Arsip</h1>
            </div>

            <?php if (count($arsip_list) > 0): ?>
                
                <div class="bg-light p-3 p-md-4 rounded-3">
                    <p>Pilih periode arsip yang datanya ingin Anda unduh.</p>
                    <p class="text-muted small">Semua data terkait akan dikemas dalam satu file Excel (.xlsx) dan otomatis terunduh.</p>
                    <hr class="my-4">
                    
                    <form action="process_export_arsip.php" method="POST">
                        <div class="mb-3">
                            <label for="arsip_id" class="form-label fw-bold">Pilih Periode Arsip:</label>
                            <select class="form-select" id="arsip_id" name="arsip_id" required>
                                <option value="" selected disabled>-- Klik untuk memilih periode --</option>
                                <?php foreach ($arsip_list as $arsip): ?>
                                    <option value="<?= htmlspecialchars($arsip['id']) ?>">
                                        <?= htmlspecialchars($arsip['judul']) ?> (<?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 w-md-auto mt-3">
                            <i class="fas fa-download"></i> Ekspor ke Excel
                        </button>
                    </form>
                </div>

            <?php else: ?>
                
                <div class="alert alert-warning mt-4" role="alert">
                    <h4 class="alert-heading">Data Arsip Kosong!</h4>
                    <p>Belum ada data arsip yang bisa diekspor. Silakan buat arsip terlebih dahulu melalui menu pengarsipan.</p>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<?php
// Ganti path footer jika diperlukan
require_once __DIR__ . '/../footer.php';
?>