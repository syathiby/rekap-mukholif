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

/* Tables */
.table-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.table-section-header h5 {
    font-size: clamp(0.9rem, 2.5vw, 1.1rem);
    font-weight: 700;
    margin: 0;
}
.table th {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
}
.table td { font-size: 0.875rem; vertical-align: middle; }

/* Mobile card view for tables */
@media (max-width: 640px) {
    .table-responsive .table thead { display: none; }
    .table-responsive .table tbody tr {
        display: block;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 10px;
        padding: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .table-responsive .table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }
    .table-responsive .table tbody td:last-child { border-bottom: none; }
    .table-responsive .table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        flex-shrink: 0;
        margin-right: 8px;
    }
    .table-responsive .table tbody td.text-end { justify-content: flex-end; }
    .table-responsive .table tbody td.text-end::before { content: ''; }
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
                    <h5><i class="fas fa-file-invoice text-primary me-2"></i>Musyrif Belum Cetak Rapot</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadKinerja()">
                        <i class="fas fa-sync-alt me-1"></i> Segarkan
                    </button>
                </div>
                <div class="table-responsive mb-5">
                    <table class="table table-hover align-middle mb-0" id="tableKinerja">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Lengkap</th>
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
                <div class="table-section-header mt-2">
                    <h5><i class="fas fa-exclamation-triangle text-warning me-2"></i>Rapot Mencurigakan <span class="badge bg-warning text-dark ms-1" id="badgeJanggalCount" style="display:none;"></span></h5>
                    <span class="text-muted small">Musyrif yang mengisi rapot sebelum waktunya</span>
                </div>
                <div class="alert alert-warning border-0 py-2 px-3 mb-3" style="font-size:0.82rem; border-radius:10px;">
                    <i class="fas fa-info-circle me-1"></i>
                    Rapot dianggap <strong>janggal/mencurigakan</strong> jika dibuat sebelum memasuki <strong>7 hari terakhir bulan tersebut</strong>, atau dibuat untuk bulan yang belum terjadi.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableJanggal">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Lengkap</th>
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
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-success fw-bold"><i class="fas fa-check-circle fs-3 d-block mb-2"></i>Semua musyrif sudah menyetorkan rapot!</td></tr>';
            } else {
                res.data.forEach(item => {
                    const badgeStatus = item.is_active == 1
                        ? '<span class="badge bg-success">Aktif</span>'
                        : '<span class="badge bg-danger">Suspend</span>';

                    let badges = '';
                    if (item.tertunggak && item.tertunggak.length > 0) {
                        badges = item.tertunggak.map(m => `<span class="badge bg-danger me-1 mb-1" style="font-size:0.72rem;">${m}</span>`).join('');
                    }

                    const pesan_bc = `Peringatan: Anda belum menyetorkan rapot kepengasuhan untuk bulan: ${item.tertunggak.join(', ')}. Mohon untuk segera diselesaikan.`;

                    const btnPeringatan = item.has_recent_warning
                        ? `<button class="btn btn-sm btn-secondary" disabled title="Sudah diingatkan dalam 24 jam terakhir"><i class="fas fa-check-circle me-1"></i>Terkirim</button>`
                        : `<button class="btn btn-sm btn-outline-warning" onclick="kirimPeringatan(${item.id}, '${pesan_bc}')"><i class="fas fa-bell me-1"></i>Peringatan</button>`;

                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Nama" class="fw-bold">${item.nama_lengkap}</td>
                            <td data-label="Username" class="text-muted">${item.username}</td>
                            <td data-label="Tertunggak">${badges}</td>
                            <td data-label="Status">${badgeStatus}</td>
                            <td data-label="Aksi" class="text-end">${btnPeringatan}</td>
                        </tr>
                    `;
                });
            }

            // ── Tabel: Rapot Janggal ──
            const tbodyJ = document.querySelector('#tableJanggal tbody');
            const badgeCount = document.getElementById('badgeJanggalCount');
            tbodyJ.innerHTML = '';
            if (!res.janggal || res.janggal.length === 0) {
                tbodyJ.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-success"><i class="fas fa-check-circle me-1"></i>Tidak ada rapot mencurigakan.</td></tr>';
                badgeCount.style.display = 'none';
            } else {
                badgeCount.textContent = res.janggal.length;
                badgeCount.style.display = 'inline-block';
                res.janggal.forEach(item => {
                    const badgeStatus = item.is_active == 1
                        ? '<span class="badge bg-success">Aktif</span>'
                        : '<span class="badge bg-danger">Suspend</span>';

                    const badges = item.bulan_janggal.map(b =>
                        `<span class="badge bg-warning text-dark me-1 mb-1" style="font-size:0.72rem;"><i class="fas fa-exclamation-circle me-1"></i>${b}</span>`
                    ).join('');

                    const bulanList = item.bulan_janggal.join(', ');
                    const pesanJanggal = `Peringatan Integritas Data: Anda terdeteksi mengisi rapot kepengasuhan untuk ${bulanList} sebelum periode pengisian dibuka (7 hari terakhir bulan tersebut). Tindakan ini berpotensi melanggar integritas data. Harap segera Lakukan klarifikasi Data.`;

                    const btnPeringatan = item.has_recent_warning
                        ? `<button class="btn btn-sm btn-secondary" disabled title="Sudah diingatkan dalam 24 jam terakhir"><i class="fas fa-check-circle me-1"></i>Terkirim</button>`
                        : `<button class="btn btn-sm btn-outline-warning" onclick="kirimPeringatanJanggal(${item.id}, '${pesanJanggal.replace(/'/g, "&apos;")}')"><i class="fas fa-bell me-1"></i>Peringatan</button>`;

                    tbodyJ.innerHTML += `
                        <tr>
                            <td data-label="Nama" class="fw-bold">${item.nama_lengkap}</td>
                            <td data-label="Username" class="text-muted">${item.username}</td>
                            <td data-label="Rapot Janggal">${badges}</td>
                            <td data-label="Status">${badgeStatus}</td>
                            <td data-label="Aksi" class="text-end">${btnPeringatan}</td>
                        </tr>
                    `;
                });
            }
        }
    });
}

function kirimPeringatan(id, pesan) {
    Swal.fire({
        title: 'Kirim Peringatan?',
        text: "Peringatan ini akan muncul khusus di dashboard musyrif tersebut.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('buat_peringatan', { target_id: id, pesan: pesan, tipe: 'rapot' }).then(res => {
                if (res.status === 'success') {
                    Swal.fire('Terkirim!', 'Peringatan berhasil di-broadcast ke musyrif tersebut.', 'success');
                    loadKinerja();
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            });
        }
    });
}

function kirimPeringatanJanggal(id, pesan) {
    Swal.fire({
        title: 'Kirim Peringatan Integritas?',
        html: 'Musyrif akan menerima notifikasi bahwa rapotnya <strong>terdeteksi diisi sebelum waktunya</strong> dan diminta untuk klarifikasi.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#f59e0b',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('buat_peringatan', { target_id: id, pesan: pesan, tipe: 'janggal' }).then(res => {
                if (res.status === 'success') {
                    Swal.fire('Terkirim!', 'Peringatan integritas berhasil dikirim ke musyrif tersebut.', 'success');
                    loadKinerja();
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
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
    Swal.fire({
        title: newStatus === 1 ? 'Aktifkan Akun?' : 'Suspend Akun?',
        html: newStatus === 1
            ? 'Musyrif akan bisa login kembali ke sistem.'
            : 'Musyrif <strong>tidak akan bisa login</strong> dan akan <strong>otomatis dikeluarkan</strong> dari sesi aktifnya.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('toggle_status', { id: id, status: newStatus }).then(res => {
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', res.message, 'success');
                    loadMusyrif();
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            });
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        title: 'Reset Password?',
        html: 'Password akan diubah menjadi: <br><code class="fs-5 fw-bold text-danger">123456</code><br><small class="text-muted">Ingatkan musyrif untuk segera menggantinya.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetchAPI('reset_password', { id: id }).then(res => {
                if (res.status === 'success') {
                    Swal.fire('Berhasil!', 'Password berhasil direset. Password baru: <b>123456</b>', 'success');
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
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
            Swal.fire('Berhasil!', 'Pengumuman telah dikirim.', 'success');
            e.target.reset();
            document.querySelectorAll('.target-musyrif-checkbox').forEach(cb => cb.checked = false);
        } else {
            Swal.fire('Gagal!', res.message, 'error');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Kirim Pengumuman';
    });
}

function tutupBroadcast(id) {
    fetchAPI('tutup_broadcast', { id: id }).then(res => {
        if (res.status === 'success') {
            loadBroadcast();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
