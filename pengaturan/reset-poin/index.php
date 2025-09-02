<?php
// Bagian PHP di atas tetap sama, tidak ada perubahan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../header.php'; // Pindahkan ini ke atas
guard('reset_poin_manage');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">

            <header class="text-center mb-5">
                <h1 class="display-5 fw-bold">Reset Poin Periodik</h1>
                <p class="lead text-muted">Mulai lembaran baru dengan me-reset poin pelanggaran santri.</p>
            </header>

            <?php
            // Tampilkan pesan jika ada dari session, dengan gaya yang lebih modern
            if (isset($_SESSION['message'])) {
                $message = $_SESSION['message'];
                $icon = $message['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
                echo "
                <div class='alert alert-{$message['type']} d-flex align-items-center rounded-3 mb-4' role='alert'>
                    <svg class='bi flex-shrink-0 me-2' width='24' height='24' role='img' aria-label='Info:'><use xlink:href='#{$icon}'/></svg>
                    <div>
                        {$message['text']}
                    </div>
                </div>
                ";
                // SVG Icons for alerts (letakkan di dekat body atau di footer)
                echo '
                <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
                    <symbol id="check-circle-fill" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </symbol>
                    <symbol id="exclamation-triangle-fill" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </symbol>
                </svg>
                ';
                unset($_SESSION['message']);
            }
            ?>

            <!-- Kartu Peringatan (Versi Baru) -->
            <div class="card bg-danger-subtle border border-danger rounded-4 mb-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                             <i class="fas fa-exclamation-triangle fa-2x text-danger me-3"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold text-danger-emphasis mb-2">Harap Dibaca dengan Seksama</h5>
                            <p class="card-text mb-0">
                                Aksi ini akan me-reset poin pelanggaran non-permanen ('Ringan', 'Sedang', 'Berat') menjadi nol. Poin dari pelanggaran 'Sangat Berat' akan tetap ada. <strong>Tindakan ini tidak bisa dibatalkan.</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kartu Eksekusi -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="card-title mb-4">Formulir Reset Poin</h3>
                    <form action="process.php" method="POST" onsubmit="return confirmReset();">
                        <div class="mb-4">
                            <label for="keterangan" class="form-label">Keterangan Reset</label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="keterangan" name="keterangan" placeholder="Contoh: Reset Akhir Semester Ganjil 2025" required>
                            <div class="form-text mt-2">Keterangan ini akan disimpan di log untuk arsip.</div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="reset_semua_poin" class="btn btn-danger btn-lg rounded-pill px-5 py-3 fw-bold">
                                <i class="fas fa-bolt me-2"></i>Jalankan Reset Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Fungsi konfirmasi dengan JavaScript yang lebih 'dramatis'
function confirmReset() {
    const keterangan = document.getElementById('keterangan').value;
    if (keterangan.trim() === '') {
        alert('Keterangan tidak boleh kosong!');
        return false;
    }
    // Menggunakan dua kali konfirmasi untuk menekankan keseriusan
    const firstConfirm = confirm('PERINGATAN! Anda akan me-reset SEMUA poin santri. Lanjutkan?');
    if (firstConfirm) {
        return confirm('TINDAKAN INI FINAL DAN TIDAK DAPAT DIURUNGKAN. Apakah Anda 100% yakin ingin melanjutkan proses reset?');
    }
    return false;
}
</script>

<?php
// Sertakan footer
include __DIR__ . '/../../footer.php';
?>