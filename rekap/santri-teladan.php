<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_santri');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php'; 

// 🔹 Ambil periode aktif
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row_periode = mysqli_fetch_assoc($q_periode);
$periode_aktif = $row_periode['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container' style='padding-top:20px;'><div class='alert alert-danger'>⚠ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.</div></div>");
}

// 🔹 Ambil data unik untuk filter dropdown
$kamars_query = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri ORDER BY CAST(kamar AS UNSIGNED) ASC");
$kelas_query = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri ORDER BY kelas ASC");

// 🔹 Ambil filter dari URL (jika ada)
$filter_kamar = $_GET['kamar'] ?? ''; 
$filter_kelas = $_GET['kelas'] ?? ''; 

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

if (!empty($filter_kamar)) {
    $sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}

if (!empty($filter_kelas)) {
    $sql .= " AND s.kelas = ?";
    $params[] = $filter_kelas;
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
    background-color: #f8f9fa;
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

/* === BAGIAN FILTER BARU DENGAN DROPDOWN === */
.filter-form {
    margin: 25px 0;
    padding: 20px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 15px;
}

.filter-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    flex-grow: 1; 
}

.filter-form select {
    padding: 10px 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    background-color: #f8f9fa;
    min-width: 180px;
    transition: border-color 0.3s ease;
    cursor: pointer; /* ✨ Tambahan kecil biar keliatan bisa diklik */
}

.filter-form select:focus {
    outline: none;
    border-color: #2980b9;
}

.filter-form .reset-link {
    font-size: 13px;
    color: #7f8c8d;
    text-decoration: none;
    margin-left: auto; /* ✨ Pindahin link reset ke kanan */
    padding: 10px;
}
.filter-form .reset-link:hover {
    text-decoration: underline;
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
    border-left: 5px solid #27ae60;
    opacity: 0;
    transform: translateY(20px);
    animation: cardFadeIn 0.5s ease-out forwards;
}

.santri-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.card-rank {
    position: absolute;
    top: -10px;
    left: -10px;
    height: 45px;
    min-width: 45px;
    padding: 0 8px;
    box-sizing: border-box;
    background: #27ae60;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    border-radius: 25px;
    box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
    border: 3px solid white;
}

.card-content h3 {
    margin: 10px 0 5px 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    padding-left: 20px;
}

.card-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    color: #7f8c8d;
    font-size: 14px;
    padding-left: 20px;
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
    grid-column: 1 / -1;
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

    <form action="" method="GET" class="filter-form">
        <div class="filter-controls">
            <select name="kamar" onchange="this.form.submit()">
                <option value="">Semua Kamar</option>
                <?php while ($k = mysqli_fetch_assoc($kamars_query)): ?>
                    <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>
                        Kamar <?= htmlspecialchars($k['kamar']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="kelas" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php while ($kl = mysqli_fetch_assoc($kelas_query)): ?>
                    <option value="<?= htmlspecialchars($kl['kelas']) ?>" <?= ($filter_kelas == $kl['kelas']) ? 'selected' : '' ?>>
                        Kelas <?= htmlspecialchars($kl['kelas']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <a href="?" class="reset-link">Reset Filter</a>
    </form>


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
                $animation_delay = $no * 0.07; 
        ?>
                <div class="santri-card" style="animation-delay: <?= $animation_delay ?>s;">
                    <div class="card-rank"><?= $no ?></div>
                    <div class="card-content">
                        <h3><?= htmlspecialchars($row['nama']) ?></h3>
                        <div class="card-info">
                            <div class="info-item">
                                <span class="icon">🏫</span> <span>Kelas: <?= htmlspecialchars($row['kelas']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="icon">🚪</span> <span>Kamar: <?= htmlspecialchars($row['kamar']) ?></span>
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