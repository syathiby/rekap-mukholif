<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');    
require_once __DIR__ . '/../../layouts/header.php'; 

$loggedInUserId = (int)($_SESSION['user_id'] ?? 0);
$usersResult = $conn->query("SELECT id, nama_lengkap, username FROM users WHERE role != 'admin' AND id != $loggedInUserId ORDER BY nama_lengkap ASC");

$permissions = [];
$permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup, nama_izin ASC");
while($row = $permResult->fetch_assoc()) {
    $permissions[$row['grup']][] = $row;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$users = [];
while($u = $usersResult->fetch_assoc()) $users[] = $u;
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --bg:         #f8fafc; /* Slate 50 - fresh, clean, professional */
    --surface:    #ffffff;
    --border:     #e2e8f0; /* Slate 200 - subtle, clean borders */
    --border-2:   #cbd5e1; /* Slate 300 - active borders */
    --text-1:     #0f172a; /* Slate 900 - high contrast, highly readable */
    --text-2:     #475569; /* Slate 600 - secondary text */
    --text-3:     #64748b; /* Slate 500 - tertiary/muted text */
    
    --accent:     #4f46e5; /* Indigo 600 - professional accent */
    --accent-bg:  #e0e7ff; /* Indigo 100 - active background tint */
    --accent-dim: #f5f3ff; /* Indigo 50 - extremely light tint */
    
    --red:        #e11d48; /* Rose 600 - natural warm red for destructive/remove actions */
    --red-bg:     #fff1f2; /* Rose 50 - soft red bg */
    --red-dim:    #ffe4e6; /* Rose 100 - soft red border */
    
    --green:      #059669; /* Emerald 600 - natural green for success/grant actions */
    --green-bg:   #ecfdf5; /* Emerald 50 - soft green bg */
    --green-dim:  #d1fae5; /* Emerald 100 - soft green border */
    
    --font:       'Poppins', 'DM Sans', sans-serif;
    --mono:       'JetBrains Mono', monospace;
    
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
}

body { background: var(--bg); }

/* ── Wrap ──────────────────────────────────────────────────────── */
.bm {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3rem 2rem 8rem;
    font-family: var(--font);
    color: var(--text-1);
}

/* ── Page Header ───────────────────────────────────────────────── */
.bm-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.bm-top-left h1 {
    font-size: 1.85rem;
    font-weight: 700;
    letter-spacing: -.025em;
    color: var(--text-1);
    margin: 0 0 .5rem;
}

.bm-top-left p {
    font-size: 0.95rem;
    color: var(--text-3);
    margin: 0;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem .875rem;
    font-size: .875rem;
    font-family: var(--font);
    font-weight: 600;
    color: var(--text-2);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    text-decoration: none;
    white-space: nowrap;
    transition: all .15s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.btn-back:hover { 
    border-color: var(--border-2); 
    color: var(--text-1); 
    background: #fafafb; 
    transform: translateY(-1px);
}
.btn-back svg { opacity: .7; width: 12px; height: 12px; }

/* ── Alert ─────────────────────────────────────────────────────── */
.bm-alert {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .875rem 1.125rem;
    border-radius: 10px;
    font-size: .875rem;
    margin-bottom: 2rem;
    border: 1px solid;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.bm-alert.success { background: var(--green-bg); border-color: var(--green-dim); color: var(--green); }
.bm-alert.error   { background: var(--red-bg);   border-color: var(--red-dim); color: var(--red); }
.bm-alert .dismiss { margin-left: auto; background: none; border: none; cursor: pointer; opacity: .6; padding: 0; line-height: 1; color: inherit; }
.bm-alert .dismiss:hover { opacity: 1; }
.bm-alert svg { width: 18px; height: 18px; flex-shrink: 0; }
.bm-alert .dismiss svg { width: 12px; height: 12px; }

/* ── Section label ─────────────────────────────────────────────── */
.section-label {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1.5rem;
}

.section-label-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: #fff;
    font-size: .875rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.25);
}

.section-label-text {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: -.015em;
}

