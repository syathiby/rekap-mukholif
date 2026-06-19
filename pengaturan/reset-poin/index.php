<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('reset_poin_manage');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// (Opsi Reset Per Santri telah dihapus)

// 4. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';
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
                <h1 class="display-5 fw-bold">Tutup Buku Akhir Tahun</h1>
                <p class="lead text-muted">Arsipkan data lama ke gudang dan mulai lembaran bersih untuk tahun ajaran baru.</p>
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <h4 class="mb-3">Eksekusi Tutup Buku Akhir Tahun</h4>
                         <div class="card bg-danger-subtle border border-danger rounded-4 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-archive fa-2x text-danger me-3"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title fw-bold text-danger-emphasis mb-2">Aksi Tutup Buku Otomatis</h5>
                                        <p class="card-text mb-0">
                                            Fitur ini akan secara otomatis: <br>
                                            1. <strong>Mengarsipkan</strong> seluruh data pelanggaran, kebersihan, rapot kepengasuhan (bulanan), dan rapot tahunan (dari Periode Aktif s/d Hari Ini) ke Gudang Arsip.<br>
                                            2. <strong>Menghapus</strong> seluruh data rapot (bulanan & tahunan), pelanggaran ringan-sedang, dan pelanggaran kebersihan dari laci utama.<br>
                                            3. <strong>Mempertahankan</strong> pelanggaran Sangat Berat dan seluruh Surplus Poin Reward.<br>
                                            4. <strong>Memperbarui</strong> Periode Aktif ke hari esok.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="judul_arsip" class="form-label">Judul Arsip Tahunan</label>
                            <input type="text" class="form-control" id="judul_arsip" name="judul_arsip" placeholder="Contoh: Arsip Pelanggaran Tahun Ajaran 2025/2026" required>
                        </div>

                        <div class="mb-4">
                            <label for="keterangan_semua" class="form-label">Keterangan Aktivitas</label>
                            <input type="text" class="form-control" id="keterangan_semua" name="keterangan_semua" placeholder="Contoh: Tutup Buku Kenaikan Kelas 2026" required>
                        </div>
                        <div class="d-grid">
                             <button type="submit" name="tutup_buku_massal" class="btn btn-danger rounded-pill fw-bold py-3">
                                <i class="fas fa-power-off me-2"></i>JALANKAN TUTUP BUKU SEKARANG
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
include __DIR__ . '/../../layouts/footer.php';
?>

<script>
$(document).ready(function() {
    const form = document.getElementById('resetForm');
    
    if(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Mencegah submit langsung
            const submitter = event.submitter;
            const submitName = submitter.name;
            const submitValue = submitter.value || "1";
            
            if (submitName === 'tutup_buku_massal') {
                const keteranganSemua = document.getElementById('keterangan_semua');
                const judulArsip = document.getElementById('judul_arsip');

                if (judulArsip.value.trim() === '') {
                    showAlert('Judul Arsip tidak boleh kosong!', 'warning');
                    judulArsip.focus();
                    return;
                }

                if (keteranganSemua.value.trim() === '') {
                    showAlert('Keterangan aktivitas tidak boleh kosong!', 'warning');
                    keteranganSemua.focus();
                    return;
                }
                
                Swal.fire({
                    title: 'PERINGATAN KERAS!',
                    text: 'Anda akan mengeksekusi TUTUP BUKU. Data akan diarsipkan dan pelanggaran akan direset. Tindakan ini final dan tidak dapat diurungkan. Apakah Anda 100% yakin?',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'YA, JALANKAN TUTUP BUKU!'
                }).then((result) => {
                    if(result.isConfirmed) {
                        let input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = submitName;
                        input.value = submitValue;
                        form.appendChild(input);
                        form.submit();
                    }
                });
            }
        });
    }
});
</script>