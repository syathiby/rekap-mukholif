<?php
include '../db.php';
include '../header.php';

// 🔹 Ambil periode aktif
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row['nilai'] ?? null;

if (!$periode_aktif) {
    die("⚠ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.");
}

// 🔹 Ambil semua kamar unik
$kamars = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri ORDER BY CAST(kamar AS UNSIGNED) ASC");

// 🔹 Ambil filter kamar (kalau ada)
$filter_kamar = $_GET['kamar'] ?? null;

// 🔹 Query santri teladan
$sql = "
    SELECT s.id, s.nama, s.kelas, s.kamar
    FROM santri s
    LEFT JOIN pelanggaran p 
        ON s.id = p.santri_id 
        AND p.tanggal >= '$periode_aktif'
    WHERE p.santri_id IS NULL
";
if ($filter_kamar) {
    $sql .= " AND s.kamar = '" . mysqli_real_escape_string($conn, $filter_kamar) . "'";
}
$sql .= " ORDER BY CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC";

$query = mysqli_query($conn, $sql);
?>


<style>
.kamar-nav {
    margin: 15px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.kamar-nav a {
    padding: 6px 12px;
    background: #e0f2f1;
    border-radius: 6px;
    text-decoration: none;
    color: #00695c;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.2s;
}

.kamar-nav a:hover {
    background: #80cbc4;
    color: white;
}

.kamar-nav .active {
    background: #00695c;
    color: white;
}

/* Styling tabel hijau kalem + animasi fade-in (tanpa per-baris) */
.table-container {
    overflow-x: auto;
    margin-top: 20px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    animation: fadeIn 0.8s ease-in-out;
}

table {
    border-collapse: collapse;
    width: 100%;
    min-width: 600px;
}

table th {
    background: #2e7d32;
    color: white;
    padding: 10px;
    text-align: left;
}

table td {
    padding: 8px 10px;
    border-bottom: 1px solid #ddd;
}

table tr:hover {
    background: #e8f5e9;
}

h2 {
    margin-top: 20px;
    color: #1b5e20;
    font-size: 22px;
    font-weight: bold;
    animation: fadeIn 0.8s ease-in-out;
}

/* Animasi */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<h2>📋 Daftar Santri Teladan</h2>
<p style="color:#388e3c; font-size:14px; margin-bottom:10px;">
    Periode aktif: <b><?= htmlspecialchars($periode_aktif) ?></b>
</p>

<!-- Navigasi per kamar -->
<div class="kamar-nav">
    <a href="?">Semua</a>
    <?php while ($k = mysqli_fetch_assoc($kamars)): ?>
        <a href="?kamar=<?= urlencode($k['kamar']) ?>" 
           class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>">
           Kamar <?= htmlspecialchars($k['kamar']) ?>
        </a>
    <?php endwhile; ?>
</div>


<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Santri</th>
                <th>Kelas</th>
                <th>Kamar</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if (mysqli_num_rows($query) === 0) {
                echo "<tr><td colspan='4' style='text-align:center; padding:12px; color:#777;'>🎉 Tidak ada santri teladan di periode ini</td></tr>";
            } else {
                while ($row = mysqli_fetch_assoc($query)) {
                    echo "<tr>
                            <td>{$no}</td>
                            <td>{$row['nama']}</td>
                            <td>{$row['kelas']}</td>
                            <td>{$row['kamar']}</td>
                          </tr>";
                    $no++;
                }
            }
            ?>
        </tbody>
    </table>
</div>

<?php include '../footer.php'; ?>