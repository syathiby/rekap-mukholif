<footer class="mt-auto mb-5 text-center text-muted small pb-4">
    &copy; 2025 Built by <a href="https://ajsk.vercel.app/" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-primary">AJSK.</a>
</footer>
</main>

<?php
// Deteksi path aktif untuk Bottom Nav
$project_path = defined('BASE_URL') ? (parse_url(BASE_URL, PHP_URL_PATH) ?? '') : '';
$req_path = str_replace($project_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$req_path = '/' . ltrim($req_path, '/');

// Cek izin untuk menu bottom nav
$can_input_violation = false;
$can_input_reward = false;
$can_create_rapot = false;
$violation_url = BASE_URL . '/pelanggaran';
$reward_url = BASE_URL . '/reward/input/create.php';
$rapot_url = BASE_URL . '/rapot/index.php';

if (function_exists('has_permission')) {
    $can_input_reward = has_permission('reward_input');
    $can_input_violation = has_permission([
        'pelanggaran_bahasa_input', 
        'pelanggaran_diniyyah_input', 
        'pelanggaran_kesantrian_input', 
        'pelanggaran_pengabdian_input', 
        'pelanggaran_tahfidz_input'
    ]);
    $can_create_rapot = has_permission(['rapot_create', 'rapot_cetak']);
    
    if ($can_input_violation) {
        $permissions_list = [
            'pelanggaran_bahasa_input' => '/pelanggaran/bahasa/create.php',
            'pelanggaran_diniyyah_input' => '/pelanggaran/diniyyah/create.php',
            'pelanggaran_kesantrian_input' => '/pelanggaran/kesantrian/create.php',
            'pelanggaran_pengabdian_input' => '/pelanggaran/pengabdian/create.php',
            'pelanggaran_tahfidz_input' => '/pelanggaran/tahfidz/create.php',
        ];
        
        // Admin has all permissions, but should go to the main landing page to select
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $allowed_paths = [];
            foreach ($permissions_list as $perm => $path) {
                if (has_permission($perm)) {
                    $allowed_paths[] = BASE_URL . $path;
                }
            }
            if (count($allowed_paths) === 1) {
                $violation_url = $allowed_paths[0];
            }
        }
    }
}

// Bangun array FAB Item aktif berdasarkan izin akses
$active_fab_items = [];
if ($can_input_violation) {
    $active_fab_items[] = [
        'url' => $violation_url,
        'label' => 'Pelanggaran',
        'title' => 'Catat Pelanggaran',
        'icon' => 'fas fa-exclamation-triangle',
        'bg' => 'bg-danger'
    ];
}
if ($can_input_reward) {
    $active_fab_items[] = [
        'url' => $reward_url,
        'label' => 'Reward',
        'title' => 'Tambah Reward',
        'icon' => 'fas fa-trophy',
        'bg' => 'bg-success'
    ];
}
if ($can_create_rapot) {
    $active_fab_items[] = [
        'url' => $rapot_url,
        'label' => 'Rapot',
        'title' => 'Buat Rapot Baru',
        'icon' => 'fas fa-file-invoice',
        'bg' => 'bg-info'
    ];
}

$can_view_rekap = false;
if (function_exists('has_permission')) {
    $can_view_rekap = has_permission([
        'rekap_per_santri',
        'rekap_detail_santri',
        'rekap_kebersihan',
        'rekap_kamar',
        'rekap_view_statistik', 
        'rekap_view_tahfidz'
    ]);
}
?>

