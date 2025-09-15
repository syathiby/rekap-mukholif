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
 * Ini adalah satu-satunya fungsi pengecek izin yang akan kita pakai
 * di seluruh aplikasi, baik di sidebar maupun di guard.
 */
if (!function_exists('has_permission')) {
    function has_permission($permission) {
        // Aturan Emas: Admin selalu punya akses ke semuanya.
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }

        // Kalau kantong tiketnya kosong, ya udah pasti gak punya izin.
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }

        // Jika kita cuma ngecek satu tiket (string)
        if (is_string($permission)) {
            return in_array($permission, $_SESSION['permissions']);
        }

        // Jika kita ngecek beberapa tiket (array), cukup salah satu ada, langsung lolos.
        if (is_array($permission)) {
            foreach ($permission as $p) {
                if (in_array($p, $_SESSION['permissions'])) {
                    return true; // Ditemukan satu tiket yang cocok, langsung kasih izin!
                }
            }
        }

        // Kalau semua cara gagal, berarti emang gak punya izin.
        return false;
    }
}


/**
 * =================================================================
 * PROTOKOL PENJAGAAN BARU: guard()
 * =================================================================
 * Versi baru yang lebih fleksibel dan menggunakan has_permission().
 *
 * @param string|array|null $permission
 * - null (kosong): Cuma ngecek udah login atau belum. Cocok buat dashboard.
 * - string ('nama_tiket'): Ngecek satu tiket spesifik.
 * - array (['tiket_a', 'tiket_b']): Ngecek beberapa tiket, salah satu aja cukup.
 */
function guard($permission = null) {
    // Peraturan #1: Belum login? Tendang ke halaman login.
    if (!isset($_SESSION['user_id'])) {
        // Jangan tampilkan pesan error di sini, langsung redirect aja.
        header("Location: rekap-mukholif/login.php");
        exit;
    }

    // Peraturan #2: Kalau ada tiket yang harus dicek...
    if ($permission !== null) {
        // ...tapi ternyata user gak punya tiketnya...
        if (!has_permission($permission)) {
            // ✅ FIX: Tampilkan card di tengah area konten yang ada.
            http_response_code(403);
            echo "
            <!-- CSS Links: Mungkin tidak perlu jika sudah ada di header utama website Anda. -->
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
            <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap' rel='stylesheet'>

            <style>
                /* Wrapper ini akan hidup di dalam area konten utama Anda.
                  Tugasnya adalah mengisi ruang vertikal dan menengahkan card.
                */
                .access-denied-container {
                    width: 100%;
                    /* Ambil minimal 80% tinggi viewport, biar bisa center vertikal */
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
                    padding: 2.5rem 3rem; /* Sedikit lebih lega */
                    border-radius: 1rem;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                    border: none;
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                    /* Animasi subtle biar munculnya smooth */
                    animation: fadeInZoom 0.6s ease-out forwards;
                }
                
                @keyframes fadeInZoom {
                    from { 
                        opacity: 0; 
                        transform: scale(0.95); 
                    }
                    to { 
                        opacity: 1; 
                        transform: scale(1); 
                    }
                }

                .access-denied-card .icon {
                    font-size: 4rem;
                    color: #dc3545;
                    margin-bottom: 1.5rem; /* Jarak icon ke judul */
                }
                .access-denied-card h1 {
                    font-size: 2rem;
                    font-weight: 600;
                    color: #343a40;
                    margin-bottom: 0.75rem; /* Jarak judul ke paragraf */
                }
                .access-denied-card p {
                    font-size: 1.1rem;
                    color: #6c757d;
                    margin-bottom: 2rem; /* Jarak paragraf ke tombol */
                }
                .access-denied-card .btn {
                    padding: 0.75rem 1.5rem;
                    font-size: 1rem;
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
            ";
            exit; // Wajib: Hentikan eksekusi skrip setelah menampilkan halaman error.
        }
    }

    // Kalau lolos semua, berarti aman. Lanjutkan!
}
?>