.section-label-sub {
    font-size: .875rem;
    color: var(--text-3);
    margin-left: .25rem;
    font-weight: 400;
}

/* ── Section divider ───────────────────────────────────────────── */
.section-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 3rem 0;
}

/* ── Utility buttons ───────────────────────────────────────────── */
.btn-sm {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    height: 38px;
    padding: 0 .875rem;
    font-size: .875rem;
    font-family: var(--font);
    font-weight: 600;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.btn-sm:hover { 
    border-color: var(--border-2); 
    background: #fafafb; 
    color: var(--text-1); 
    transform: translateY(-1px);
}
.btn-sm.danger:hover { 
    border-color: var(--red-dim); 
    background: var(--red-bg); 
    color: var(--red); 
}
.btn-sm svg { width: 12px; height: 12px; }

/* ── User Picker ───────────────────────────────────────────────── */
.user-toolbar {
    display: flex;
    gap: .75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.user-search {
    flex: 1;
    min-width: 240px;
    height: 40px;
    padding: 0 1rem 0 2.5rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: .875rem;
    font-family: var(--font);
    color: var(--text-1);
    background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat .75rem center;
    outline: none;
    transition: all .15s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.user-search::placeholder { color: var(--text-3); }
.user-search:focus { 
    border-color: var(--accent); 
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); 
}

.user-counter-pill {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .35rem .75rem;
    background: var(--accent-dim);
    border: 1px solid var(--accent-bg);
    border-radius: 99px;
    font-size: .875rem;
    font-weight: 700;
    color: var(--accent);
    transition: all .2s;
    margin-left: auto;
}
.user-counter-pill svg { width: 12px; height: 12px; }

/* User grid — open, no scroll prison */
.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: .875rem;
}

@media (max-width: 640px) {
    .user-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .user-grid { grid-template-columns: 1fr; }
}

.user-chip {
    display: flex;
    align-items: center;
    gap: .875rem;
    padding: .875rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    background: var(--surface);
    transition: all .2s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    -webkit-user-select: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.user-chip:hover {
    border-color: var(--border-2);
    background: #fafafb;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.user-chip.selected {
    border-color: var(--accent);
    background: #f5f3ff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
}
.user-chip.hidden { display: none; }

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    font-size: 1.05rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: .02em;
    transition: all .2s ease;
}
.user-chip.selected .user-avatar {
    background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
}

.user-chip-meta { min-width: 0; flex: 1; }
.user-chip-name {
    font-size: .95rem;
    font-weight: 600;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color .15s;
    margin-bottom: .15rem;
}
.user-chip.selected .user-chip-name { color: var(--accent); }

.user-chip-uname {
    font-size: .8rem;
    color: var(--text-3);
    font-family: var(--mono);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-chip-tick {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid var(--border-2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: transparent;
    transition: all .15s ease;
}
.user-chip.selected .user-chip-tick {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}
.user-chip-tick svg { width: 10px; height: 10px; }

.hidden-inputs { display: none; }

/* ── Legend ────────────────────────────────────────────────────── */
.legend-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 2rem;
    background: #f1f5f9;
    padding: .75rem 1.25rem;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .875rem;
    color: var(--text-2);
    font-weight: 600;
}

.ldot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.ldot.green { background: var(--green); box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15); }
.ldot.gray  { background: var(--text-3); box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.15); }
.ldot.red   { background: var(--red); box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.15); }

/* ── Permission groups ─────────────────────────────────────────── */
.perm-groups {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .perm-groups { grid-template-columns: 1fr; }
}

/* group block */
.perm-group {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.perm-group:hover {
    transform: translateY(-2px);
    box-shadow: var(--card-shadow-hover);
    border-color: var(--border-2);
}
.perm-group::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1, #3b82f6);
}

.perm-group-label {
    font-size: .85rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-top: 0.25rem;
}

.perm-group-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* per-row */
.perm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
    transition: background-color .15s ease;
}
.perm-row:last-child { border-bottom: none; }

