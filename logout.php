<?php
/**
 * logout.php — Admin Session Termination & Zero-Trash Browser Cleanup
 * AsuhTrack Admin Panel
 *
 * Menghapus semua jejak sesi di sisi server DAN browser,
 * termasuk Service Worker caches, IndexedDB, cookies, dan storage.
 * Mendukung ?timeout=1 atau ?reason=timeout (auto-logout).
 */

require_once __DIR__ . '/bootstrap/init.php';

$is_auto_logout = isset($_GET['timeout']) || ($_GET['reason'] ?? '') === 'timeout' || isset($_GET['access_denied']);

// ── PROTEKSI CSRF ──────────────────────────────────────────────────────────
if (!$is_auto_logout) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed. Gunakan metode POST untuk logout demi keamanan.');
    }
    csrf_validate(); // Pastikan request POST memiliki token CSRF yang valid
} else {
    // Jika karena timeout, pastikan session user ID memang kosong (sudah dihancurkan oleh init.php)
    // Jika user masih login aktif, berarti ada orang yang iseng ngirim ?timeout=1 (CSRF attack via GET)
    if (isset($_SESSION['user_id'])) {
        // Gagal, ini percobaan CSRF logout via URL
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

$login_redirect = BASE_URL . '/login.php' . ($is_auto_logout ? '?timeout=1' : '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Catat log logout hanya jika disengaja (POST)
if (!$is_auto_logout && isset($_SESSION['username'])) {
    write_activity_log('LOGOUT', 'auth', "User '" . $_SESSION['username'] . "' melakukan logout dari sistem");
}

// ── 1. Hancurkan session server-side ──────────────────────────────────────
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // Set expired di masa lalu untuk semua kemungkinan path
    setcookie(session_name(), '', time() - 86400,
        $params["path"],   $params["domain"],
        $params["secure"], $params["httponly"]
    );
    setcookie(session_name(), '', time() - 86400, '/');
}

session_destroy();

// ── 2. Anti-Cache headers (agar halaman ini tidak di-cache) ───────────────
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Keluar — AsuhTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #09090b;
            --bg-card: #18181b;
            --border-color: #27272a;
            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;
            --accent: #3b82f6;
            --success: #10b981;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        /* Subtle top progress indicator */
        .progress-track {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: transparent;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background-color: var(--accent);
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .header {
            margin-bottom: 24px;
            text-align: left;
        }

        .header h1 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header p {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .steps {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.875rem;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .step-item.active {
            color: var(--text-main);
        }

        .step-item.done {
            color: var(--text-main);
        }

        .icon-box {
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Clean CSS Spinner */
        .spinner {
            width: 14px;
            height: 14px;
            border: 2px solid transparent;
            border-top-color: var(--text-muted);
            border-right-color: var(--text-muted);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        .step-item.active .spinner {
            border-top-color: var(--accent);
            border-right-color: var(--accent);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* SVG Checkmark */
        .check-svg {
            width: 16px;
            height: 16px;
            color: var(--success);
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke: currentColor;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .step-item.done .icon-box {
            position: relative;
        }
        
        .step-item.done .spinner {
            display: none;
        }
        
        .step-item.done .check-svg {
            opacity: 1;
            transform: scale(1);
        }

        /* Success State adjustments */
        .success-mode .progress-bar {
            background-color: var(--success);
        }

    </style>
</head>
<body>

<div class="container" id="mainContainer">
    <div class="progress-track">
        <div class="progress-bar" id="progressBar"></div>
    </div>
    
    <div class="header">
        <h1 id="mainTitle">Keluar dari AsuhTrack</h1>
        <p id="subTitle">Menghapus sisa data demi keamanan akun Anda</p>
    </div>

    <ul class="steps" id="stepList">
        <li class="step-item" id="step0">
            <div class="icon-box"><div class="spinner" id="spin0"></div><svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span>Mengakhiri sesi akun</span>
        </li>
        <li class="step-item" id="step1">
            <div class="icon-box"><div class="spinner" id="spin1"></div><svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span>Membersihkan memori browser</span>
        </li>
        <li class="step-item" id="step2">
            <div class="icon-box"><div class="spinner" id="spin2"></div><svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span>Menghapus data sementara (Cache)</span>
        </li>
        <li class="step-item" id="step3">
            <div class="icon-box"><div class="spinner" id="spin3"></div><svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span>Menghapus jejak login (Cookies)</span>
        </li>
        <li class="step-item" id="step4">
            <div class="icon-box"><div class="spinner" id="spin4"></div><svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span>Membersihkan data offline</span>
        </li>
    </ul>
</div>

<script>
'use strict';

const totalSteps = 5;
let currentStep = 0;

function updateProgress() {
    const percentage = (currentStep / totalSteps) * 100;
    document.getElementById('progressBar').style.width = percentage + '%';
}

function setStepState(id, state) {
    const el = document.getElementById(id);
    if (!el) return;
    
    if (state === 'active') {
        el.classList.add('active');
        // spinner is active by default in CSS, just inherit colors
    } else if (state === 'done') {
        el.classList.remove('active');
        el.classList.add('done');
        currentStep++;
        updateProgress();
    }
}

async function zeroTrashLogout() {
    // Initial delay
    await sleep(400);

    // STEP 0: Session storage & local storage
    setStepState('step0', 'active');
    try {
        sessionStorage.clear();
        localStorage.clear();
    } catch(e) {}
    await sleep(250);
    setStepState('step0', 'done');

    // STEP 1: Semua storage (Double-clean)
    setStepState('step1', 'active');
    try {
        if (window.localStorage) {
            const lKeys = Object.keys(localStorage);
            lKeys.forEach(k => localStorage.removeItem(k));
        }
        if (window.sessionStorage) {
            const sKeys = Object.keys(sessionStorage);
            sKeys.forEach(k => sessionStorage.removeItem(k));
        }
    } catch(e) {}
    await sleep(250);
    setStepState('step1', 'done');

    // STEP 2: Service Worker Caches
    setStepState('step2', 'active');
    try {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map(name => caches.delete(name)));
        }
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (const reg of registrations) {
                if (reg.active) {
                    reg.active.postMessage({ type: 'CLEAR_ALL_CACHES' });
                }
                await reg.unregister();
            }
        }
    } catch(e) {}
    await sleep(250);
    setStepState('step2', 'done');

    // STEP 3: Cookies Browser
    setStepState('step3', 'active');
    try {
        const cookies = document.cookie.split(';');
        for (const cookie of cookies) {
            const eqPos = cookie.indexOf('=');
            const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
            if (!name) continue;
            const expiry = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = `${name}=; ${expiry}; path=/`;
            document.cookie = `${name}=; ${expiry}; path=/admin/`;
            document.cookie = `${name}=; ${expiry}; path=/; domain=${location.hostname}`;
        }
    } catch(e) {}
    await sleep(250);
    setStepState('step3', 'done');

    // STEP 4: IndexedDB
    setStepState('step4', 'active');
    try {
        if (window.indexedDB && indexedDB.databases) {
            const dbs = await indexedDB.databases();
            await Promise.all(
                dbs.map(db => new Promise((resolve) => {
                    const req = indexedDB.deleteDatabase(db.name);
                    req.onsuccess = resolve;
                    req.onerror   = resolve;
                    req.onblocked = resolve;
                }))
            );
        }
    } catch(e) {}
    await sleep(250);
    setStepState('step4', 'done');

    // FINAL STATE
    finishSequence();
}

async function finishSequence() {
    document.getElementById('mainContainer').classList.add('success-mode');
    document.getElementById('mainTitle').textContent = 'Selesai';
    
    // Pause briefly for user to see all checkmarks
    await sleep(600);

    // Redirect
    window.location.replace('<?php echo addslashes($login_redirect); ?>');
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

window.addEventListener('DOMContentLoaded', zeroTrashLogout);
</script>
</body>
</html>
<?php exit; ?>