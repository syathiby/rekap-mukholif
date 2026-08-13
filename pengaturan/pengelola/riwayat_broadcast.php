<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Strict Whitelist Proteksi
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pengelola'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1><p>Anda tidak memiliki akses ke halaman ini.</p>";
    exit;
}

$page_title = 'Riwayat Pengumuman';
require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ==============================
   RIWAYAT BROADCAST - RESPONSIVE
   ============================== */
.page-header-wrap {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}
.page-header-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1.3rem;
    box-shadow: 0 4px 14px rgba(14,165,233,0.3);
    flex-shrink: 0;
}
.main-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow: hidden;
}
.card-body-pad { padding: 16px; }
@media (min-width: 768px) { .card-body-pad { padding: 24px; } }

/* ── Desktop table ── */
.table th {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.table td { font-size: 0.875rem; vertical-align: middle; }

/* ── Mobile card list ── */
.bc-card-list { display: none; }

.bc-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.05);
}
.bc-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.bc-card-title { font-weight: 700; font-size: 0.9rem; color: #1e293b; }
.bc-card-date  { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }
.bc-card-meta  {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.bc-card-body  {
    font-size: 0.82rem;
    color: #475569;
    line-height: 1.5;
    border-top: 1px solid #f1f5f9;
    padding-top: 8px;
}
.bc-card-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}

@media (max-width: 639px) {
    .table-wrap { display: none; }
    .bc-card-list { display: block; }
}

.py-mobile { padding-top: 16px; padding-bottom: 16px; }
@media (min-width: 768px) { .py-mobile { padding-top: 28px; padding-bottom: 28px; } }
.px-mobile { padding-left: 12px; padding-right: 12px; }
@media (min-width: 768px) { .px-mobile { padding-left: 24px; padding-right: 24px; } }
</style>

<div class="container-fluid py-mobile px-mobile">

    <!-- Header -->
    <div class="page-header-wrap">
        <div class="d-flex align-items-center gap-3">
            <div class="page-header-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark" style="font-size: clamp(1rem, 3vw, 1.3rem);">Riwayat Pengumuman</h4>
                <p class="text-secondary mb-0" style="font-size: 0.82rem;">Semua pesan broadcast yang pernah dikirim.</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/pengaturan/pengelola" class="btn btn-outline-secondary btn-sm align-self-center">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Content Card -->
    <div class="main-card">
        <div class="card-body-pad">

            <!-- DESKTOP: Table -->
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableBroadcast">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Judul</th>
                                <th>Target</th>
                                <th>Pesan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MOBILE: Card List -->
            <div class="bc-card-list" id="bcCardList">
                <div class="text-center text-muted py-4">Memuat data...</div>
            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';

document.addEventListener('DOMContentLoaded', function() {
    loadBroadcast();
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

function getBadge(status) {
    if (status == 1) return '<span class="badge bg-success">Aktif</span>';
    if (status == 2) return '<span class="badge bg-secondary">Expired (24j)</span>';
    return '<span class="badge bg-dark">Ditutup</span>';
}

function getBtnTutup(id, status) {
    return status == 1
        ? `<button class="btn btn-sm btn-outline-danger" onclick="tutupBroadcast(${id})" title="Tutup"><i class="fas fa-times me-1"></i>Tutup</button>`
        : `<span class="text-muted small">-</span>`;
}

function loadBroadcast() {
    fetchAPI('get_broadcast').then(res => {
        if (res.status !== 'success') return;

        // ── Desktop table ──
        const tbody = document.querySelector('#tableBroadcast tbody');
        // ── Mobile cards ──
        const cardList = document.getElementById('bcCardList');

        tbody.innerHTML = '';
        cardList.innerHTML = '';

        if (res.data.length === 0) {
            const empty = '<div class="text-center py-5 text-muted"><i class="fas fa-inbox fs-2 d-block mb-2"></i>Belum ada pengumuman.</div>';
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-inbox fs-3 d-block mb-2"></i>Belum ada pengumuman.</td></tr>`;
            cardList.innerHTML = empty;
            return;
        }

        res.data.forEach(item => {
            const badge   = getBadge(item.status_aktif);
            const btnTutup = getBtnTutup(item.id, item.status_aktif);

            // Desktop row
            tbody.innerHTML += `
                <tr>
                    <td class="text-muted small" style="white-space:nowrap;">${item.tanggal}</td>
                    <td class="fw-semibold">${item.judul || 'Pengumuman Sistem!'}</td>
                    <td><span class="badge bg-light text-dark border">${item.target}</span></td>
                    <td style="max-width:200px;word-break:break-word;">${item.pesan}</td>
                    <td>${badge}</td>
                    <td class="text-end">${getBtnTutup(item.id, item.status_aktif)}</td>
                </tr>
            `;

            // Mobile card
            cardList.innerHTML += `
                <div class="bc-card">
                    <div class="bc-card-header">
                        <div>
                            <div class="bc-card-title">${item.judul || 'Pengumuman Sistem!'}</div>
                            <div class="bc-card-date"><i class="fas fa-clock me-1"></i>${item.tanggal}</div>
                        </div>
                        ${badge}
                    </div>
                    <div class="bc-card-meta">
                        <i class="fas fa-user-circle text-muted" style="font-size:0.8rem;"></i>
                        <span class="badge bg-light text-dark border" style="font-size:0.75rem;">${item.target}</span>
                    </div>
                    <div class="bc-card-body">${item.pesan}</div>
                    ${item.status_aktif == 1 ? `<div class="bc-card-footer">${getBtnTutup(item.id, item.status_aktif)}</div>` : ''}
                </div>
            `;
        });
    });
}

function tutupBroadcast(id) {
    Swal.fire({
        title: 'Tutup Pengumuman?',
        text: 'Pengumuman ini tidak akan muncul lagi di dashboard musyrif.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tutup',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(result => {
        if (result.isConfirmed) {
            fetchAPI('tutup_broadcast', { id: id }).then(res => {
                if (res.status === 'success') {
                    showToast('Pengumuman berhasil ditutup.', 'success');
                    loadBroadcast();
                }
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