.perm-info { flex: 1; min-width: 0; }

.perm-desc {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-1);
    margin-bottom: .25rem;
    line-height: 1.4;
}

.perm-key {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .75rem;
    font-family: var(--mono);
    color: var(--text-3);
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
}

/* ── 3-state toggle ────────────────────────────────────────────── */
.tri {
    display: inline-flex;
    background: #f1f5f9;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 2px;
    gap: 2px;
    overflow: hidden;
    flex-shrink: 0;
}

.tri input[type="radio"] { display: none; }

.tri label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    padding: .5rem .875rem;
    font-size: .875rem;
    font-family: var(--font);
    font-weight: 600;
    color: var(--text-2);
    cursor: pointer;
    border-radius: 6px;
    transition: all .15s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    line-height: 1.2;
    border: 1px solid transparent;
}
.tri label:hover { 
    background: rgba(0, 0, 0, 0.04); 
    color: var(--text-1); 
}
.tri label svg { 
    width: 11px; 
    height: 11px;
    transition: transform .15s ease;
}
.tri label:hover svg {
    transform: scale(1.1);
}

.tri input:checked + .lbl-remove { 
    background: var(--red); 
    color: #ffffff; 
    border-color: var(--red);
    box-shadow: 0 2px 4px rgba(225, 29, 72, 0.2);
}
.tri input:checked + .lbl-keep { 
    background: var(--text-2); 
    color: #ffffff; 
    border-color: var(--text-2);
    box-shadow: 0 2px 4px rgba(71, 85, 105, 0.2);
}
.tri input:checked + .lbl-grant { 
    background: var(--green); 
    color: #ffffff; 
    border-color: var(--green);
    box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
}

/* ── Perm group gutter on 2-col ────────────────────────────────── */
@media (min-width: 769px) {
    .perm-groups { column-gap: 1.5rem; }
    .perm-group { padding-right: 1.5rem; }
}

/* ── Sticky footer ─────────────────────────────────────────────── */
.bm-footer {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-top: 1px solid var(--border);
    padding: 1rem 2rem;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
}

.footer-meta {
    font-size: .875rem;
    color: var(--text-2);
    line-height: 1.5;
}
.footer-meta strong { color: var(--text-1); font-weight: 700; }

.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.5rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: .95rem;
    font-family: var(--font);
    font-weight: 600;
    cursor: pointer;
    letter-spacing: -.01em;
    transition: all .2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px rgba(79, 70, 229, 0.25);
}
.btn-submit:hover { 
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(79, 70, 229, 0.35);
}
.btn-submit:active { transform: translateY(0); }
.btn-submit svg { width: 14px; height: 14px; }

/* ── Responsive logic for Mobile ───────────────────────────────── */
@media (max-width: 640px) {
    .bm { padding: 2rem 1rem 3rem; }
    .bm-top { flex-direction: column; align-items: flex-start; }
    .btn-back { width: 100%; justify-content: center; margin-top: 1rem; }
    .user-toolbar { flex-direction: column; align-items: stretch; }
    .user-search { width: 100%; margin-bottom: .5rem; }
    .user-counter-pill { margin-left: 0; align-self: flex-start; }
    .perm-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .tri { width: 100%; }
    .tri label { flex: 1; justify-content: center; }
    .bm-footer { 
        position: static;
        flex-direction: column; 
        padding: 2rem 0 0 0; 
        text-align: center; 
        background: transparent;
        border-top: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        margin-top: 1.5rem;
    }
    .btn-submit { width: 100%; justify-content: center; padding: 1rem; }
}
</style>

