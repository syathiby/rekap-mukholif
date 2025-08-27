<?php
include '../db.php';
include '../header.php';

// 🔹 Ambil periode aktif
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row['nilai'] ?? null;

if (!$periode_aktif) {
    die("⚠️ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.");
}

// 🔹 Ambil semua kamar unik
$kamars = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri ORDER BY CAST(kamar AS UNSIGNED) ASC");

// 🔹 Ambil filter kamar
$filter_kamar = $_GET['kamar'] ?? null;

// 🔹 Ambil filter tanggal
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date   = $_GET['end_date'] ?? date("Y-m-d");

// ===== GANTI BAGIAN QUERY DENGAN BLOCK INI =====
// 🔹 Query santri pelanggar: pre-aggregate dari tabel pelanggaran
$s_date = mysqli_real_escape_string($conn, $start_date);
$e_date = mysqli_real_escape_string($conn, $end_date);
$filter_kamar_esc = $filter_kamar ? mysqli_real_escape_string($conn, $filter_kamar) : null;

$sql = "
SELECT s.id, s.nama, s.kelas, s.kamar,
       COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran,
       COALESCE(p.telat_sholat, 0) AS telat_sholat,
       COALESCE(p.telat_kbm, 0) AS telat_kbm
FROM santri s
LEFT JOIN (
    SELECT santri_id,
           COUNT(*) AS total_pelanggaran,
           SUM(CASE WHEN jenis_pelanggaran_id = 1 THEN 1 ELSE 0 END) AS telat_sholat,
           SUM(CASE WHEN jenis_pelanggaran_id = 2 THEN 1 ELSE 0 END) AS telat_kbm
    FROM pelanggaran
    WHERE DATE(tanggal) BETWEEN '$s_date' AND '$e_date'
    GROUP BY santri_id
) p ON s.id = p.santri_id
";

if ($filter_kamar_esc) {
    $sql .= " WHERE s.kamar = '" . $filter_kamar_esc . "'";
}

$sql .= " HAVING total_pelanggaran > 0 ORDER BY total_pelanggaran DESC, s.nama ASC";

