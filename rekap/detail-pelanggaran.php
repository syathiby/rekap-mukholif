<?php
include '../db.php';
include '../header.php';

$santri_id = $_GET['id'] ?? null;
if (!$santri_id) {
    die("❌ Santri tidak ditemukan");
}

// 🔹 Ambil data santri
$q_santri = mysqli_query($conn, "SELECT * FROM santri WHERE id = " . intval($santri_id));
$santri = mysqli_fetch_assoc($q_santri);
if (!$santri) {
    die("❌ Data santri tidak ada");
}

// 🔹 Ambil histori pelanggaran santri
$q_pelanggaran = mysqli_query($conn, "
    SELECT p.tanggal, j.nama_pelanggaran AS pelanggaran
    FROM pelanggaran p
    INNER JOIN jenis_pelanggaran j ON p.jenis_pelanggaran_id = j.id
    WHERE p.santri_id = $santri_id
    ORDER BY p.tanggal DESC
");

if (!$q_pelanggaran) {
    die("Query gagal: " . mysqli_error($conn));
}

// Fungsi buat deteksi jenis pelanggaran berdasarkan jam input
function deteksiKategoriPelanggaran($datetime) {
    $jam = date('H:i', strtotime($datetime));

    if ($jam >= '03:30' && $jam <= '05:30') return 'Sholat Subuh';
    elseif ($jam >= '07:30' && $jam <= '08:15') return 'KBM';
    elseif ($jam >= '11:30' && $jam <= '13:00') return 'Sholat Dzuhur';
    elseif ($jam >= '14:45' && $jam <= '16:00') return 'Sholat Ashar';
    elseif ($jam >= '17:30' && $jam <= '19:00') return 'Sholat Maghrib';
    elseif ($jam >= '19:00' && $jam <= '20:00') return 'Sholat Isya';
    else return 'Luar Jam Ketentuan';
}
?>

<style>
.detail-box {
    background: #fff3e0;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.table-container {
    overflow-x: auto;
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
    background: #ef6c00;
    color: white;
    padding: 10px;
    text-align: left;
}

table td {
    padding: 8px 10px;
    border-bottom: 1px solid #ddd;
}

tr.highlight-red { background: #ffebee; }     /* Pelanggaran berat */
tr.highlight-orange { background: #fff3e0; } /* Sedang */
tr.highlight-green { background: #e8f5e9; }  /* Ringan */

h2 {
    margin-top: 20px;
    color: #e65100;
    font-size: 22px;
    font-weight: bold;
    animation: fadeIn 0.8s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.filter-container {
    margin-bottom: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
}

.filter-container label {
    margin-right: 10px;
    font-weight: bold;
}

.filter-container select {
    padding: 5px 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
}
</style>

<h2>📄 Detail Pelanggaran Santri</h2>

<div class="detail-box">
    <p><b>Nama:</b> <?= htmlspecialchars($santri['nama']) ?></p>
    <p><b>Kelas:</b> <?= htmlspecialchars($santri['kelas']) ?></p>
    <p><b>Kamar:</b> <?= htmlspecialchars($santri['kamar']) ?></p>
</div>

<!-- Filter berdasarkan waktu -->
<div class="filter-container">
    <label for="filter-waktu">Filter Waktu:</label>
    <select id="filter-waktu" onchange="filterByTime()">
        <option value="semua">Semua Waktu</option>
        <option value="Sholat Subuh">Sholat Subuh (03:30-05:30)</option>
        <option value="KBM">KBM (07:30-08:15)</option>
        <option value="Sholat Dzuhur">Sholat Dzuhur (11:30-13:00)</option>
        <option value="Sholat Ashar">Sholat Ashar (14:45-16:00)</option>
        <option value="Sholat Maghrib">Sholat Maghrib (17:30-19:00)</option>
        <option value="Sholat Isya">Sholat Isya (19:00-20:00)</option>
        <option value="Luar Jam Ketentuan">Luar Jam Ketentuan</option>
    </select>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Deskripsi Pelanggaran</th>
                <th>Waktu Input</th>
            </tr>
        </thead>
        <tbody id="pelanggaran-tbody">
            <?php
            $no = 1;
            $all_pelanggaran = [];
            if (mysqli_num_rows($q_pelanggaran) === 0) {
                echo "<tr><td colspan='4' style='text-align:center; padding:12px; color:#777;'>✅ Belum ada pelanggaran tercatat</td></tr>";
            } else {
                while ($row = mysqli_fetch_assoc($q_pelanggaran)) {
                    $all_pelanggaran[] = $row;
                    $kelas_row = "highlight-green"; // default ringan
                    if (stripos($row['pelanggaran'], 'berat') !== false) {
                        $kelas_row = "highlight-red";
                    } elseif (stripos($row['pelanggaran'], 'sedang') !== false) {
                        $kelas_row = "highlight-orange";
                    }
                    
                    $kategori_waktu = deteksiKategoriPelanggaran($row['tanggal']);
                    
                    echo "<tr class='{$kelas_row}' data-waktu='{$kategori_waktu}'>
                            <td>{$no}</td>
                            <td>" . htmlspecialchars($row['tanggal']) . "</td>
                            <td>" . htmlspecialchars($row['pelanggaran']) . "</td>
                            <td>{$kategori_waktu}</td>
                        </tr>";
                    $no++;
                }
            }
            ?>
        </tbody>
    </table>
</div>

<p style="margin-top:20px;">
    <a href='santri-pelanggar.php' style="color:#e65100; font-weight:bold;">⬅ Kembali ke Daftar Pelanggaran</a>
</p>

<script>
function filterByTime() {
    const filterValue = document.getElementById('filter-waktu').value;
    const rows = document.querySelectorAll('#pelanggaran-tbody tr');
    
    rows.forEach(row => {
        if (filterValue === 'semua') {
            row.style.display = '';
        } else {
            const waktu = row.getAttribute('data-waktu');
            if (waktu === filterValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}
</script>

<?php include '../footer.php'; ?>