<div class="bm">

    <!-- ── Header ──────────────────────────────────────────────── -->
    <div class="bm-top">
        <div class="bm-top-left">
            <h1>Edit Izin Massal</h1>
            <p>Terapkan atau cabut akses ke beberapa pengguna sekaligus.</p>
        </div>
        <a href="index.php" class="btn-back">
            <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M7.5 2L3.5 6l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali
        </a>
    </div>

    <!-- ── Alerts ───────────────────────────────────────────────── -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bm-alert success">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#16a34a" stroke-width="1.5"/><path d="M5 8l2.5 2.5L11 5.5" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span><?= $_SESSION['success_message'] ?></span>
            <button class="dismiss" onclick="this.closest('.bm-alert').remove()">
                <svg width="13" height="13" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bm-alert error">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#dc2626" stroke-width="1.5"/><path d="M8 5v3.5M8 10.5v.5" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span><?= $_SESSION['error_message'] ?></span>
            <button class="dismiss" onclick="this.closest('.bm-alert').remove()">
                <svg width="13" height="13" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form action="process_bulk.php" method="POST" id="bulk-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- ── Step 1: Pilih User ──────────────────────────────── -->
        <div class="section-label">
            <div class="section-label-num">1</div>
            <span class="section-label-text">Pilih Pengguna <span class="section-label-sub">— klik chip untuk memilih</span></span>
        </div>

        <div class="user-toolbar">
            <input type="text" class="user-search" id="user-search" placeholder="Cari nama atau username…" autocomplete="off">
            <button type="button" class="btn-sm" id="select-all-btn">
                <svg width="12" height="12" viewBox="0 0 12 10" fill="none"><path d="M1 5l3 3 7-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Pilih Semua
            </button>
            <button type="button" class="btn-sm danger" id="clear-all-btn">
                <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Bersihkan
            </button>
            <div class="user-counter-pill" id="counter-pill">
                <svg width="11" height="11" viewBox="0 0 12 10" fill="none"><path d="M1 5l3 3 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span id="sel-count">0</span> dipilih
            </div>
        </div>

        <div class="user-grid" id="user-grid">
            <?php foreach($users as $u):
                $initials = '';
                foreach (explode(' ', trim($u['nama_lengkap'])) as $w)
                    $initials .= strtoupper(mb_substr($w, 0, 1));
                $initials = mb_substr($initials, 0, 2);

                // Deterministic gradient classes for organic professional colors
                $gradients = [
                    'linear-gradient(135deg, #e0f2fe, #bae6fd)', // Soft Blue
                    'linear-gradient(135deg, #fef3c7, #fde68a)', // Soft Amber
                    'linear-gradient(135deg, #dcfce7, #bbf7d0)', // Soft Emerald
                    'linear-gradient(135deg, #f3e8ff, #e9d5ff)', // Soft Purple
                    'linear-gradient(135deg, #ffe4e6, #fecdd3)', // Soft Rose
                ];
                $text_colors = ['#0369a1', '#b45309', '#047857', '#6d28d9', '#be123c'];
                $idx = ((int)$u['id']) % 5;
                $avatar_style = "background: " . $gradients[$idx] . "; color: " . $text_colors[$idx] . ";";
            ?>
            <div class="user-chip"
                 data-id="<?= $u['id'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($u['nama_lengkap'])) ?>"
                 data-uname="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                 onclick="toggleUser(this)">
                <div class="user-avatar" style="<?= $avatar_style ?>"><?= $initials ?></div>
                <div class="user-chip-meta">
                    <div class="user-chip-name"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                    <div class="user-chip-uname">@<?= htmlspecialchars($u['username']) ?></div>
                </div>
                <div class="user-chip-tick">
                    <svg width="9" height="9" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="hidden-inputs" id="hidden-inputs"></div>

        <hr class="section-divider">

        <!-- ── Step 2: Atur Izin ─────────────────────────────── -->
        <div class="section-label" style="margin-bottom:1rem">
            <div class="section-label-num">2</div>
            <span class="section-label-text">Atur Perizinan <span class="section-label-sub">— pilih tindakan per izin</span></span>
        </div>

        <div class="legend-row">
            <div class="legend-item"><div class="ldot green"></div> Berikan izin</div>
            <div class="legend-item"><div class="ldot gray"></div> Biarkan (tidak berubah)</div>
            <div class="legend-item"><div class="ldot red"></div> Cabut izin</div>
        </div>

        <div class="perm-groups">
            <?php foreach ($permissions as $grup => $items): ?>
            <div class="perm-group">
                <div class="perm-group-label"><?= htmlspecialchars($grup) ?></div>
                <?php foreach ($items as $perm): ?>
                <div class="perm-row">
                    <div class="perm-info">
                        <div class="perm-desc"><?= htmlspecialchars($perm['deskripsi']) ?></div>
                        <div class="perm-key">
                            <svg width="9" height="9" viewBox="0 0 12 12" fill="none" style="opacity:.5"><path d="M8 1a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM5.5 5.5L1 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <?= htmlspecialchars($perm['nama_izin']) ?>
                        </div>
                    </div>
                    <div class="tri" role="group">
                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="rem_<?= $perm['id'] ?>" value="remove" autocomplete="off">
                        <label class="lbl-remove" for="rem_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 2l6 6M8 2L2 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Cabut
                        </label>

                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="keep_<?= $perm['id'] ?>" value="no_change" autocomplete="off" checked>
                        <label class="lbl-keep" for="keep_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 4" fill="none"><path d="M1 2h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Biarkan
                        </label>

                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="add_<?= $perm['id'] ?>" value="add" autocomplete="off">
                        <label class="lbl-grant" for="add_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Berikan
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Sticky Footer ─────────────────────────────────── -->
        <div class="bm-footer">
            <div class="footer-meta">
                Menerapkan ke <strong id="footer-count">0 pengguna</strong>
            </div>
            <button type="submit" class="btn-submit">
                <svg width="13" height="13" viewBox="0 0 14 12" fill="none"><path d="M1.5 6.5l4 4 7-8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Terapkan Perubahan
            </button>
        </div>

    </form>
