<?php
// Pastikan BASE_URL udah didefinisikan di config.php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #6c757d;
            --background-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --text-primary: #212529;
        }
        
        html, body {
            height: 100%; /* Pastikan html dan body setinggi layar */
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--background-gradient);
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            overflow: hidden;
            /* KUNCI 1: Jadikan body sebagai flex container utama */
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        /* --- KONTEN UTAMA --- */
        .content-wrapper {
            width: 100%; /* Lebar penuh */
            padding: 1.5rem; /* Padding untuk jarak dari tepi layar, terutama HP */
            box-sizing: border-box; 
        }

        /* --- STYLING KOTAK KADO --- */
        .gift-container {
            /* KUNCI 2: Pusatkan kado di tengah wrapper */
            margin-left: auto;
            margin-right: auto;
            position: relative;
            width: 200px;
            height: 200px;
            cursor: pointer;
            transition: opacity 0.5s ease, transform 0.5s ease;
            animation: shake-fast 0.8s cubic-bezier(.36,.07,.19,.97) infinite;
        }
        .gift-lid { position: absolute; width: 200px; height: 60px; background: #ef7b87; border-radius: 8px 8px 0 0; top: 40px; left: 0; z-index: 3; }
        .gift-box { position: absolute; width: 180px; height: 120px; background: #e55d6b; border-radius: 8px; bottom: 0; left: 10px; z-index: 2; }
        .ribbon-vertical { position: absolute; width: 25px; height: 140px; background: #f1c40f; top: 40px; left: 87.5px; z-index: 4; border-radius: 5px; }
        .bow { position: absolute; width: 50px; height: 40px; background: #f39c12; border-radius: 50%; top: 20px; left: 75px; z-index: 5; }
        .bow::before, .bow::after { content: ''; position: absolute; width: 40px; height: 40px; background: #f1c40f; border-radius: 50%; top: -5px; }
        .bow::before { left: -20px; }
        .bow::after { right: -20px; }
        .click-instruction { margin-top: 15px; font-size: 0.9rem; color: #6c757d; animation: pulse 2s infinite; transition: opacity 0.3s ease, transform 0.3s ease; }

        /* --- PESAN ERROR --- */
        .error-message-container {
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) 0.2s;
            pointer-events: none;
            display: none; 
        }
        
        /* State setelah kado diklik */
        body.opened .gift-container,
        body.opened .click-instruction {
            transform: scale(0);
            opacity: 0;
            pointer-events: none;
            display: none; /* Sembunyikan total agar tidak makan tempat */
        }

        body.opened .error-message-container {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
            display: block; 
        }

        .error-icon { font-size: 4.5rem; margin-bottom: 1rem; animation: bounce 2s ease-in-out infinite; }
        .error-title { font-size: clamp(2rem, 8vw, 3.5rem); font-weight: 700; margin: 0; line-height: 1.2; }
        .error-message { font-size: clamp(1rem, 4vw, 1.25rem); color: var(--secondary-color); margin: 1rem auto 2rem auto; max-width: 600px; }
        .countdown-text { font-size: 1rem; color: var(--secondary-color); }
        #countdown { font-weight: 700; color: var(--primary-color); }

        /* Animasi */
        .confetti { position: absolute; width: 10px; height: 10px; background-color: #f1c40f; opacity: 0; }
        @keyframes confetti-fall { 0% { transform: translateY(-100px) rotate(0deg); opacity: 1; } 100% { transform: translateY(100vh) rotate(360deg); opacity: 0; } }
        @keyframes pulse { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; } }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-20px); } 60% { transform: translateY(-10px); } }
        @keyframes shake-fast { 0%, 100% { transform: translateX(0) rotate(0); } 20%, 60% { transform: translateX(-10px) rotate(-4deg); } 40%, 80% { transform: translateX(10px) rotate(4deg); } }
    </style>
</head>
<body>

    <div class="content-wrapper" id="contentWrapper">
        <div class="gift-container" id="giftBox">
            <div class="bow"></div>
            <div class="gift-lid"></div>
            <div class="ribbon-vertical"></div>
            <div class="gift-box"></div>
        </div>
        <div class="click-instruction">
            <i class="fas fa-mouse-pointer"></i> Klik kotak kado!
        </div>

        <div class="error-message-container">
            <div class="error-icon">🚧</div>
            <h1 class="error-title">Widih, jago bangeet...</h1>
            <p class="error-message">
                Tapi sayangnya anda tidak dapat melakukan aksi ini.
            </p>
            <p class="countdown-text">
                Otomatis logout dalam <span id="countdown">5</span> detik...
            </p>
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

            // Tambah class 'opened' ke body untuk memicu transisi CSS
            document.body.classList.add('opened');
            createConfetti();
            
            // Logika countdown
            let countdown = 5;
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = `${BASE_URL}/logout.php`;
                }
            }, 1000);
        });

        function createConfetti() {
            const colors = ['#e55d6b', '#f1c40f', '#3498db', '#2ecc71'];
            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                const duration = Math.random() * 3 + 2;
                confetti.style.animation = `confetti-fall ${duration}s linear forwards`;
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), duration * 1000);
            }
        }
    </script>

</body>
</html>