<!-- --- Bottom Navigation Bar (Mobile Only) --- -->
<nav class="bottom-nav">
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= ($req_path === '/' || strpos($req_path, '/dashboard.php') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard.php">
            <i class="fas fa-home"></i>
            <span>Beranda</span>
        </a>
    </div>
    <?php if (function_exists('has_permission') && has_permission('santri_view')): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/santri') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/santri">
            <i class="fas fa-users"></i>
            <span>Santri</span>
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Spacer untuk memberikan tempat bagi tombol FAB (Plus) di tengah -->
    <div class="bottom-nav-item fab-spacer" style="flex: 0.6; visibility: hidden; pointer-events: none;"></div>
    
    <?php if ($can_view_rekap): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/rekap') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap">
            <i class="fas fa-chart-bar"></i>
            <span>Rekap</span>
        </a>
    </div>
    <?php endif; ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/pengaturan/users/profil.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pengaturan/users/profil.php">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </div>
</nav>

<!-- --- Floating Action Button (Mobile Only) --- -->
<?php if (count($active_fab_items) > 1): ?>
    <!-- Speed Dial (Multi Item) -->
    <div class="fab-container">
        <button class="fab-btn" type="button" data-bs-toggle="modal" data-bs-target="#fabModal" title="Tambah Data">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Minimalist FAB Modal -->
    <div class="modal fade" id="fabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 shadow-lg p-2">
                <div class="modal-body text-center p-3">
                    <h6 class="mb-4 text-muted fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">PILIH TINDAKAN</h6>
                    <div class="d-flex flex-row justify-content-center flex-wrap gap-2 mb-4">
                        <?php foreach ($active_fab_items as $item): ?>
                            <a href="<?= $item['url'] ?>" class="text-decoration-none d-flex flex-column align-items-center" style="gap: 8px; flex: 1; min-width: 70px;">
                                <div class="fab-icon <?= $item['bg'] ?> text-white" style="width: 52px; height: 52px; font-size: 1.25rem;"><i class="<?= $item['icon'] ?>"></i></div>
                                <span class="fw-medium text-secondary" style="font-size: 0.85rem;"><?= $item['label'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-light rounded-circle shadow-sm border" data-bs-dismiss="modal" style="width: 44px; height: 44px; font-size: 1.1rem; color: #64748b;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php elseif (count($active_fab_items) === 1): ?>
    <!-- Single FAB -->
    <?php $single_item = $active_fab_items[0]; ?>
    <a href="<?= $single_item['url'] ?>" class="fab-btn single-fab" title="<?= $single_item['title'] ?>" style="<?= $single_item['label'] === 'Reward' ? 'background: linear-gradient(135deg, var(--success) 0%, #047857 100%);' : ($single_item['label'] === 'Rapot' ? 'background: linear-gradient(135deg, var(--info) 0%, #0284c7 100%);' : '') ?>">
        <i class="<?= $single_item['label'] === 'Reward' ? 'fas fa-trophy' : ($single_item['label'] === 'Rapot' ? 'fas fa-file-invoice' : 'fas fa-plus') ?>"></i>
    </a>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Fungsi Global untuk Konfirmasi Aksi (Hapus, dll) menggunakan SweetAlert2
    function confirmSubmit(event, element, titleText, textMessage) {
        event.preventDefault();
        
        let formToSubmit = null;
        if (element.tagName.toLowerCase() === 'form') {
            formToSubmit = element;
        } else if (element.closest('form')) {
            formToSubmit = element.closest('form');
        }
        
        Swal.fire({
            title: titleText || 'Apakah Anda Yakin?',
            text: textMessage || "Tindakan ini tidak dapat dibatalkan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Lanjutkan!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            width: '22em',
            padding: '1.2em',
            customClass: {
                popup: 'rounded-4 shadow',
                title: 'fs-6 fw-bold text-dark mb-1',
                htmlContainer: 'text-secondary small m-0',
                confirmButton: 'btn btn-danger btn-sm px-3 rounded-pill fw-medium',
                cancelButton: 'btn btn-light btn-sm border px-3 rounded-pill fw-medium text-dark me-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                if (formToSubmit) {
                    formToSubmit.submit();
                } else {
                    if (element.href) window.location.href = element.href;
                }
            }
        });
    }

    // Fungsi Global untuk Alert Pengganti native alert()
    function showAlert(textMessage, iconType = 'info') {
        let titleText = 'Informasi';
        if (iconType === 'error') titleText = 'Oops...';
        else if (iconType === 'success') titleText = 'Berhasil!';
        else if (iconType === 'warning') titleText = 'Peringatan!';

        Swal.fire({
            title: titleText,
            text: textMessage,
            icon: iconType,
            confirmButtonColor: '#4f46e5'
        });
    }

    // Fungsi Global untuk Toast
    function showToast(textMessage, iconType = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        Toast.fire({
            icon: iconType,
            title: textMessage
        });
    }

    function updateLiveTime() {
        const timeEl = document.getElementById('live-time');
        if (timeEl) {
            const now = new Date();
            timeEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }
    if (document.getElementById('live-time')) {
        setInterval(updateLiveTime, 1000);
        updateLiveTime();
    }

    // FAB Toggle logic is now handled by Bootstrap Modal

    // Global Fix: Mencegah error accessibility 'aria-hidden' di DevTools Chrome
    // saat menutup modal Bootstrap dan elemen di dalamnya masih memiliki fokus.
    document.addEventListener('hide.bs.modal', function () {
        if (document.activeElement && document.activeElement !== document.body) {
            document.activeElement.blur();
        }
    });

    // ==========================================
    // GLOBAL FLASH MESSAGE HANDLER (SweetAlert2)
    // ==========================================
    <?php if (isset($_SESSION['flash_message'])): ?>
        <?php 
            $f_type = 'success';
            $f_msg = 'Operasi berhasil.';
            if (is_array($_SESSION['flash_message'])) {
                $f_type = $_SESSION['flash_message']['type'] ?? 'success';
                $f_msg = $_SESSION['flash_message']['message'] ?? '';
            } else {
                $f_msg = $_SESSION['flash_message'];
                if (in_array(strtolower($f_msg), ['success', 'danger', 'warning', 'info', 'error'])) {
                    $f_type = strtolower($f_msg);
                    $f_msg = ($f_type == 'success') ? 'Operasi berhasil.' : 'Terjadi kesalahan.';
                }
            }
            if ($f_type == 'danger') $f_type = 'error'; // Map bootstrap danger to sweetalert error
            unset($_SESSION['flash_message']); // Bersihkan session
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast("<?= addslashes(htmlspecialchars($f_msg)) ?>", "<?= addslashes(htmlspecialchars($f_type)) ?>");
        });
    <?php endif; ?>
</script>
</body>
</html>