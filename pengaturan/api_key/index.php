<?php
// pengaturan/api_key/index.php
require_once __DIR__ . '/../../bootstrap/init.php';
guard('setup_api_ai');

$page_title = "Konfigurasi API Key Gemini";

// Flash message dari proses save
$flash = null;
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Ambil API key dari DB
$api_key_raw    = '';
$api_key_exists = false;
$db_error       = false;

try {
    $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE nama = 'gemini_api_key' LIMIT 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row !== null) {
        $api_key_raw    = $row['nilai'];
        $api_key_exists = ($api_key_raw !== '');
    }
} catch (Exception $e) {
    $db_error = true;
}

// Masking: ••••••••ABCD (4 karakter terakhir)
$api_key_masked = '';
if ($api_key_exists) {
    $len            = strlen($api_key_raw);
    $api_key_masked = str_repeat('•', max(0, $len - 4)) . substr($api_key_raw, -4);
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
    }

    .config-card {
        background-color: var(--bg-card);
        border-radius: 1rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .config-card-header {
        background-color: #f8fafc;
        border-bottom: 1px solid var(--border);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .config-card-header h5 {
        margin: 0;
        font-weight: 600;
        color: var(--text-main);
        font-size: 1.1rem;
    }

    .config-card-body {
        padding: 1.5rem;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }

    .status-badge.active {
        background-color: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-badge.inactive {
        background-color: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .status-badge.active .dot { background-color: #10b981; }
    .status-badge.inactive .dot { background-color: #f59e0b; }

    .key-display {
        background-color: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        padding: 0.875rem 1rem;
        font-family: 'Courier New', monospace;
        font-size: 1rem;
        color: var(--text-main);
        letter-spacing: 2px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .key-display span.badge {
        font-family: 'Inter', sans-serif;
        letter-spacing: normal;
        font-size: 0.7rem;
        background-color: #e2e8f0;
        color: var(--text-muted);
    }

    .input-group-custom {
        position: relative;
    }

    .input-group-custom input {
        padding-right: 3rem;
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
    }

    .input-group-custom .btn-toggle-password {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: var(--text-muted);
        z-index: 10;
    }

    .input-group-custom .btn-toggle-password:hover {
        color: var(--text-main);
    }

    /* ── Responsive fixes for small screens ─────────────────── */
    .key-display {
        overflow: hidden;
    }

    .key-display > div {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
        min-width: 0;
        font-size: 0.95rem;
        letter-spacing: 1.5px;
    }

    .key-display span.badge {
        flex-shrink: 0;
        margin-left: 0.5rem;
    }

    /* Card header: allow wrapping on very small screens */
    .config-card-header {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    /* Info list items: stack label and value on tiny screens */
    @media (max-width: 400px) {
        .config-card-header {
            padding: 1rem;
        }

        .config-card-body {
            padding: 1rem;
        }

        .list-group-item.d-flex {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 0.25rem;
        }

        .key-display > div {
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        h3.fw-bold {
            font-size: 1.3rem;
        }
    }

    @media (max-width: 576px) {
        .container-fluid.py-4.px-4 {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        .config-card-header h5 {
            font-size: 1rem;
        }

        /* Buttons full-width on mobile */
        #form-apikey .d-flex.align-items-center.gap-2 {
            flex-direction: column;
            align-items: stretch !important;
        }

        #form-apikey .d-flex.align-items-center.gap-2 .btn {
            width: 100%;
            text-align: center;
        }

        /* Breadcrumb smaller on mobile */
        .breadcrumb {
            font-size: 0.85rem;
        }

        /* Alert padding smaller on mobile */
        .alert {
            font-size: 0.875rem;
        }
    }
</style>

<div class="container-fluid py-4 px-4">
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Pengaturan</a></li>
            <li class="breadcrumb-item active" aria-current="page">Konfigurasi AI</li>
        </ol>
    </nav>

    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-robot text-primary me-2"></i>Konfigurasi API Key</h3>
            <p class="text-muted mb-0">Kelola API Key Google Gemini untuk fitur Rapor Tahunan dan otomasi narasi.</p>
        </div>
    </div>

    <!-- Alert / Notification -->
    <?php if ($flash): ?>
        <?php
        $f_type = is_array($flash) ? ($flash['type'] ?? 'info') : 'info';
        $f_msg  = is_array($flash) ? ($flash['message'] ?? '') : $flash;
        $alert_map = [
            'success' => ['alert-success', 'fa-check-circle'],
            'danger'  => ['alert-danger',  'fa-times-circle'],
            'warning' => ['alert-warning', 'fa-exclamation-triangle'],
            'info'    => ['alert-info',    'fa-info-circle'],
        ];
        [$alert_cls, $alert_icon] = $alert_map[$f_type] ?? $alert_map['info'];
        ?>
        <div class="alert <?= $alert_cls ?> alert-dismissible fade show mb-4" role="alert">
            <i class="fas <?= $alert_icon ?> me-2"></i> <?= htmlspecialchars($f_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($db_error): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-database me-2"></i> <strong>Error:</strong> Tabel <code>pengaturan</code> belum ada di database. Harap jalankan file migrasi SQL.
        </div>
    <?php endif; ?>

    <div class="row gx-3 gy-3">
        <!-- Kolom Kiri: Form & Status -->
        <div class="col-lg-8">
            <div class="config-card">
                <div class="config-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-key text-primary"></i>
                        <h5>Kredensial API</h5>
                    </div>
                    <div>
                        <?php if ($api_key_exists && !$db_error): ?>
                            <span class="status-badge active"><span class="dot"></span> Aktif</span>
                        <?php else: ?>
                            <span class="status-badge inactive"><span class="dot"></span> Belum Dikonfigurasi</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="config-card-body">
                    <?php if ($api_key_exists && !$db_error): ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-muted small text-uppercase">API Key Tersimpan</label>
                            <div class="key-display">
                                <div><?= htmlspecialchars($api_key_masked) ?></div>
                                <span class="badge rounded-pill">Tersembunyi</span>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="btn-test" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-plug me-1"></i> Test Koneksi
                                </button>
                                <div id="test-result" class="mt-2" style="display: none;"></div>
                            </div>
                        </div>
                        <hr class="text-muted opacity-25 mb-4">
                    <?php endif; ?>

                    <form action="process.php" method="POST" id="form-apikey" autocomplete="off">
                        <input type="hidden" name="action" value="save">
                        
                        <div class="mb-3">
                            <label for="api_key_input" class="form-label fw-semibold text-dark">
                                <?= $api_key_exists ? 'Ganti API Key' : 'Masukkan API Key Baru' ?>
                            </label>
                            <div class="input-group-custom">
                                <input type="password" name="api_key" id="api_key_input" class="form-control" placeholder="AIzaSy..." autocomplete="new-password" required <?= $db_error ? 'disabled' : '' ?>>
                                <button type="button" class="btn-toggle-password" id="btn-toggle-show" <?= $db_error ? 'disabled' : '' ?>>
                                    <i class="fas fa-eye" id="icon-eye"></i>
                                </button>
                            </div>
                            <div class="form-text mt-2">Dapatkan API Key dari <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-decoration-none">Google AI Studio</a>. Pastikan key memiliki akses ke model Gemini 2.0 Flash.</div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" <?= $db_error ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i> <?= $api_key_exists ? 'Perbarui Key' : 'Simpan Key' ?>
                            </button>
                            <a href="../index.php" class="btn btn-light border shadow-sm rounded-pill px-4">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Info -->
        <div class="col-lg-4">
            <div class="config-card">
                <div class="config-card-header">
                    <i class="fas fa-info-circle text-info"></i>
                    <h5>Informasi Model</h5>
                </div>
                <div class="config-card-body">
                    <p class="text-muted small mb-3">Sistem ini dikonfigurasi untuk menggunakan model Gemini AI dengan batasan kuota gratis yang berlaku.</p>
                    
                    <ul class="list-group list-group-flush mb-0 border-top-0">
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center bg-transparent">
                            <span class="small fw-semibold text-dark">Model Aktif</span>
                            <span class="badge bg-primary rounded-pill">gemini-2.5-flash</span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center bg-transparent">
                            <span class="small fw-semibold text-dark">Request Limit (Free)</span>
                            <span class="small text-muted">1.500 / hari</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-secondary small border-0 shadow-sm" style="border-radius: 1rem;">
                <i class="fas fa-shield-alt text-secondary me-2"></i> <strong>Keamanan Data:</strong> API Key disimpan terenkripsi di database dan tidak akan dicatat di log server.
            </div>
        </div>
    </div>

</div>

<script>
// Toggle show/hide password
document.getElementById('btn-toggle-show').addEventListener('click', function () {
    const input = document.getElementById('api_key_input');
    const icon  = document.getElementById('icon-eye');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

// Test koneksi via AJAX
const btnTest = document.getElementById('btn-test');
if (btnTest) {
    btnTest.addEventListener('click', async function () {
        const resultBox = document.getElementById('test-result');
        btnTest.disabled = true;
        const originalText = btnTest.innerHTML;
        btnTest.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menguji...';
        resultBox.style.display = 'none';

        try {
            const res  = await fetch('process.php?action=test');
            const data = await res.json();

            resultBox.style.display = 'block';
            if (data.success) {
                resultBox.className = 'alert alert-success py-2 px-3 small rounded-3';
                resultBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
            } else {
                resultBox.className = 'alert alert-danger py-2 px-3 small rounded-3';
                resultBox.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + data.message;
            }
        } catch (e) {
            resultBox.style.display = 'block';
            resultBox.className = 'alert alert-danger py-2 px-3 small rounded-3';
            resultBox.innerHTML = '<i class="fas fa-times-circle me-1"></i> Gagal menghubungi server.';
        }

        btnTest.disabled = false;
        btnTest.innerHTML = originalText;
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
