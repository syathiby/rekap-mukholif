<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('reset_poin_manage');

// 3. Ambil data santri yang punya poin untuk dropdown
$santri_result = mysqli_query($conn, "SELECT id, nama, poin_aktif FROM santri WHERE poin_aktif > 0 ORDER BY nama ASC");

// 4. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';
?>

<!-- Library CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

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

    /* -- UI Autocomplete Fix (Soft & Clean) -- */
    .ui-autocomplete { 
        z-index: 1050; 
        max-height: 250px; 
        overflow-y: auto; 
        border-radius: 10px; 
        border: 1px solid #eee;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); /* Shadow halus */
        padding: 5px;
        background: #fff;
    }
    .ui-menu-item .ui-menu-item-wrapper.ui-state-active {
        background: #f8f9fa !important; /* Abu muda soft */
        color: #212529 !important;
        border: none !important;
        border-radius: 6px;
    }
    .ui-menu-item {
        margin-bottom: 2px;
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
                        
                        <h4 class="mb-3">1. Reset Santri Terpilih</h4>
                        <div class="mb-4">
                            <label class="form-label">Cari Nama Santri</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                                <input type="text" id="santri-search" class="form-control border-start-0 ps-2" placeholder="Ketik nama santri...">
                            </div>
                        </div>

                        <!-- 4. Tabel Daftar -->
                        <div class="table-responsive border rounded-3 mb-4">
                            <table class="table table-hover mb-0" id="tabel-santri-reset">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Nama Santri</th>
                                        <th class="d-none d-md-table-cell">Kelas</th>
                                        <th class="d-none d-md-table-cell">Kamar</th>
                                        <th class="text-center" width="60"><i class="fas fa-trash-alt"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data masuk via JS -->
                                </tbody>
                            </table>
                            <!-- State Kosong -->
                            <div id="empty-table-message" class="text-center py-5 text-muted">
                                <div class="mb-2 opacity-25">
                                    <i class="fas fa-user-plus fa-3x"></i>
                                </div>
                                <small>Belum ada santri ditambahkan.</small>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="keterangan_satu" class="form-label">Keterangan Reset</label>
                            <input type="text" class="form-control" id="keterangan_satu" name="keterangan_satu" placeholder="Contoh: Pemutihan poin karena prestasi">
                        </div>
                        <div class="d-grid mb-5">
                            <button type="submit" name="reset_satu_santri" class="btn btn-primary rounded-pill fw-bold">
                                <i class="fas fa-users-cog me-2"></i>Reset Poin Santri Terpilih
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
include __DIR__ . '/../../layouts/footer.php';
?>

<!-- Scripts -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    let selectedSantri = null;

    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return $('<div>').text(str).html();
    }

    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            tambahSantri(); // Auto Add Trigger
            $(this).val('');
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append(`
                <div class='py-2 px-3 border-bottom'>
                    <div class='fw-semibold text-dark'>${item.value}</div>
                    <small class='text-muted'>Kelas: ${item.kelas} • Kamar: ${item.kamar} • Poin: ${item.poin}</small>
                </div>
            `)
            .appendTo(ul);
    };

    function checkTableEmpty() {
        if ($('#tabel-santri-reset tbody tr').length === 0) {
            $('#empty-table-message').show();
            $('#tabel-santri-reset').parent().addClass('d-none'); // Hide wrapper border
        } else {
            $('#empty-table-message').hide();
            $('#tabel-santri-reset').parent().removeClass('d-none').show();
        }
    }
    
    // Global function
    window.checkTableEmpty = checkTableEmpty;
    checkTableEmpty(); // Init state

    function tambahSantri() {
        if (!selectedSantri) return;

        if ($('#tabel-santri-reset').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            showAlert('Santri ini sudah masuk daftar!', 'warning');
            return;
        }

        let namaSantri = escapeHTML(selectedSantri.value);
        let kelasSantri = escapeHTML(selectedSantri.kelas);
        let kamarSantri = escapeHTML(selectedSantri.kamar);
        let poinSantri = escapeHTML(selectedSantri.poin);

        let barisBaru = `
            <tr data-id="${selectedSantri.id}">
                <td class="ps-3">
                    <div class="fw-bold text-dark">${namaSantri} <span class="badge bg-danger rounded-pill ms-2">${poinSantri} Poin</span></div>
                    <!-- Badge Mobile -->
                    <div class="d-block d-md-none mt-1">
                        <span class="badge bg-light text-dark border me-1">Kelas: ${kelasSantri}</span>
                        <span class="badge bg-light text-dark border">Kamar: ${kamarSantri}</span>
                    </div>
                    <input type="hidden" name="santri_id[]" value="${selectedSantri.id}">
                </td>
                <td class="d-none d-md-table-cell align-middle text-muted">${kelasSantri}</td>
                <td class="d-none d-md-table-cell align-middle text-muted">${kamarSantri}</td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="$(this).closest('tr').remove(); checkTableEmpty();" title="Hapus">
                        <i class="fas fa-times-circle fa-lg"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-reset tbody').append(barisBaru);
        selectedSantri = null;
        checkTableEmpty(); 
    }

    const form = document.getElementById('resetForm');
    
    if(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Mencegah submit langsung
            const submitter = event.submitter;
            const submitName = submitter.name;
            const submitValue = submitter.value || "1";
            
            if (submitName === 'reset_satu_santri') {
                const rowCount = $('#tabel-santri-reset tbody tr').length;
                const keteranganSatu = document.getElementById('keterangan_satu');
                
                if (rowCount === 0) {
                    showAlert('Silakan pilih setidaknya satu santri terlebih dahulu!', 'warning');
                    $('#santri-search').focus();
                    return;
                }
                if (keteranganSatu.value.trim() === '') {
                    showAlert('Keterangan untuk reset santri tidak boleh kosong!', 'warning');
                    keteranganSatu.focus();
                    return;
                }
                
                Swal.fire({
                    title: 'Konfirmasi Reset',
                    text: `Anda yakin ingin me-reset poin untuk ${rowCount} santri yang dipilih?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Reset!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        let input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = submitName;
                        input.value = submitValue;
                        form.appendChild(input);
                        form.submit();
                    }
                });
            }
            
            if (submitName === 'reset_semua_poin') {
                const keteranganSemua = document.getElementById('keterangan_semua');

                if (keteranganSemua.value.trim() === '') {
                    showAlert('Keterangan untuk reset massal tidak boleh kosong!', 'warning');
                    keteranganSemua.focus();
                    return;
                }
                
                Swal.fire({
                    title: 'PERINGATAN KERAS!',
                    text: 'Anda akan me-reset SEMUA poin santri. Tindakan ini final dan tidak dapat diurungkan. Apakah Anda 100% yakin?',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'YA, SAYA YAKIN RESET SEMUA!'
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