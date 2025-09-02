<?php 
require_once __DIR__ . '/../header.php';
guard('rekap_view_santri'); 

// 🔹 Ambil periode aktif
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row_periode = mysqli_fetch_assoc($q_periode);
$periode_aktif = $row_periode['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container' style='padding-top:20px;'><div class='alert alert-danger'>⚠ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.</div></div>");
}

// 🔹 Ambil semua kamar unik untuk navigasi filter
$kamars_query = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri ORDER BY CAST(kamar AS UNSIGNED) ASC");

// 🔹 Ambil filter kamar dari URL (jika ada)
$filter_kamar = $_GET['kamar'] ?? null;

// 🔹 Query utama untuk mencari santri teladan
$sql = "
    SELECT s.id, s.nama, s.kelas, s.kamar
    FROM santri s
    LEFT JOIN pelanggaran p 
        ON s.id = p.santri_id 
        AND p.tanggal >= ?
    WHERE p.santri_id IS NULL
";

$params = [$periode_aktif];
$types = "s";

if ($filter_kamar) {
    $sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}

$sql .= " ORDER BY CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC";

// Menggunakan prepared statement untuk keamanan
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>

<style>
/* --- Font Keren dari Google --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

/* --- Reset & Body Styling --- */
body {
    background-color: #f8f9fa; /* Warna latar belakang yang soft */
    font-family: 'Poppins', sans-serif;
    color: #333;
}

/* --- Container Utama --- */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 15px;
    animation: fadeIn 0.5s ease-out;
}

/* --- Header Halaman --- */
.page-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.page-header h2 {
    color: #2c3e50;
    font-weight: 700;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header h2::before {
    content: '🏆'; /* Emoji piala */
    font-size: 26px;
}

.page-header .subtitle {
    color: #7f8c8d;
    font-size: 16px;
    margin-top: -5px;
}

.page-header .periode-info {
    font-size: 14px;
    color: #34495e;
    background-color: #ecf0f1;
    padding: 5px 12px;
    border-radius: 15px;
    display: inline-block;
    margin-top: 10px;
}
.page-header .periode-info b {
    color: #2980b9;
}

/* --- Navigasi Filter Kamar --- */
.kamar-nav {
    margin: 25px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.kamar-nav a {
    padding: 8px 16px;
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    text-decoration: none;
    color: #495057;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.kamar-nav a:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #2980b9;
    color: #2980b9;
}

.kamar-nav a.active {
    background: #2980b9;
    color: white;
    border-color: #2980b9;
    font-weight: 600;
}

/* --- Grid Kartu Santri --- */
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.santri-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-left: 5px solid #27ae60; /* Aksen hijau teladan */
    opacity: 0; /* Mulai dari transparan untuk animasi */
    transform: translateY(20px);
    animation: cardFadeIn 0.5s ease-out forwards;
}

.santri-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

/* === PERBAIKAN DI SINI === */
.card-rank {
    position: absolute;
    top: -10px;
    left: -10px;
    height: 45px; /* Tetapkan tinggi */
    min-width: 45px; /* Ganti width menjadi min-width */
    padding: 0 8px; /* Tambahkan padding horizontal */
    box-sizing: border-box; /* Pastikan padding tidak merusak ukuran */
    background: #27ae60;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    border-radius: 25px; /* Radius besar agar tetap membulat */
    box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
    border: 3px solid white;
}
/* === AKHIR PERBAIKAN === */


.card-content h3 {
    margin: 10px 0 5px 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    padding-left: 20px; /* Beri sedikit ruang dari rank number */
}

.card-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    color: #7f8c8d;
    font-size: 14px;
    padding-left: 20px; /* Beri sedikit ruang dari rank number */
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-item .icon {
    color: #2980b9;
    font-size: 16px;
}

/* --- Pesan Kosong --- */
.no-data {
    grid-column: 1 / -1; /* Span full width di grid */
    text-align: center;
    padding: 40px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.no-data .icon {
    font-size: 48px;
    color: #27ae60;
    margin-bottom: 10px;
}
.no-data p {
    font-size: 18px;
    color: #555;
    font-weight: 500;
}

/* --- Animasi --- */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes cardFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="container">
    <div class="page-header">
        <h2>Daftar Santri Teladan</h2>
        <p class="subtitle">Daftar santri yang tidak pernah melakukan pelanggaran.</p>
        <div class="periode-info">
            Periode Aktif: <b><?= htmlspecialchars($periode_aktif) ?></b>
        </div>
    </div>

    <!-- Navigasi per kamar -->
    <div class="kamar-nav">
        <a href="?" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
        <?php while ($k = mysqli_fetch_assoc($kamars_query)): ?>
            <a href="?kamar=<?= urlencode($k['kamar']) ?>" 
               class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>">
                Kamar <?= htmlspecialchars($k['kamar']) ?>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Grid Kartu Santri Teladan -->
    <div class="card-grid">
        <?php
        if (mysqli_num_rows($result) === 0) {
            echo "
            <div class='no-data'>
                <div class='icon'>🎉</div>
                <p>Belum ada santri teladan untuk filter ini.</p>
            </div>";
        } else {
            $no = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                // Menghitung delay animasi agar muncul satu per satu
                $animation_delay = $no * 0.07; 
        ?>
                <div class="santri-card" style="animation-delay: <?= $animation_delay ?>s;">
                    <div class="card-rank"><?= $no ?></div>
                    <div class="card-content">
                        <h3><?= htmlspecialchars($row['nama']) ?></h3>
                        <div class="card-info">
                            <div class="info-item">
                                <span class="icon">🏫</span> <!-- Emoji sekolah -->
                                <span>Kelas: <?= htmlspecialchars($row['kelas']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="icon">🚪</span> <!-- Emoji pintu -->
                                <span>Kamar: <?= htmlspecialchars($row['kamar']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
        <?php
                $no++;
            }
        }
        mysqli_stmt_close($stmt);
        ?>
    </div>

</div>

<?php require_once __DIR__ . '/../footer.php'; ?>