<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Strict Whitelist Proteksi
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pengelola'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1><p>Anda tidak memiliki akses ke halaman ini.</p>";
    exit;
}

// Get active musyrif for broadcast dropdown
$musyrif_list = [];
$res_m = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE role='musyrif' AND is_active=1 ORDER BY nama_lengkap ASC");
if($res_m) {
    while($r = mysqli_fetch_assoc($res_m)) $musyrif_list[] = $r;
}

$page_title = 'Pengelola Musyrifin';
require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ==============================
   COMMAND CENTER - RESPONSIVE UI
   ============================== */

/* Stats Cards */
.stat-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 20px;
    height: 100%;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}
.stat-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 16px 16px 0 0;
}
.stat-card.primary::after { background: #4f46e5; }
.stat-card.danger::after  { background: #ef4444; }
.stat-card.success::after { background: #22c55e; }
.stat-card.warning::after { background: #f59e0b; }

.stat-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}
.stat-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 6px;
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    float: right;
    margin-top: -4px;
}

/* Tabs */
.tab-nav-wrap {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.tab-nav-wrap::-webkit-scrollbar { display: none; }
.tab-nav-wrap .nav-pills {
    flex-wrap: nowrap;
    min-width: max-content;
    gap: 6px;
}
.tab-nav-wrap .nav-link {
    color: #475569;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
    white-space: nowrap;
}
.tab-nav-wrap .nav-link.active {
    background-color: #4f46e5;
    color: #fff;
    box-shadow: 0 4px 12px rgba(79,70,229,0.25);
}
.tab-nav-wrap .nav-link:hover:not(.active) { background: #e2e8f0; }

/* Tables & Headers */
.table-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    width: 100%;
}
.table-section-header h5 {
    font-size: clamp(0.95rem, 2.5vw, 1.15rem);
    font-weight: 700;
    margin: 0;
    color: #0f172a;
}
.section-header-badge {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #eef2ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.header-actions-group {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* Solid Button Ingatkan Massal (No Gradient) */
.btn-ingatkan-massal {
    background-color: #d97706 !important;
    color: #ffffff !important;
    border: 1px solid #b45309 !important;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 0.84rem;
    font-weight: 600;
    letter-spacing: 0.1px;
    box-shadow: 0 2px 5px rgba(217, 119, 6, 0.22);
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}
.btn-ingatkan-massal:hover {
    background-color: #b45309 !important;
    border-color: #92400e !important;
    box-shadow: 0 3px 8px rgba(180, 83, 9, 0.3);
    transform: translateY(-1px);
}
.btn-ingatkan-massal:active {
    transform: translateY(0);
}
.btn-ingatkan-massal:disabled {
    background-color: #f1f5f9 !important;
    color: #94a3b8 !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: none !important;
    cursor: not-allowed;
    transform: none !important;
}

.btn-segarkan-custom {
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.84rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.user-avatar-mini {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #e0e7ff;
    color: #4338ca;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.table th {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 14px;
}
.table td { 
    font-size: 0.875rem; 
    vertical-align: middle; 
    padding: 12px 14px;
}

/* Subtle Badges */
.bg-success-subtle { background-color: #ecfdf5 !important; }
.border-success-subtle { border-color: #a7f3d0 !important; }
.bg-danger-subtle { background-color: #fef2f2 !important; }
.border-danger-subtle { border-color: #fecaca !important; }
.bg-warning-subtle { background-color: #fffbeb !important; }
.border-warning-subtle { border-color: #fde68a !important; }

/* ── RESPONSIVE MOBILE CARD VIEW (NO HORIZONTAL SCROLL) ── */
@media (max-width: 767.98px) {
    .table-section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 14px;
    }
    .header-actions-group {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .header-actions-group .btn-ingatkan-massal,
    .header-actions-group .btn-segarkan-custom {
        width: 100% !important;
        padding: 9px 14px !important;
        font-size: 0.85rem !important;
        justify-content: center !important;
        box-sizing: border-box !important;
    }

    .table-responsive {
        overflow-x: visible !important;
        border: none !important;
        padding: 0 !important;
    }
    .table-responsive .table {
        display: block !important;
        width: 100% !important;
        min-width: 100% !important;
        margin: 0 !important;
        border: none !important;
    }
    .table-responsive .table thead {
        display: none !important;
    }
    .table-responsive .table tbody {
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        width: 100% !important;
    }

    /* Tiap Musyrif jadi Card Rapi (CSS Grid Mobile) */
    .table-responsive .table tbody tr.kinerja-card-item {
        display: grid !important;
        grid-template-columns: 1fr auto;
        grid-template-areas: 
            "user status"
            "tunggakan tunggakan"
            "action action";
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        padding: 14px !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03) !important;
        gap: 10px 8px !important;
        align-items: center !important;
    }

    .table-responsive .table tbody tr.kinerja-card-item > td {
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }

    .cell-user {
        grid-area: user;
    }
    .cell-status {
        grid-area: status;
        text-align: right;
    }
    .cell-tertunggak {
        grid-area: tunggakan;
        background: #f8fafc !important;
        border-radius: 10px !important;
        padding: 8px 10px !important;
        border: 1px solid #f1f5f9 !important;
    }
    .cell-action {
        grid-area: action;
        width: 100% !important;
        margin-top: 2px;
    }
    .cell-action .btn {
        width: 100% !important;
        padding: 8px 14px !important;
        font-size: 0.84rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* Empty state row center alignment */
    .table-responsive .table tbody tr.empty-state-row {
        display: block !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03) !important;
        padding: 20px 14px !important;
        text-align: center !important;
    }
    .table-responsive .table tbody tr.empty-state-row td {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding: 0 !important;
        border: none !important;
        width: 100% !important;
        text-align: center !important;
    }
    .table-responsive .table tbody tr.empty-state-row td::before {
        display: none !important;
        content: '' !important;
    }

    /* Standard generic mobile row for tableAktivitas / tableMusyrif */
    .table-responsive .table tbody tr:not(.kinerja-card-item):not(.empty-state-row) {
        display: block !important;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    }
    .table-responsive .table tbody tr:not(.kinerja-card-item):not(.empty-state-row) td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }
    .table-responsive .table tbody tr:not(.kinerja-card-item):not(.empty-state-row) td:last-child {
        border-bottom: none;
    }
    .table-responsive .table tbody tr:not(.kinerja-card-item):not(.empty-state-row) td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        flex-shrink: 0;
        margin-right: 8px;
    }
}

/* Main card */
.main-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow: hidden;
}
.tab-content-inner { padding: 20px; }
@media (min-width: 768px) { .tab-content-inner { padding: 28px; } }

/* Page header */
.page-header-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}
.page-header-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1.3rem;
    box-shadow: 0 4px 14px rgba(79,70,229,0.35);
    flex-shrink: 0;
}

/* Broadcast form */
.broadcast-form-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
}
.musyrif-checkbox-list {
    max-height: 160px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.musyrif-checkbox-list::-webkit-scrollbar { width: 4px; }
.musyrif-checkbox-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.form-check-input:checked { background-color: #4f46e5; border-color: #4f46e5; }

/* Padding adjustments */
.py-mobile { padding-top: 16px; padding-bottom: 16px; }
@media (min-width: 768px) { .py-mobile { padding-top: 28px; padding-bottom: 28px; } }
.px-mobile { padding-left: 12px; padding-right: 12px; }
@media (min-width: 768px) { .px-mobile { padding-left: 24px; padding-right: 24px; } }
</style>

<div class="container-fluid py-mobile px-mobile">

    <!-- Page Header -->
    <div class="page-header-wrap mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="page-header-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark" style="font-size: clamp(1rem, 3vw, 1.4rem);">Command Center Musyrifin</h4>
                <p class="text-secondary mb-0" style="font-size: 0.82rem;">Pusat pemantauan kinerja, analitik, dan manajemen akun musyrif.</p>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card primary">
                <div class="stat-icon" style="background:#eef2ff; color:#4f46e5;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Total Musyrif</div>
                <div class="stat-value" id="stat-musyrif">--</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card danger">
                <div class="stat-icon" style="background:#fef2f2; color:#ef4444;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-label">Belum Rapot</div>
                <div class="stat-value text-danger" id="stat-belum-rapot">--</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card warning">
                <div class="stat-icon" style="background:#fffbeb; color:#f59e0b;">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-label">Rapot Janggal</div>
                <div class="stat-value text-warning" id="stat-rapot-janggal">--</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card success">
                <div class="stat-icon" style="background:#f0fdf4; color:#22c55e;">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-label">Aktivitas Hari Ini</div>
                <div class="stat-value text-success" id="stat-aktivitas">--</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card warning">
                <div class="stat-icon" style="background:#fffbeb; color:#f59e0b;">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-label">Pengumuman Aktif</div>
                <div class="stat-value text-warning" id="stat-pengumuman">--</div>
            </div>
        </div>
    </div>

    <!-- Main Content Card with Tabs -->
    <div class="main-card">
        <!-- Tab Navigation (scrollable on mobile) -->
        <div class="tab-nav-wrap">
            <ul class="nav nav-pills" id="pengelolaTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="aktivitas-tab" data-bs-toggle="pill" data-bs-target="#aktivitas" type="button">
                        <i class="fas fa-history me-1"></i> Log Aktivitas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="musyrif-tab" data-bs-toggle="pill" data-bs-target="#musyrif" type="button">
                        <i class="fas fa-users-cog me-1"></i> Manajemen Musyrif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="kinerja-tab" data-bs-toggle="pill" data-bs-target="#kinerja" type="button">
                        <i class="fas fa-file-invoice me-1"></i> Kinerja Musyrif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="broadcast-tab" data-bs-toggle="pill" data-bs-target="#broadcast" type="button">
                        <i class="fas fa-bullhorn me-1"></i> Broadcast
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content tab-content-inner" id="pengelolaTabContent">

            <!-- TAB: LOG AKTIVITAS -->
            <div class="tab-pane fade show active" id="aktivitas" role="tabpanel">
                <div class="table-section-header">
                    <h5><i class="fas fa-history text-primary me-2"></i>Jejak Digital Terkini</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadAktivitas()">
                        <i class="fas fa-sync-alt me-1"></i> Segarkan
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableAktivitas">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Aksi</th>
                                <th>Modul</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: MANAJEMEN MUSYRIF -->
            <div class="tab-pane fade" id="musyrif" role="tabpanel">
                <div class="table-section-header">
                    <h5><i class="fas fa-users-cog text-primary me-2"></i>Manajemen Akun Pengguna</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadMusyrif()">
                        <i class="fas fa-sync-alt me-1"></i> Segarkan
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableMusyrif">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: KINERJA MUSYRIF -->
            <div class="tab-pane fade" id="kinerja" role="tabpanel">
                <!-- Musyrif Belum Rapot -->
                <div class="table-section-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="section-header-badge">
                            <i class="fas fa-file-invoice text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Musyrif Belum Cetak Rapot</h5>
                            <span class="text-muted small d-none d-sm-inline">Daftar musyrif dengan rapot yang belum disetorkan</span>
                        </div>
                    </div>
                    <div class="header-actions-group">
                        <button class="btn btn-ingatkan-massal" id="btnKirimSemuaPeringatan" onclick="kirimSemuaPeringatan()" style="display:none;">
                            <i class="fas fa-bell me-1"></i> Ingatkan Semua
                        </button>
                        <button class="btn btn-sm btn-outline-primary btn-segarkan-custom fw-semibold px-3 py-1 rounded-3" onclick="loadKinerja()">
                            <i class="fas fa-sync-alt me-1"></i> Segarkan
                        </button>
                    </div>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0" id="tableKinerja">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Musyrif</th>
                                <th>Username</th>
                                <th>Bulan Tertunggak</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Rapot Janggal / Mencurigakan -->
                <div class="table-section-header mt-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="section-header-badge" style="background:#fffbeb;">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Rapot Mencurigakan <span class="badge bg-warning text-dark ms-1" id="badgeJanggalCount" style="display:none;"></span></h5>
                            <span class="text-muted small d-none d-sm-inline">Musyrif yang mengisi rapot sebelum waktunya dibuka</span>
                        </div>
                    </div>
                    <div class="header-actions-group">
                        <button class="btn btn-ingatkan-massal" id="btnKirimSemuaJanggal" onclick="kirimSemuaPeringatanJanggal()" style="display:none;">
                            <i class="fas fa-shield-alt me-1"></i> Ingatkan Semua Integritas
                        </button>
                    </div>
                </div>
                <div class="alert alert-warning border-0 py-2 px-3 mb-3" style="font-size:0.82rem; border-radius:10px;">
                    <i class="fas fa-info-circle me-1"></i>
                    Rapot dianggap <strong>janggal/mencurigakan</strong> jika dibuat sebelum memasuki <strong>7 hari terakhir bulan tersebut</strong>, atau dibuat untuk bulan yang belum terjadi.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableJanggal">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Musyrif</th>
                                <th>Username</th>
                                <th>Rapot Janggal</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: BROADCAST -->
            <div class="tab-pane fade" id="broadcast" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-9 col-lg-7">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <h5 class="fw-bold mb-0"><i class="fas fa-bullhorn text-primary me-2"></i>Buat Pengumuman</h5>
                            <a href="<?= BASE_URL ?>/pengaturan/pengelola/riwayat_broadcast.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-list me-1"></i> Riwayat Lengkap
                            </a>
                        </div>
                        <div class="broadcast-form-card">
                            <form id="formBroadcast" onsubmit="submitBroadcast(event)">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-secondary text-uppercase letter-spacing">Judul Pengumuman</label>
                                    <input type="text" class="form-control" name="judul" required placeholder="Contoh: Info Penting!" maxlength="150">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-secondary text-uppercase">Target Musyrif <span class="text-muted fw-normal">(Opsional)</span></label>
                                    <div class="musyrif-checkbox-list">
                                        <?php foreach($musyrif_list as $m): ?>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input target-musyrif-checkbox" type="checkbox" value="<?= $m['id'] ?>" id="musyrif_<?= $m['id'] ?>">
                                            <label class="form-check-label" for="musyrif_<?= $m['id'] ?>">
                                                <?= htmlspecialchars($m['nama_lengkap']) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-text mt-2">Biarkan kosong untuk kirim ke <strong>Semua Musyrif</strong>.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold small text-secondary text-uppercase">Isi Pesan</label>
                                    <textarea class="form-control" name="pesan" rows="4" required placeholder="Ketik pengumuman di sini..."></textarea>
                                    <div class="form-text mt-2">
                                        Pesan muncul sebagai popup di dashboard musyrif terkait.<br>
                                        <i class="fas fa-clock text-warning me-1"></i><strong>Kadaluarsa otomatis setelah 24 jam.</strong>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2" id="btnSubmitBroadcast">
                                    <i class="fas fa-paper-plane me-2"></i> Kirim Pengumuman
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /main-card -->

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadAktivitas();
    loadMusyrif();
    loadKinerja();

    // Auto-switch tab jika ada ?tab= dari URL (misal dari notif dashboard)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        const tabEl = document.querySelector(`#pengelolaTab [data-bs-target="#${tabParam}"]`);
        if (tabEl) {
            const bsTab = new bootstrap.Tab(tabEl);
            bsTab.show();
            // Scroll ke tab agar terlihat
            setTimeout(() => tabEl.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
        }
    }
});

function fetchAPI(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (const key in data) {
        formData.append(key, data[key]);
    }
    return fetch(`${BASE_URL}/pengaturan/pengelola/proses.php`, {
        method: 'POST',
        body: formData
    }).then(res => res.json());
}

function loadStats() {
    fetchAPI('get_stats').then(res => {
        if (res.status === 'success') {
            document.getElementById('stat-musyrif').innerText = res.data.musyrif;
            document.getElementById('stat-aktivitas').innerText = res.data.aktivitas;
            document.getElementById('stat-pengumuman').innerText = res.data.pengumuman;
            document.getElementById('stat-belum-rapot').innerText = res.data.belum_rapot;
            document.getElementById('stat-rapot-janggal').innerText = res.data.rapot_janggal;
        }
    });
}

function loadKinerja() {
    fetchAPI('get_kinerja').then(res => {
        if (res.status === 'success') {
            // ── Tabel: Musyrif Belum Rapot ──
            const tbody = document.querySelector('#tableKinerja tbody');
            const btnAll = document.getElementById('btnKirimSemuaPeringatan');
            tbody.innerHTML = '';

            if (res.data.length === 0) {
                tbody.innerHTML = '<tr class="empty-state-row"><td colspan="5" class="py-4 text-success fw-semibold"><i class="fas fa-check-circle fs-4 me-2 text-success"></i><span>Semua musyrif sudah menyetorkan rapot!</span></td></tr>';
                if (btnAll) btnAll.style.display = 'none';
            } else {
                const unnotified = res.data.filter(item => !item.has_recent_warning);
                if (btnAll) {
                    btnAll.style.display = 'inline-flex';
                    if (unnotified.length > 0) {
                        btnAll.disabled = false;
                        btnAll.className = 'btn btn-ingatkan-massal';
                        btnAll.innerHTML = `<i class="fas fa-bell me-1"></i> Ingatkan Semua (${unnotified.length})`;
                    } else {
                        btnAll.disabled = true;
                        btnAll.className = 'btn btn-ingatkan-massal';
                        btnAll.innerHTML = `<i class="fas fa-check-circle me-1"></i> Semua Sudah Diingatkan`;
                    }
                }

                res.data.forEach(item => {
                    const badgeStatus = item.is_active == 1
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-medium"><i class="fas fa-check-circle me-1 small"></i>Aktif</span>'
                        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill fw-medium"><i class="fas fa-ban me-1 small"></i>Suspend</span>';

                    let badges = '';
                    if (item.tertunggak && item.tertunggak.length > 0) {
                        badges = item.tertunggak.map(m => `<span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1 mb-1 fw-normal" style="font-size:0.75rem; border-radius:6px;"><i class="fas fa-exclamation-circle me-1 small"></i>${m}</span>`).join('');
                    }

                    const pesan_bc = `Peringatan: Anda belum menyetorkan rapot kepengasuhan untuk bulan: ${item.tertunggak.join(', ')}. Mohon untuk segera diselesaikan.`;

                    const btnPeringatan = item.has_recent_warning
                        ? `<button class="btn btn-sm btn-light border text-muted px-3 py-1 rounded-3" disabled title="Sudah diingatkan dalam 24 jam terakhir"><i class="fas fa-check-circle text-success me-1"></i>Terkirim</button>`
                        : `<button class="btn btn-sm btn-outline-warning text-dark fw-semibold px-3 py-1 rounded-3" onclick="kirimPeringatan(${item.id}, '${pesan_bc}')"><i class="fas fa-bell text-warning me-1"></i>Peringatan</button>`;

                    const initial = (item.nama_lengkap || 'M').charAt(0).toUpperCase();

                    tbody.innerHTML += `
                        <tr class="kinerja-card-item">
                            <td class="cell-user" data-label="Musyrif">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-mini">${initial}</div>
                                    <div>
                                        <div class="fw-bold text-dark">${item.nama_lengkap}</div>
                                        <div class="text-muted small">@${item.username}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="cell-tertunggak" data-label="Bulan Tertunggak">
                                <div class="d-md-none text-muted small fw-semibold mb-1"><i class="fas fa-clock text-danger me-1"></i>Tunggakan Rapot:</div>
                                <div>${badges}</div>
                            </td>
                            <td class="cell-status" data-label="Status">
                                <div>${badgeStatus}</div>
                            </td>
                            <td class="cell-action text-end" data-label="Aksi">
                                ${btnPeringatan}
                            </td>
                        </tr>
                    `;
                });
            }

            // ── Tabel: Rapot Janggal ──
            const tbodyJ = document.querySelector('#tableJanggal tbody');
            const badgeCount = document.getElementById('badgeJanggalCount');
            const btnAllJ = document.getElementById('btnKirimSemuaJanggal');
            tbodyJ.innerHTML = '';
            if (!res.janggal || res.janggal.length === 0) {
                tbodyJ.innerHTML = '<tr class="empty-state-row"><td colspan="5" class="py-4 text-success fw-medium"><i class="fas fa-check-circle fs-5 me-2 text-success"></i><span>Tidak ada rapot mencurigakan.</span></td></tr>';
                badgeCount.style.display = 'none';
                if (btnAllJ) btnAllJ.style.display = 'none';
            } else {
                badgeCount.textContent = res.janggal.length;
                badgeCount.style.display = 'inline-block';
                
                const unnotifiedJ = res.janggal.filter(item => !item.has_recent_warning);
                if (btnAllJ) {
                    btnAllJ.style.display = 'inline-flex';
                    if (unnotifiedJ.length > 0) {
                        btnAllJ.disabled = false;
                        btnAllJ.className = 'btn btn-ingatkan-massal';
                        btnAllJ.innerHTML = `<i class="fas fa-shield-alt me-1"></i> Ingatkan Semua Integritas (${unnotifiedJ.length})`;
                    } else {
                        btnAllJ.disabled = true;
                        btnAllJ.className = 'btn btn-ingatkan-massal';
                        btnAllJ.innerHTML = `<i class="fas fa-check-circle me-1"></i> Semua Sudah Diingatkan`;
                    }
                }

                res.janggal.forEach(item => {
                    const badgeStatus = item.is_active == 1
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-medium"><i class="fas fa-check-circle me-1 small"></i>Aktif</span>'
                        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill fw-medium"><i class="fas fa-ban me-1 small"></i>Suspend</span>';

                    const badges = item.bulan_janggal.map(b =>
                        `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle me-1 mb-1 fw-normal" style="font-size:0.75rem; border-radius:6px; color:#9a3412 !important;"><i class="fas fa-exclamation-triangle me-1 small text-warning"></i>${b}</span>`
                    ).join('');

                    const bulanList = item.bulan_janggal.join(', ');
                    const pesanJanggal = `Peringatan Integritas Data: Anda terdeteksi mengisi rapot kepengasuhan untuk ${bulanList} sebelum periode pengisian dibuka (7 hari terakhir bulan tersebut). Tindakan ini berpotensi melanggar integritas data. Harap segera Lakukan klarifikasi Data.`;

                    const btnPeringatan = item.has_recent_warning
                        ? `<button class="btn btn-sm btn-light border text-muted px-3 py-1 rounded-3" disabled title="Sudah diingatkan dalam 24 jam terakhir"><i class="fas fa-check-circle text-success me-1"></i>Terkirim</button>`
                        : `<button class="btn btn-sm btn-outline-warning text-dark fw-semibold px-3 py-1 rounded-3" onclick="kirimPeringatanJanggal(${item.id}, '${pesanJanggal.replace(/'/g, "&apos;")}')"><i class="fas fa-shield-alt text-warning me-1"></i>Peringatan</button>`;

                    const initial = (item.nama_lengkap || 'M').charAt(0).toUpperCase();

                    tbodyJ.innerHTML += `
                        <tr class="kinerja-card-item">
                            <td class="cell-user" data-label="Musyrif">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-mini">${initial}</div>
                                    <div>
                                        <div class="fw-bold text-dark">${item.nama_lengkap}</div>
                                        <div class="text-muted small">@${item.username}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="cell-tertunggak" data-label="Rapot Janggal">
                                <div class="d-md-none text-muted small fw-semibold mb-1"><i class="fas fa-exclamation-triangle text-warning me-1"></i>Rapot Janggal:</div>
                                <div>${badges}</div>
                            </td>
                            <td class="cell-status" data-label="Status">
                                <div>${badgeStatus}</div>
                            </td>
                            <td class="cell-action text-end" data-label="Aksi">
                                ${btnPeringatan}
                            </td>
                        </tr>
                    `;
                });
            }
        }
    });
}

function kirimPeringatan(id, pesan) {
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="swal-icon-badge-warning">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="swal-custom-title">Kirim Peringatan?</h3>
                <p class="swal-custom-desc">Peringatan ini akan muncul khusus di dashboard musyrif tersebut.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: 'swal-btn-confirm-warning',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('buat_peringatan', { target_id: id, pesan: pesan, tipe: 'rapot' }).then(res => {
                if (res.status === 'success') {
                    showToast('Peringatan berhasil di-broadcast ke musyrif.', 'success');
                    loadKinerja();
                    loadStats();
                } else {
                    showToast(res.message || 'Gagal mengirim peringatan.', 'error');
                }
            });
        }
    });
}

function kirimSemuaPeringatan() {
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="swal-icon-badge-warning">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h3 class="swal-custom-title">Kirim Peringatan Massal?</h3>
                <p class="swal-custom-desc">Sistem akan otomatis mengirimkan notifikasi peringatan rapot ke <strong>seluruh musyrif yang belum menyelesaikan rapotnya</strong>.</p>
                <div class="alert alert-light border text-start small mb-0 mt-2 py-2 px-3 text-muted" style="border-radius:10px;">
                    <i class="fas fa-info-circle me-1 text-primary"></i> Musyrif yang sudah dikirimi peringatan dalam 24 jam terakhir akan otomatis dilewati.
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim ke Semua!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: 'swal-btn-confirm-warning',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Mengirim Peringatan...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            fetchAPI('kirim_semua_peringatan', { tipe: 'rapot' }).then(res => {
                Swal.close();
                if (res.status === 'success') {
                    showToast(res.message || 'Peringatan massal berhasil dikirim!', 'success');
                    loadKinerja();
                    loadStats();
                } else if (res.status === 'info') {
                    showToast(res.message, 'info');
                    loadKinerja();
                    loadStats();
                } else {
                    showToast(res.message || 'Gagal mengirim peringatan massal.', 'error');
                }
            }).catch(() => {
                Swal.close();
                showToast('Terjadi kesalahan koneksi server.', 'error');
            });
        }
    });
}

