<footer class="mt-auto mb-5 text-center text-muted small pb-4">
    &copy; 2025 Built by <a href="https://ajsk.vercel.app/" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-primary">AJSK.</a>
</footer>
</main>

<?php
// Deteksi path aktif untuk Bottom Nav
$project_path = defined('BASE_URL') ? parse_url(BASE_URL, PHP_URL_PATH) : '';
$req_path = str_replace($project_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$req_path = '/' . ltrim($req_path, '/');

// Cek izin untuk menu bottom nav
$can_input_violation = false;
$can_input_reward = false;
$violation_url = BASE_URL . '/pelanggaran';
$reward_url = BASE_URL . '/reward/input/create.php';

if (function_exists('has_permission')) {
    $can_input_reward = has_permission('reward_input');
    $can_input_violation = has_permission([
        'pelanggaran_bahasa_input', 
        'pelanggaran_diniyyah_input', 
        'pelanggaran_kesantrian_input', 
        'pelanggaran_pengabdian_input', 
        'pelanggaran_tahfidz_input'
    ]);
    
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

$can_view_rekap = false;
if (function_exists('has_permission')) {
    $can_view_rekap = has_permission([
        'rekap_view_umum', 
        'rekap_view_per_kamar', 
        'rekap_view_santri', 
        'rekap_view_statistik', 
        'rekap_view_tahfidz'
    ]);
}
?>

<!-- --- Bottom Navigation Bar (Mobile Only) --- -->
<nav class="bottom-nav">
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= ($req_path === '/' || strpos($req_path, '/index.php') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
            <i class="fas fa-home"></i>
            <span>Beranda</span>
        </a>
    </div>
    <?php if ($can_input_violation): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/pelanggaran') === 0 ? 'active' : '' ?>" href="<?= $violation_url ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Catat</span>
        </a>
    </div>
    <?php endif; ?>
    <?php if ($can_view_rekap): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/rekap') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap">
            <i class="fas fa-chart-bar"></i>
            <span>Rekap</span>
        </a>
    </div>
    <?php endif; ?>
    <div class="bottom-nav-item">
        <button class="bottom-nav-link" type="button" onclick="openSidebarMobile()">
            <i class="fas fa-bars"></i>
            <span>Lainnya</span>
        </button>
    </div>
</nav>

<!-- --- Floating Action Button (Mobile Only) --- -->
<?php if ($can_input_violation && $can_input_reward): ?>
    <!-- Speed Dial (Keduanya) -->
    <div class="fab-container">
        <button class="fab-btn" id="fabToggle" title="Tambah Data">
            <i class="fas fa-plus"></i>
        </button>
        <div class="fab-menu" id="fabMenu">
            <a href="<?= $reward_url ?>" class="fab-item" title="Tambah Reward">
                <span class="fab-label">Reward</span>
                <div class="fab-icon bg-success text-white"><i class="fas fa-trophy"></i></div>
            </a>
            <a href="<?= $violation_url ?>" class="fab-item" title="Catat Pelanggaran">
                <span class="fab-label">Pelanggaran</span>
                <div class="fab-icon bg-danger text-white"><i class="fas fa-exclamation-triangle"></i></div>
            </a>
        </div>
    </div>
<?php elseif ($can_input_violation): ?>
    <!-- Hanya Pelanggaran -->
    <a href="<?= $violation_url ?>" class="fab-btn single-fab" title="Catat Pelanggaran">
        <i class="fas fa-plus"></i>
    </a>
<?php elseif ($can_input_reward): ?>
    <!-- Hanya Reward -->
    <a href="<?= $reward_url ?>" class="fab-btn single-fab" title="Tambah Reward" style="background: linear-gradient(135deg, var(--success) 0%, #047857 100%);">
        <i class="fas fa-trophy"></i>
    </a>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
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

    // FAB Speed Dial Toggle Logic
    const fabToggle = document.getElementById('fabToggle');
    const fabMenu = document.getElementById('fabMenu');
    
    if (fabToggle && fabMenu) {
        fabToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            fabMenu.classList.toggle('active');
            fabToggle.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (fabMenu.classList.contains('active') && !fabMenu.contains(e.target) && e.target !== fabToggle) {
                fabMenu.classList.remove('active');
                fabToggle.classList.remove('active');
            }
        });
    }
</script>
</body>
</html>