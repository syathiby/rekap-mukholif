<?php
// Pastikan session sudah aktif sebelum file ini dipanggil.
if (session_status() === PHP_SESSION_NONE) {
    // Ini cuma buat jaga-jaga, harusnya session udah start di header.
    session_start();
}

/**
 * =================================================================
 * FUNGSI SATPAM SAKTI v3: has_permission()
 * =================================================================
 */
if (!function_exists('has_permission')) {
    function has_permission($permission) {
        // Aturan Emas: Admin selalu punya akses ke semuanya.
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }
        if (is_string($permission)) {
            return in_array($permission, $_SESSION['permissions']);
        }
        if (is_array($permission)) {
            foreach ($permission as $p) {
                if (in_array($p, $_SESSION['permissions'])) {
                    return true;
                }
            }
        }
        return false;
    }
}


/**
 * =================================================================
 * PROTOKOL PENJAGAAN BARU: guard()
 * =================================================================
 */
function guard($permission = null) {
    // Peraturan #1: Belum login? Tendang ke halaman login.
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }

    // Peraturan #2: Kalau ada tiket yang harus dicek...
    if ($permission !== null) {
        // ...tapi ternyata user gak punya tiketnya...
        if (!has_permission($permission)) {
            // ✅ FIX THE REAL FINAL: Balik ke metode inject, tapi suntik viewport pake JS.
            http_response_code(403);
            echo "
            <!-- CSS Links: Mungkin tidak perlu jika sudah ada di header utama website Anda. -->
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
            <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap' rel='stylesheet'>

            <style>
                /* Styling ini nargetin div yang disisipin ke konten utama */
                .access-denied-container {
                    width: 100%;
                    min-height: 80vh; 
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Poppins', sans-serif;
                    padding: 1rem;
                    box-sizing: border-box;
                }
                .access-denied-card {
                    background-color: #ffffff;
                    padding: 2.5rem 3rem;
                    border-radius: 1rem;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                    border: none;
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                    animation: fadeInZoom 0.6s ease-out forwards;
                }
                
                @keyframes fadeInZoom {
                    from { opacity: 0; transform: scale(0.95); }
                    to { opacity: 1; transform: scale(1); }
                }

                .access-denied-card .icon {
                    font-size: 4rem; color: #dc3545; margin-bottom: 1.5rem;
                }
                .access-denied-card h1 {
                    font-size: 2rem; font-weight: 600; color: #343a40; margin-bottom: 0.75rem;
                }
                .access-denied-card p {
                    font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;
                }
                .access-denied-card .btn {
                    padding: 0.75rem 1.5rem; font-size: 1rem;
                }

                /* Jurus responsive untuk tampilan HP */
                @media (max-width: 576px) {
                    .access-denied-card { padding: 2rem 1.5rem; }
                    .access-denied-card .icon { font-size: 3.5rem; margin-bottom: 1rem; }
                    .access-denied-card h1 { font-size: 1.75rem; }
                    .access-denied-card p { font-size: 1rem; margin-bottom: 1.5rem; }
                }
            </style>

            <div class='access-denied-container'>
                <div class='access-denied-card'>
                    <div class='icon'><i class='fas fa-ban'></i></div>
                    <h1>Akses Ditolak</h1>
                    <p>Maaf, Anda tidak memiliki izin ('tiket') untuk mengakses halaman ini.</p>
                    <a href='/index.php' class='btn btn-primary'><i class='fas fa-home me-2'></i>Kembali ke Dashboard</a>
                </div>
            </div>
            
            <!-- ================================================================== -->
            <!-- JURUS SAKTI: Cek & Suntik meta viewport jika belum ada -->
            <!-- ================================================================== -->
            <script>
                (function() {
                    if (!document.querySelector('meta[name=\"viewport\"]')) {
                        var meta = document.createElement('meta');
                        meta.name = 'viewport';
                        meta.content = 'width=device-width, initial-scale=1.0';
                        document.getElementsByTagName('head')[0].appendChild(meta);
                    }
                })();
            </script>
            ";
            exit; // Wajib: Hentikan eksekusi skrip setelah menampilkan halaman error.
        }
    }

    // Kalau lolos semua, berarti aman. Lanjutkan!
}
?>