function kirimPeringatanJanggal(id, pesan) {
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="swal-icon-badge-warning">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="swal-custom-title">Kirim Peringatan Integritas?</h3>
                <p class="swal-custom-desc">Musyrif akan menerima notifikasi bahwa rapotnya <strong>terdeteksi diisi sebelum waktunya</strong>.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: 'swal-btn-confirm-warning',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('buat_peringatan', { target_id: id, pesan: pesan, tipe: 'janggal' }).then(res => {
                if (res.status === 'success') {
                    showToast('Peringatan integritas berhasil dikirim.', 'success');
                    loadKinerja();
                    loadStats();
                } else {
                    showToast(res.message || 'Gagal mengirim peringatan.', 'error');
                }
            });
        }
    });
}

function kirimSemuaPeringatanJanggal() {
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="swal-icon-badge-warning">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="swal-custom-title">Kirim Peringatan Integritas Massal?</h3>
                <p class="swal-custom-desc">Sistem akan otomatis mengirimkan peringatan khusus ke <strong>seluruh musyrif yang mengisi rapot sebelum waktunya</strong>.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim Integritas!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: 'swal-btn-confirm-warning',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Mengirim Peringatan...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            fetchAPI('kirim_semua_peringatan', { tipe: 'janggal' }).then(res => {
                Swal.close();
                if (res.status === 'success') {
                    showToast(res.message || 'Peringatan integritas massal berhasil dikirim!', 'success');
                    loadKinerja();
                    loadStats();
                } else if (res.status === 'info') {
                    showToast(res.message, 'info');
                    loadKinerja();
                    loadStats();
                } else {
                    showToast(res.message || 'Gagal mengirim peringatan massal.', 'error');
                }
            }).catch(() => {
                Swal.close();
                showToast('Terjadi kesalahan koneksi server.', 'error');
            });
        }
    });
}

