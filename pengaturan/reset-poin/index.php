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

    /* Custom Modern SweetAlert Modal Styling */
    .swal2-popup.swal-modern-popup {
        border-radius: 24px !important;
        padding: 2rem !important;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
        border: 1px solid #e2e8f0 !important;
        font-family: 'Poppins', sans-serif !important;
    }
    .swal-custom-modal {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .swal-icon-badge-danger {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: #fee2e2;
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 0 0 8px #fef2f2;
        animation: pulseWarning 2s infinite;
    }
    @keyframes pulseWarning {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
        70% { box-shadow: 0 0 0 14px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .swal-custom-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }
    .swal-custom-desc {
        font-size: 0.88rem;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 1rem;
    }
    .swal-summary-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1rem 1.15rem;
        width: 100%;
        text-align: left;
        margin-bottom: 0.5rem;
    }
    .swal-summary-item {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        font-size: 0.82rem;
        color: #334155;
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }
    .swal-summary-item:last-child {
        margin-bottom: 0;
    }
    .swal-summary-item i {
        font-size: 0.88rem;
        margin-top: 2px;
        flex-shrink: 0;
    }
    .swal-modern-actions {
        width: 100% !important;
        gap: 0.75rem !important;
        margin-top: 1.25rem !important;
    }
    .swal-btn-confirm-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        font-size: 0.9rem !important;
        padding: 0.75rem 1.5rem !important;
        border-radius: 50px !important;
        border: none !important;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35) !important;
        transition: all 0.2s ease !important;
        flex: 1;
    }
    .swal-btn-confirm-danger:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 18px rgba(239, 68, 68, 0.45) !important;
    }
    .swal-btn-cancel {
        background: #f1f5f9 !important;
        color: #475569 !important;
        font-weight: 600 !important;
        font-size: 0.9rem !important;
        padding: 0.75rem 1.5rem !important;
        border-radius: 50px !important;
        border: 1px solid #e2e8f0 !important;
        transition: all 0.2s ease !important;
    }
    .swal-btn-cancel:hover {
        background: #e2e8f0 !important;
        color: #0f172a !important;
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
                                            1. <strong>Mengarsipkan (Sapu Jagat)</strong> seluruh data sejak awal Periode Aktif tanpa batas waktu (termasuk yang salah input tanggal).<br>
                                            2. <strong>Menghapus Bersih</strong> seluruh data tersebut dari laci utama, <u>kecuali</u> Pelanggaran Sangat Berat yang dipertahankan abadi.<br>
                                            3. <strong>Menghapus Bersih</strong> seluruh data rapot bulanan dan tahunan.<br>
                                            4. <strong>Mempertahankan</strong> seluruh Surplus Poin Reward (jika ada).<br>
                                            5. <strong>Memperbarui</strong> Periode Aktif ke hari esok.
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
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, function(tag) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag);
    });
}

$(document).ready(function() {
    const form = document.getElementById('resetForm');
    
    if(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Mencegah submit langsung
            const submitter = event.submitter;
            const submitName = submitter ? submitter.name : 'tutup_buku_massal';
            const submitValue = submitter ? (submitter.value || "1") : "1";
            
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

                const judulVal = judulArsip.value.trim();
                const ketVal = keteranganSemua.value.trim();
                
                Swal.fire({
                    html: `
                        <div class="swal-custom-modal">
                            <div class="swal-icon-badge-danger">
                                <i class="fas fa-box-archive"></i>
                            </div>
                            <h4 class="swal-custom-title">Konfirmasi Tutup Buku</h4>
                            <p class="swal-custom-desc">
                                Anda akan mengeksekusi <strong>Tutup Buku Akhir Tahun (Sapu Jagat)</strong>. Seluruh data aktif akan diarsipkan dan laci utama dibersihkan.
                            </p>

                            <div class="swal-summary-box">
                                <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                    <span class="text-muted small fw-semibold">Judul Arsip:</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 text-truncate" style="max-width: 220px;">${escapeHtml(judulVal)}</span>
                                </div>
                                <div class="swal-summary-item">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span><strong>Snapshot Otomatis:</strong> Pelanggaran umum, kebersihan, rapot, reward, dan riwayat bahasa tersimpan utuh di gudang arsip.</span>
                                </div>
                                <div class="swal-summary-item">
                                    <i class="fas fa-broom text-warning"></i>
                                    <span><strong>Lembaran Baru:</strong> Poin santri direset bersih untuk tahun ajaran baru (surplus reward tetap aman).</span>
                                </div>
                                <div class="swal-summary-item">
                                    <i class="fas fa-shield-alt text-danger"></i>
                                    <span><strong>Tindakan Final:</strong> Proses ini permanen dan tercatat dalam audit log sistem.</span>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-power-off me-2"></i> Ya, Jalankan Tutup Buku!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    buttonsStyling: false,
                    customClass: {
                        popup: 'swal-modern-popup',
                        actions: 'swal-modern-actions',
                        confirmButton: 'swal-btn-confirm-danger',
                        cancelButton: 'swal-btn-cancel'
                    }
                }).then((result) => {
                    if(result.isConfirmed) {
                        Swal.fire({
                            title: 'Sedang Mengeksekusi Tutup Buku...',
                            html: `
                                <div class="py-2 text-center">
                                    <p class="text-muted small mb-0">Menyalin seluruh data ke gudang arsip & menyiapkan lembaran baru.<br><strong class="text-danger">Harap tunggu, jangan menutup atau me-refresh halaman ini!</strong></p>
                                </div>
                            `,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'swal-modern-popup'
                            },
                            didOpen: () => {
                                Swal.showLoading();
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
    }
});
</script>