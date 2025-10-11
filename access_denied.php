<?php
// Kita butuh BASE_URL buat ngarahin ke logout.php dan aset dengan benar
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            background-color: #1e272e; /* Warna background dari video */
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            max-width: 100%;
        }

        /* Ini kontainer buat nampung animasinya */
        #lottie-container {
            width: 90%;
            max-width: 450px; /* Atur ukuran maksimal animasi di layar besar */
        }
        
        .main-text {
            animation: fadeIn 1s 0.5s ease-out forwards;
            opacity: 0;
            margin-top: -2rem; /* Tarik teks sedikit ke atas biar lebih pas */
        }

        .main-text h1 {
            font-size: clamp(2rem, 6vw, 3rem); /* Ukuran font responsif */
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .main-text p {
            font-size: clamp(0.9rem, 3vw, 1.2rem); /* Ukuran font responsif */
            margin-top: 0.5rem;
            opacity: 0.8;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Kontainer buat animasi Lottie -->
        <div id="lottie-container"></div>
        
        <div class="main-text">
            <h1>Oops! Akses Ditolak</h1>
            <p>Sesi Anda akan diakhiri secara otomatis...</p>
        </div>
    </div>

    <!-- Panggil library Lottie dari CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

    <script>
        // Inisialisasi Lottie
        const animationContainer = document.getElementById('lottie-container');

        const anim = lottie.loadAnimation({
            container: animationContainer, // wadah animasinya
            renderer: 'svg', // pake SVG biar tajem
            loop: true, // ulang terus animasinya
            autoplay: true, // langsung mainin
            path: '<?= BASE_URL ?>/assets/animations/error-animation.json' // Path ke file JSON lu
        });

        // Setelah 10 detik, tendang user keluar!
        setTimeout(() => {
            window.location.href = '<?= BASE_URL ?>/logout.php';
        }, 10000); // 10000 milidetik = 10 detik
    </script>

</body>
</html>