function loadAktivitas() {
    fetchAPI('get_aktivitas').then(res => {
        if (res.status === 'success') {
            const tbody = document.querySelector('#tableAktivitas tbody');
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada aktivitas.</td></tr>';
            } else {
                res.data.forEach(item => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Waktu" class="text-muted small">${item.waktu}</td>
                            <td data-label="Pengguna" class="fw-medium">${item.nama}</td>
                            <td data-label="Aksi"><span class="badge bg-secondary">${item.aksi}</span></td>
                            <td data-label="Modul">${item.fitur}</td>
                            <td data-label="Keterangan">${item.deskripsi}</td>
                        </tr>
                    `;
                });
            }
        }
    });
}

function loadMusyrif() {
    fetchAPI('get_musyrif').then(res => {
        if (res.status === 'success') {
            const tbody = document.querySelector('#tableMusyrif tbody');
            tbody.innerHTML = '';
            res.data.forEach(item => {
                const badgeStatus = item.is_active == 1
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Suspend</span>';
                const btnStatus = item.is_active == 1
                    ? `<button class="btn btn-sm btn-outline-danger" onclick="toggleStatus(${item.id}, 0)" title="Suspend"><i class="fas fa-ban"></i></button>`
                    : `<button class="btn btn-sm btn-outline-success" onclick="toggleStatus(${item.id}, 1)" title="Aktifkan"><i class="fas fa-check"></i></button>`;

                tbody.innerHTML += `
                    <tr>
                        <td data-label="Nama" class="fw-bold">${item.nama_lengkap}</td>
                        <td data-label="Username" class="text-muted">${item.username}</td>
                        <td data-label="Role"><span class="badge bg-primary">${item.role}</span></td>
                        <td data-label="Status">${badgeStatus}</td>
                        <td data-label="Aksi" class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(${item.id})" title="Reset Password"><i class="fas fa-key"></i></button>
                                ${btnStatus}
                            </div>
                        </td>
                    </tr>
                `;
            });
        }
    });
}

function toggleStatus(id, newStatus) {
    const isActivating = newStatus === 1;
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="${isActivating ? 'swal-icon-badge-success' : 'swal-icon-badge-danger'}">
                    <i class="fas ${isActivating ? 'fa-user-check' : 'fa-user-slash'}"></i>
                </div>
                <h3 class="swal-custom-title">${isActivating ? 'Aktifkan Akun?' : 'Suspend Akun?'}</h3>
                <p class="swal-custom-desc">${isActivating ? 'Musyrif akan bisa login kembali ke sistem.' : 'Musyrif <strong>tidak akan bisa login</strong> dan akan <strong>otomatis dikeluarkan</strong> dari sesi aktifnya.'}</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isActivating ? 'Ya, Aktifkan' : 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: isActivating ? 'swal-btn-confirm-success' : 'swal-btn-confirm-danger',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('toggle_status', { id: id, status: newStatus }).then(res => {
                if (res.status === 'success') {
                    showToast(res.message || 'Status akun berhasil diperbarui.', 'success');
                    loadMusyrif();
                } else {
                    showToast(res.message || 'Gagal memperbarui status.', 'error');
                }
            });
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        html: `
            <div class="swal-custom-modal">
                <div class="swal-icon-badge-primary">
                    <i class="fas fa-key"></i>
                </div>
                <h3 class="swal-custom-title">Reset Password?</h3>
                <p class="swal-custom-desc mb-2">Password akan diubah menjadi:</p>
                <div class="my-2">
                    <span class="badge bg-danger-subtle text-danger fs-5 px-3 py-1 rounded-pill font-monospace fw-bold border border-danger-subtle">123456</span>
                </div>
                <p class="swal-custom-desc text-muted small mt-2">Ingatkan musyrif untuk segera menggantinya setelah login.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusConfirm: true,
        customClass: {
            popup: 'swal-modern-popup',
            actions: 'swal-modern-actions',
            confirmButton: 'swal-btn-confirm-primary',
            cancelButton: 'swal-btn-cancel'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('reset_password', { id: id }).then(res => {
                if (res.status === 'success') {
                    showToast('Password berhasil direset menjadi 123456', 'success');
                } else {
                    showToast(res.message || 'Gagal mereset password.', 'error');
                }
            });
        }
    });
}

function submitBroadcast(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitBroadcast');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';

    let targets = Array.from(document.querySelectorAll('.target-musyrif-checkbox:checked')).map(cb => cb.value);
    let targetStr = targets.length > 0 ? targets.join(',') : '';

    fetchAPI('buat_pengumuman', {
        judul: e.target.judul.value,
        pesan: e.target.pesan.value,
        target_users: targetStr
    }).then(res => {
        if (res.status === 'success') {
            showToast('Pengumuman berhasil dikirim.', 'success');
            e.target.reset();
            document.querySelectorAll('.target-musyrif-checkbox').forEach(cb => cb.checked = false);
            loadBroadcast();
        } else {
            showToast(res.message || 'Gagal mengirim pengumuman.', 'error');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Kirim Pengumuman';
    });
}

function tutupBroadcast(id) {
    fetchAPI('tutup_broadcast', { id: id }).then(res => {
        if (res.status === 'success') {
            showToast('Pengumuman berhasil ditutup.', 'success');
            loadBroadcast();
        } else {
            showToast(res.message || 'Gagal menutup pengumuman.', 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
