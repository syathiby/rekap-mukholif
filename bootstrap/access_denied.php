<?php
// Pastikan BASE_URL udah didefinisikan di config/app.php
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aduh, Ketahuan Deh! 🤭</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-light: #f8fafc;
            --text-main: #334155;
            --text-muted: #64748b;
            --primary-accent: #f43f5e;
            --primary-light: #fff1f2;
            --primary-border: #fecdd3;
            --gold-accent: #f59e0b;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.6);
            --font-display: 'Outfit', sans-serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
        }

        body {
            background-color: var(--bg-light);
            /* Gradien pastel yang sangat lembut dan tenang */
            background-image: 
                radial-gradient(circle at 5% 5%, #fef2f2 0%, transparent 35%),
                radial-gradient(circle at 95% 95%, #eff6ff 0%, transparent 35%),
                radial-gradient(circle at 50% 50%, #faf5ff 0%, transparent 40%);
            font-family: var(--font-sans);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1200px;
            transition: background-color 0.6s ease;
        }

        /* Alarm lembut saat kado dibuka */
        body.opened {
            animation: soft-alarm-pulse 3s infinite alternate;
        }

        .main-container {
            width: 100%;
            max-width: 540px;
            padding: 1.5rem;
            text-align: center;
            z-index: 10;
        }

        /* ==========================================
           1. DESAIN KADO MINIMALIS & ELEGAN (LIGHT)
           ========================================== */
        .gift-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .glass-card-pre {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(30px);
            border-radius: 32px;
            padding: 3rem 2rem;
            box-shadow: 
                0 10px 30px -10px rgba(148, 163, 184, 0.12),
                0 30px 60px -15px rgba(148, 163, 184, 0.18);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        /* Pantulan cahaya soft di kartu */
        .glass-card-pre::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 300%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.4), transparent);
            transform: skewX(-25deg);
            animation: shine-slow 6s infinite ease-in-out;
        }

        .gift-title {
            font-family: var(--font-display);
            font-size: 1.45rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        /* Animasi Kado Pastel Melayang */
        .gift-container {
            position: relative;
            width: 140px;
            height: 140px;
            cursor: pointer;
            transition: transform 0.3s ease;
            animation: float-soft 4s ease-in-out infinite;
        }

        .gift-container:hover {
            animation: shake-gentle 0.4s infinite;
        }

        /* Bagian Kotak Kado Pastel */
        .gift-lid { 
            position: absolute; 
            width: 140px; 
            height: 38px; 
            background: linear-gradient(135deg, #fda4af, #f43f5e); 
            border-radius: 6px 6px 2px 2px; 
            top: 32px; 
            left: 0; 
            z-index: 3; 
            box-shadow: 0 4px 10px rgba(244, 63, 94, 0.15);
        }
        
        .gift-box { 
            position: absolute; 
            width: 126px; 
            height: 82px; 
            background: linear-gradient(135deg, #f43f5e, #e11d48); 
            border-radius: 0 0 10px 10px; 
            bottom: 0; 
            left: 7px; 
            z-index: 2; 
            box-shadow: inset 0 6px 12px rgba(0,0,0,0.06);
        }
        
        .ribbon-vertical { 
            position: absolute; 
            width: 18px; 
            height: 96px; 
            background: linear-gradient(to bottom, #fef08a, #fde047); 
            top: 32px; 
            left: 61px; 
            z-index: 4; 
            border-radius: 1px; 
        }
        
        .bow { 
            position: absolute; 
            width: 38px; 
            height: 30px; 
            background: #fde047; 
            border-radius: 50%; 
            top: 14px; 
            left: 51px; 
            z-index: 5; 
        }
        
        .bow::before, .bow::after { 
            content: ''; 
            position: absolute; 
            width: 28px; 
            height: 28px; 
            background: #fef08a; 
            border-radius: 50%; 
            top: -4px; 
        }
        
        .bow::before { left: -14px; transform: rotate(-30deg); }
        .bow::after { right: -14px; transform: rotate(30deg); }

        .click-instruction { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(148, 163, 184, 0.05);
            padding: 8px 16px;
            border-radius: 50px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            animation: pulse-soft 2.5s infinite; 
        }

        .click-instruction i {
            color: var(--primary-accent);
        }

        /* ==========================================
           2. TAMPILAN INTERAKTIF YANG MANUSIAWI & HALUS
           ========================================== */
        .error-card-wrapper {
            opacity: 0;
            transform: scale(0.92) translateY(15px);
            transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: none;
            width: 100%;
        }

        .glass-card-post {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(35px);
            border-radius: 36px;
            padding: 3rem 2rem;
            box-shadow: 
                0 20px 50px -10px rgba(15, 23, 42, 0.08),
                0 40px 80px -20px rgba(15, 23, 42, 0.12);
            position: relative;
            overflow: hidden;
        }

        .troll-emoji {
            font-size: 6rem;
            display: inline-block;
            margin-bottom: 1.25rem;
            transform-origin: center bottom;
            animation: emoji-playful 2s infinite ease-in-out alternate;
        }

        .error-title {
            font-family: var(--font-display);
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1.2;
            color: #1e293b;
            margin-bottom: 1rem;
            letter-spacing: -0.75px;
        }

        .error-message {
            font-size: 0.98rem;
            color: var(--text-muted);
            line-height: 1.6;
            max-width: 420px;
            margin: 0 auto 2.5rem auto;
        }

        .error-message strong {
            color: var(--primary-accent);
            font-weight: 700;
        }

        /* Desain Timer Kalem yang Rapi */
        .timer-container {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: var(--primary-light);
            border: 1px solid var(--primary-border);
            padding: 1.2rem 3rem;
            border-radius: 24px;
            position: relative;
        }

        .countdown-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #f43f5e;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .countdown-digits {
            font-family: var(--font-display);
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--primary-accent);
            display: flex;
            align-items: center;
            gap: 1px;
        }

        .digits-desc {
            font-size: 1.2rem;
            color: #fb7185;
            margin-left: 2px;
        }

        /* ==========================================
           3. EMOJI PARTIKEL JATUH LAMBAT DAN BANYAK
           ========================================== */
        .emoji-particle {
            position: absolute;
            font-size: 2rem;
            user-select: none;
            pointer-events: none;
            opacity: 0;
            z-index: 100;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.04));
        }

        /* Kecepatan lambat & gerakan mengayun halus */
        @keyframes emoji-fall-slow {
            0% {
                transform: translateY(-80px) translateX(0) rotate(0deg) scale(0.6);
                opacity: 0;
            }
            10% {
                opacity: 0.85;
            }
            90% {
                opacity: 0.85;
            }
            100% {
                transform: translateY(105vh) translateX(var(--drift-width)) rotate(var(--spin-degree)) scale(1.1);
                opacity: 0;
            }
        }

        /* KEYFRAMES & KONDISI TRANSISI */
        body.opened .gift-wrapper {
            transform: translateY(-20px) scale(0.85);
            opacity: 0;
            pointer-events: none;
            display: none;
        }

        body.opened .error-card-wrapper {
            opacity: 1;
            transform: scale(1) translateY(0);
            display: block;
        }

        @keyframes float-soft {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes shine-slow {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        @keyframes pulse-soft {
            0%, 100% { opacity: 0.7; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }

        @keyframes soft-alarm-pulse {
            0% { background-color: var(--bg-light); }
            100% { background-color: #fff8f8; }
        }

        @keyframes emoji-playful {
            0% { transform: rotate(-5deg) translateY(0); }
            100% { transform: rotate(5deg) translateY(-8px); }
        }

        @keyframes shake-gentle {
            0%, 100% { transform: translateX(0) rotate(0); }
            25% { transform: translateX(-4px) rotate(-1.5deg); }
            75% { transform: translateX(4px) rotate(1.5deg); }
        }

        @keyframes shake-timer {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <!-- BAGIAN 1: WRAPPER KADO ELEGAN -->
        <div class="gift-wrapper" id="giftWrapper">
            <div class="glass-card-pre">
                <h2 class="gift-title">Aduh, Ada Kado Kejutan Untukmu! 🎁</h2>
                <div class="gift-container" id="giftBox">
                    <div class="bow"></div>
                    <div class="gift-lid"></div>
                    <div class="ribbon-vertical"></div>
                    <div class="gift-box"></div>
                </div>
                <div class="click-instruction">
                    <i class="fas fa-hand-pointer"></i> Klik pelan untuk membuka kado
                </div>
            </div>
        </div>

        <!-- BAGIAN 2: SCREEN TROLL / MENGEJEK HALUS -->
        <div class="error-card-wrapper" id="errorCard">
            <div class="glass-card-post">
                <div class="troll-emoji">🤭</div>
                <h1 class="error-title">Aduh, Ketahuan Deh... 🤭</h1>
                <p class="error-message">
                    Sayang sekali, sistem mendeteksi tindakan <strong>bypass akses ilegal</strong> yang Anda lakukan. Hadiah spesial buat Anda yang kreatif: <strong>Logout Otomatis!</strong>
                </p>
                
                <!-- Bomb Countdown Box -->
                <div class="timer-container">
                    <span class="countdown-label">Sesi Anda Selesai Dalam</span>
                    <div class="countdown-digits">
                        <span id="countdown">10</span>
                        <span class="digits-desc">s</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        const BASE_URL = '<?= defined('BASE_URL') ? BASE_URL : '' ?>';
        const giftBox = document.getElementById('giftBox');
        const countdownElement = document.getElementById('countdown');
        let hasBeenClicked = false;

        giftBox.addEventListener('click', () => {
            if (hasBeenClicked) return;
            hasBeenClicked = true;

            // 1. Bunyikan nada "Ding-Dong!" gembira baru disusul sad trombone cempreng
            playPoliteTrollSound();

            // 2. Aktifkan visual alarm pastel lembut
            document.body.classList.add('opened');
            
            // 3. Picu hujan emoji yang BANYAK dan LAMBAT jatuh ke bawah
            triggerSlowEmojiRain();
            
            // 4. Logika countdown
            let countdown = 10;
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                
                const timerContainer = document.querySelector('.timer-container');
                if (countdown <= 3 && timerContainer) {
                    timerContainer.style.animation = 'shake-timer 0.2s infinite';
                }

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `${BASE_URL}/logout.php`;
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = 'csrf_token';
                    csrf.value = '<?= csrf_generate() ?>';
                    form.appendChild(csrf);
                    document.body.appendChild(form);
                    form.submit();
                }
            }, 1000);
        });

        // =======================================================
        // 🎼 EFEK SUARA MANUSIAWI (DING-DONG GEMBIRA + SAD TROMBONE)
        // =======================================================
        function playPoliteTrollSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                let time = audioCtx.currentTime;
                
                // A. NADA DING-DONG GEMBIRA (Menarik perhatian seolah berhasil)
                const dingNotes = [587.33, 880.00]; // D5 (Tinggi), A5 (Lebih Tinggi)
                const dingTimes = [0.0, 0.15];
                
                dingNotes.forEach((freq, i) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    
                    osc.type = 'sine'; // Suara bel murni
                    osc.frequency.setValueAtTime(freq, time + dingTimes[i]);
                    
                    gain.gain.setValueAtTime(0.12, time + dingTimes[i]);
                    gain.gain.exponentialRampToValueAtTime(0.002, time + dingTimes[i] + 0.3);
                    
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(time + dingTimes[i]);
                    osc.stop(time + dingTimes[i] + 0.35);
                });

                // B. NADA WAH-WAH SAD TROMBONE (Dimulai setelah Ding-Dong selesai)
                const tromboneNotes = [293.66, 277.18, 261.63, 220.00]; // Nada sumbang turun
                const tromboneDurations = [0.25, 0.25, 0.25, 0.75];
                let tromboneStart = time + 0.5; // Mulai di detik 0.5
                
                tromboneNotes.forEach((freq, i) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    
                    osc.type = 'triangle'; // Lebih lembut dari sawtooth agar tidak terlalu menusuk telinga
                    osc.frequency.setValueAtTime(freq, tromboneStart);
                    osc.frequency.exponentialRampToValueAtTime(freq * 0.88, tromboneStart + tromboneDurations[i]); // Efek perosotan (pitch bend)
                    
                    gain.gain.setValueAtTime(0.15, tromboneStart);
                    gain.gain.exponentialRampToValueAtTime(0.002, tromboneStart + tromboneDurations[i] - 0.05);
                    
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    
                    osc.start(tromboneStart);
                    osc.stop(tromboneStart + tromboneDurations[i]);
                    
                    tromboneStart += tromboneDurations[i];
                });
                
            } catch (e) {
                console.warn("[Audio] Web Audio API diblokir browser atau tidak didukung:", e);
            }
        }

        // =======================================================
        // 🌧️ HUJAN EMOJI PELAN (SLOW DRIFT) DAN BANYAK (350 PARTIKEL)
        // =======================================================
        function triggerSlowEmojiRain() {
            const emojis = ['🤭', '🤫', '🤓', '🙅‍♂️', '🔒', '🎈', '🌸', '✨', '🎈', '🧸'];
            const screenWidth = window.innerWidth;
            
            // Generate 350 emoji agar berjatuhan sangat banyak
            for (let i = 0; i < 350; i++) {
                const particle = document.createElement('div');
                particle.className = 'emoji-particle';
                particle.innerText = emojis[Math.floor(Math.random() * emojis.length)];
                
                // Penempatan posisi horizontal acak
                particle.style.left = Math.random() * screenWidth + 'px';
                particle.style.top = '-40px';
                
                // Hitung variabel acak untuk di-passing ke CSS keyframe
                const driftWidth = (Math.random() * 200 - 100) + 'px'; // Ayunan kiri-kanan -100px s/d 100px
                const spinDegree = (Math.random() * 720 - 360) + 'deg'; // Derajat putaran -360 s/d 360
                
                particle.style.setProperty('--drift-width', driftWidth);
                particle.style.setProperty('--spin-degree', spinDegree);
                
                // Durasi jatuhnya sangat lambat (8 s/d 16 detik)
                const duration = Math.random() * 8 + 8;
                // Penundaan acak berkisar 0 s/d 4 detik agar jatuh bergantian secara alami
                const delay = Math.random() * 4;
                
                particle.style.animation = `emoji-fall-slow ${duration}s ease-in-out ${delay}s forwards`;
                
                document.body.appendChild(particle);
                
                // Hapus elemen setelah selesai jatuh untuk mencegah lag DOM
                setTimeout(() => particle.remove(), (duration + delay) * 1000);
            }
        }
    </script>

</body>
</html>