</div>

<script>
(function () {
    const selected = new Set();

    function sync() {
        const n = selected.size;
        document.getElementById('sel-count').textContent = n;
        document.getElementById('footer-count').textContent = n + ' pengguna';

        const wrap = document.getElementById('hidden-inputs');
        wrap.innerHTML = '';
        selected.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'user_ids[]';
            inp.value = id;
            wrap.appendChild(inp);
        });
    }

    window.toggleUser = function (chip) {
        const id = chip.dataset.id;
        if (selected.has(id)) { selected.delete(id); chip.classList.remove('selected'); }
        else                  { selected.add(id);    chip.classList.add('selected'); }
        sync();
    };

    document.getElementById('select-all-btn').addEventListener('click', () => {
        document.querySelectorAll('.user-chip:not(.hidden)').forEach(c => {
            selected.add(c.dataset.id); c.classList.add('selected');
        });
        sync();
    });

    document.getElementById('clear-all-btn').addEventListener('click', () => {
        selected.clear();
        document.querySelectorAll('.user-chip').forEach(c => c.classList.remove('selected'));
        sync();
    });

    document.getElementById('user-search').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.user-chip').forEach(c => {
            c.classList.toggle('hidden', q && !c.dataset.name.includes(q) && !c.dataset.uname.includes(q));
        });
    });

    document.getElementById('bulk-form').addEventListener('submit', function (e) {
        e.preventDefault();
        
        if (selected.size === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Belum ada pengguna dipilih',
                text: 'Silakan pilih minimal satu pengguna untuk menerapkan perubahan.',
                confirmButtonColor: '#4f46e5'
            });
            return;
        }
        const hasChange = [...document.querySelectorAll('input[type="radio"]:checked')]
            .some(r => r.value !== 'no_change');
        if (!hasChange) {
            Swal.fire({
                icon: 'info',
                title: 'Tidak ada tindakan',
                text: 'Semua izin masih diatur ke "Biarkan". Anda harus menentukan minimal satu tindakan (Berikan/Cabut).',
                confirmButtonColor: '#4f46e5'
            });
            return;
        }
        
        Swal.fire({
            title: 'Terapkan Perubahan?',
            text: `Anda akan menerapkan pengaturan izin ini ke ${selected.size} pengguna terpilih.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Terapkan!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    sync();
})();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>