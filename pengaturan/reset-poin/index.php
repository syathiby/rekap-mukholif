<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('reset_poin_manage');

// 3. Ambil data santri yang punya poin untuk dropdown
$santri_result = mysqli_query($conn, "SELECT id, nama, poin_aktif FROM santri WHERE poin_aktif > 0 ORDER BY nama ASC");

// 4. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';
?>

<style>
    @media (max-width: 576px) {
        .display-5 {
            font-size: 2.5rem; /* Kecilkan ukuran judul utama di HP */
        }
        .btn {
            /* Pastikan tombol tidak terlalu besar di HP */
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
    }
</style>

<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">

            <header class="text-center mb-5">
                <h1 class="display-5 fw-bold">Reset Poin Pelanggaran</h1>
                <p class="lead text-muted">Mulai lembaran baru dengan me-reset poin santri, baik secara spesifik maupun periodik.</p>
            </header>

            <?php
            // Tampilkan pesan jika ada dari session
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
                // SVG Icons
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

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-lg-5">
                    <form action="process.php" method="POST" id="resetForm">
                        
                        <h4 class="mb-3">1. Reset Santri Tertentu</h4>
                        <div class="mb-3">
                            <label for="santri_id" class="form-label">Cari dan Pilih Santri</label>
                            <select class="form-select" name="santri_id" id="santri_id">
                                <option value="">-- Pilih Santri --</option>
                                <?php mysqli_data_seek($santri_result, 0); while ($santri = mysqli_fetch_assoc($santri_result)): ?>
                                <option value="<?= $santri['id'] ?>" data-nama="<?= htmlspecialchars($santri['nama']) ?>">
                                    <?= htmlspecialchars($santri['nama']) ?> (Poin: <?= $santri['poin_aktif'] ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="keterangan_satu" class="form-label">Keterangan Reset</label>
                            <input type="text" class="form-control" id="keterangan_satu" name="keterangan_satu" placeholder="Contoh: Pemutihan poin karena prestasi">
                        </div>
                        <div class="d-grid mb-5">
                            <button type="submit" name="reset_satu_santri" class="btn btn-primary rounded-pill fw-bold">
                                <i class="fas fa-user-check me-2"></i>Reset Poin Santri Ini
                            </button>
                        </div>

                        <hr class="my-5">

                        <h4 class="mb-3">2. Reset Periodik (Semua Santri)</h4>
                         <div class="card bg-danger-subtle border border-danger rounded-4 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle fa-2x text-danger me-3"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title fw-bold text-danger-emphasis mb-2">Aksi Berisiko Tinggi</h5>
                                        <p class="card-text mb-0">
                                            Opsi ini akan me-reset poin semua santri (kecuali poin 'Sangat Berat'). <strong>Gunakan dengan hati-hati dan pastikan ini adalah jadwal reset periodik.</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="keterangan_semua" class="form-label">Keterangan Reset Massal</label>
                            <input type="text" class="form-control" id="keterangan_semua" name="keterangan_semua" placeholder="Contoh: Reset Akhir Semester Ganjil 2025">
                        </div>
                        <div class="d-grid">
                             <button type="submit" name="reset_semua_poin" class="btn btn-danger rounded-pill fw-bold">
                                <i class="fas fa-bolt me-2"></i>Jalankan Reset Semua Santri
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Panggil footer DULU, baru jalankan script yang butuh JQuery
include __DIR__ . '/../../footer.php';
?>

<script>
$(function() {
    $('#santri_id').select2({
        theme: 'bootstrap-5'
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    
    if(form) {
        form.addEventListener('submit', function(event) {
            const submitter = event.submitter;
            
            if (submitter.name === 'reset_satu_santri') {
                const santriSelect = document.getElementById('santri_id');
                const keteranganSatu = document.getElementById('keterangan_satu');
                
                if (santriSelect.value === '') {
                    alert('Silakan pilih santri terlebih dahulu!');
                    event.preventDefault();
                    return;
                }
                if (keteranganSatu.value.trim() === '') {
                    alert('Keterangan untuk reset santri tidak boleh kosong!');
                    event.preventDefault();
                    return;
                }
                
                const selectedOption = santriSelect.options[santriSelect.selectedIndex];
                const selectedSantriName = selectedOption ? selectedOption.dataset.nama : 'Santri';
                
                if (!confirm(`Anda yakin ingin me-reset poin untuk santri "${selectedSantriName}"?`)) {
                    event.preventDefault();
                }
            }
            
            if (submitter.name === 'reset_semua_poin') {
                const keteranganSemua = document.getElementById('keterangan_semua');

                if (keteranganSemua.value.trim() === '') {
                    alert('Keterangan untuk reset massal tidak boleh kosong!');
                    event.preventDefault();
                    return;
                }
                
                const firstConfirm = confirm('PERINGATAN! Anda akan me-reset SEMUA poin santri. Lanjutkan?');
                if (!firstConfirm) {
                    event.preventDefault();
                    return;
                }
                
                const secondConfirm = confirm('TINDAKAN INI FINAL DAN TIDAK DAPAT DIURUNGKAN. Apakah Anda 100% yakin?');
                if (!secondConfirm) {
                    event.preventDefault();
                }
            }
        });
    }
});
</script>