$query = mysqli_query($conn, $sql);
if (!$query) die("❌ Query error: " . mysqli_error($conn));
// ===== END REPLACE =====

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pelanggaran Santri</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Palet Warna Merah yang Lebih Kalem */
            --danger: #B91C1C; /* Merah utama (gelap) */
            --danger-light: #FEE2E2; /* Latar belakang merah muda */
            --danger-dark: #991B1B; /* Merah untuk hover */
            --danger-text: #7F1D1D; /* Teks merah tua */

            /* Warna Pendukung */
            --success: #15803D;
            --success-light: #DCFCE7;
            --success-dark: #166534;
            
            /* Warna Netral */
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --light-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-primary);
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            color: var(--danger);
            margin-bottom: 1.5rem;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Card Style */
        .card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            border: 1px solid var(--border-color);
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .form-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .filter-form input[type="date"] {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .filter-form input[type="date"]:focus {
            outline: none;
            border-color: var(--danger);
            box-shadow: 0 0 0 2px var(--danger-light);
        }
        
        .btn {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background-color: var(--danger-dark);
        }
        
        .kamar-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .kamar-nav a {
            padding: 0.375rem 1rem;
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .kamar-nav a:hover {
            background: var(--danger-light);
            border-color: var(--danger);
            color: var(--danger-text);
        }
        
        .kamar-nav .active {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        table th, table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        table th {
            background-color: var(--light-bg);
            color: var(--danger-text);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tbody tr:hover {
            background-color: #FDF2F2; /* Sedikit merah muda saat dihover */
        }
        
        /* Peringkat Pelanggar Teratas */
        .rank-1 { background-color: rgba(185, 28, 28, 0.15); font-weight: 600; }
        .rank-2 { background-color: rgba(185, 28, 28, 0.1); }
        .rank-3 { background-color: rgba(185, 28, 28, 0.05); }

        .santri-info { display: flex; flex-direction: column; }
        .santri-info .nama { font-weight: 600; }
        .santri-info .sub-info { font-size: 0.875rem; color: var(--text-secondary); }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: 20px;
            text-align: center;
        }
        .badge-danger {
            background-color: var(--danger-light);
            color: var(--danger-text);
        }
        
        .detail-pelanggaran span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.875rem;
        }
        
        .btn-detail {
            /* Properti yang ditambahkan untuk merapikan ikon dan teks */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem; /* Memberi jarak antara ikon dan teks */

            /* Properti yang sudah ada (tidak perlu diubah) */
            background-color: var(--success-light);
            color: var(--success);
            padding: 0.375rem 0.875rem;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .btn-detail:hover {
            background-color: var(--success);
            color: white;
            border-color: var(--success-dark);
        }
        
        .no-data {
            text-align: center;
            padding: 4rem;
            color: var(--text-secondary);
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #D1D5DB;
        }
        
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .page-title { font-size: 1.5rem; }
            .filter-form { flex-direction: column; align-items: stretch; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-exclamation-triangle"></i>
            Laporan Pelanggaran Santri
        </h1>
        
        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-filter"></i>
                Filter Data
            </h2>
            
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label for="start_date">Dari Tanggal</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Sampai Tanggal</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <?php if ($filter_kamar): ?>
                    <input type="hidden" name="kamar" value="<?= htmlspecialchars($filter_kamar) ?>">
                <?php endif; ?>
                
                <button type="submit" class="btn"><i class="fas fa-search"></i> Terapkan</button>
            </form>
            
            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
            
            <div class="kamar-nav">
                <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
                <?php 
                mysqli_data_seek($kamars, 0);
                while ($k = mysqli_fetch_assoc($kamars)): ?>
                    <a href="?kamar=<?= urlencode($k['kamar']) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"
                       class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>">
                       <?= htmlspecialchars($k['kamar']) ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="card table-container">
            <table>
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama Santri</th>
                        <th>Total Pelanggaran</th>
                        <th>Detail Pelanggaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if (mysqli_num_rows($query) === 0) {
                        echo '<tr>
                                <td colspan="5" class="no-data">
                                    <i class="fas fa-check-circle"></i>
                                    <p><b>Hebat!</b><br>Tidak ada pelanggaran pada periode dan filter yang dipilih.</p>
                                </td>
                              </tr>';
                    } else {
                        while ($row = mysqli_fetch_assoc($query)) {
                            $rank_class = "";
                            if ($no == 1) $rank_class = "rank-1";
                            elseif ($no == 2) $rank_class = "rank-2";
                            elseif ($no == 3) $rank_class = "rank-3";

                            echo "<tr class='{$rank_class}'>
                                    <td>{$no}</td>
                                    <td>
                                        <div class='santri-info'>
                                            <span class='nama'>{$row['nama']}</span>
                                            <span class='sub-info'>Kelas: {$row['kelas']} | Kamar: {$row['kamar']}</span>
                                        </div>
                                    </td>
                                    <td><span class='badge badge-danger'>{$row['total_pelanggaran']}</span></td>
                                    <td class='detail-pelanggaran'>
                                        <span style='color:#C2410C;'><i class='fas fa-pray'></i> Telat Sholat: {$row['telat_sholat']}</span>
                                        <br>
                                        <span style='color:#1D4ED8;'><i class='fas fa-book'></i> Telat KBM: {$row['telat_kbm']}</span>
                                    </td>
                                    <td><a href='detail-pelanggaran.php?id={$row['id']}' class='btn-detail'><i class='fas fa-info-circle'></i> Detail</a></td>
                                  </tr>";
                            $no++;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Script tetap sama, tidak perlu diubah.
    </script>
</body>
</html>

<?php include '../footer.php'; ?>