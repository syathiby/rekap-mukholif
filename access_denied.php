<?php
// Kita butuh BASE_URL buat ngarahin ke halaman utama dengan benar
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Halaman Tidak Ditemukan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            background-color: #1e272e;
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-align: center;
        }

        .container {
            width: 100%;
            max-width: 480px;
            padding: 0 20px;
        }
        
        .card {
            background-color: #2c3a47;
            border-radius: 16px;
            padding: 1rem 2rem 2.5rem 2rem; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            /* Garis tepi dikasih warna aksen! */
            border: 1px solid rgba(79, 195, 247, 0.25);
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.3s ease; /* Transisi halus untuk hover */
        }
        
        .card:hover {
            box-shadow: 0 0 25px rgba(79, 195, 247, 0.2); /* Efek glow saat disentuh mouse */
        }

        #lottie-container {
            width: 100%;
            max-width: 300px;
            height: auto;
            margin-bottom: 1rem;
        }
        
        .main-text {
            animation: fadeIn 1s 0.5s ease-out forwards;
            opacity: 0;
        }

        .main-text h1 {
            font-size: clamp(2.2rem, 8vw, 3rem);
            margin: 0 0 0.75rem 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            line-height: 1.2;
            /* Ini dia warnanya! */
            color: #4fc3f7; 
            /* Dikasih bayangan biar sedikit 'glow' */
            text-shadow: 0 0 10px rgba(79, 195, 247, 0.3);
        }

        .main-text p {
            font-size: clamp(0.9rem, 4vw, 1rem);
            margin: 0;
            opacity: 0.8;
            line-height: 1.6;
            font-weight: 400;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        @media (max-width: 480px) {
            .card {
                padding: 1rem 1.5rem 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div id="lottie-container"></div>
            
            <div class="main-text">
                <h1>404 Not Found</h1>
                <p>Maaf, halaman yang Anda cari tidak dapat ditemukan. Anda akan dialihkan kembali secara otomatis.</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    <script>
        const animationContainer = document.getElementById('lottie-container');

        const anim = lottie.loadAnimation({
            container: animationContainer,
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: '<?= BASE_URL ?>/assets/animations/error-animation.json' // Pastikan path ini benar
        });

        // Diubah jadi 5 detik sesuai permintaan lu
        setTimeout(() => {
            window.location.href = '<?= BASE_URL ?>'; 
        }, 5000);
    </script>
</body>
</html>