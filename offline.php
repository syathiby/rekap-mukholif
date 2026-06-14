<?php
// Offline fallback page — tidak butuh koneksi untuk tampil
// File ini di-cache oleh service worker saat pertama install
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tidak Ada Koneksi — AsuhTrack</title>
    <meta name="theme-color" content="#16a34a">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .wifi-icon {
            font-size: 2.2rem;
            display: block;
        }

        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        p {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .btn-retry {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.35);
        }

        .btn-retry:active {
            transform: translateY(0);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 1rem;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f87171;
            animation: blink 1.5s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .app-name {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <span class="wifi-icon">📶</span>
        </div>

        <h1>Tidak Ada Koneksi</h1>
        <p>Sepertinya perangkat Anda sedang offline. Periksa koneksi internet Anda dan coba lagi.</p>

        <button class="btn-retry" onclick="retryConnection()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Coba Lagi
        </button>

        <div class="status">
            <span class="dot"></span>
            <span id="status-text">Menunggu koneksi...</span>
        </div>

        <div class="app-name">AsuhTrack</div>
    </div>

    <script>
        // Auto-retry setiap 5 detik
        let retryCount = 0;

        function retryConnection() {
            const btn = document.querySelector('.btn-retry');
            const statusEl = document.getElementById('status-text');

            btn.style.opacity = '0.7';
            btn.disabled = true;
            statusEl.textContent = 'Mencoba menghubungkan...';

            fetch('./dashboard.php', { method: 'HEAD', cache: 'no-store' })
                .then(response => {
                    if (response.ok) {
                        statusEl.textContent = 'Koneksi berhasil! Mengalihkan...';
                        window.location.href = './dashboard.php';
                    }
                })
                .catch(() => {
                    retryCount++;
                    statusEl.textContent = `Belum ada koneksi (percobaan ke-${retryCount})`;
                    btn.style.opacity = '1';
                    btn.disabled = false;
                });
        }

        // Monitor status koneksi secara real-time
        window.addEventListener('online', () => {
            document.getElementById('status-text').textContent = 'Koneksi terdeteksi! Mengalihkan...';
            document.querySelector('.dot').style.background = '#4ade80';
            setTimeout(() => {
                window.location.href = './dashboard.php';
            }, 800);
        });

        window.addEventListener('offline', () => {
            document.getElementById('status-text').textContent = 'Masih offline...';
            document.querySelector('.dot').style.background = '#f87171';
        });

        // Auto-retry setiap 10 detik
        setInterval(() => {
            if (navigator.onLine) {
                retryConnection();
            }
        }, 10000);
    </script>
</body>
</html>
