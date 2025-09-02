<?php 
require_once __DIR__ . '/../header.php';
guard('rekap_view_per_kamar'); 
?>

<?php

// Ambil periode aktif dari pengaturan
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row ? $row['nilai'] : '2000-01-01';

// Ambil data filter dari form
$tanggal_awal = $_GET['tanggal_awal'] ?? $periode_aktif;
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date("Y-m-d");
$sort_order = $_GET['sort'] ?? 'desc'; // default: terbanyak

// =======================================================
// === PERUBAHAN UTAMA: QUERY DIUBAH TOTAL ===
// =======================================================
// Tujuannya: Mengambil SEMUA kamar dari tabel santri, lalu LEFT JOIN data pelanggaran.
// Ini memastikan kamar dengan 0 pelanggaran tetap muncul.
$sql = "
    SELECT 
        k.kamar,
        COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran
    FROM 
        (SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '') k
    LEFT JOIN 
        (SELECT kamar, COUNT(*) AS total_pelanggaran
         FROM pelanggaran_kebersihan
         WHERE DATE(tanggal) BETWEEN ? AND ?
         GROUP BY kamar
        ) p ON k.kamar = p.kamar
";

// Tentukan urutan
$order = ($sort_order === 'asc') ? 'ASC' : 'DESC';
$sql .= " ORDER BY total_pelanggaran $order, CAST(k.kamar AS UNSIGNED) ASC";

// Menggunakan Prepared Statement agar lebih aman
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) die("Query Error: " . $stmt->error);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pelanggaran Kebersihan per Kamar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --danger: #f72585;
            --warning: #f8961e;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            /* Warna untuk piala */
            --gold: #f59e0b;
            --silver: #9ca3af;
            --bronze: #a16207;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
        }
        
        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }
        
        .header-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 5px solid var(--primary);
        }
        
        h2 {
            margin: 0;
            color: var(--primary);
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .filter-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-size: 14px;
            margin-bottom: 8px;
            color: #6c757d;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        input[type="date"], select {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            min-width: 200px;
        }
        
        input[type="date"]:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        button {
            padding: 12px 24px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        button:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .table-wrapper {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        table {
            width: 100% !important;
            border-collapse: collapse;
        }
        
        table thead th {
            background-color: var(--light) !important;
            color: var(--dark) !important;
            font-weight: 600;
            padding: 16px 12px;
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
        }
        
        table tbody tr {
            transition: all 0.2s ease;
        }
        
        table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05) !important;
        }
        
        table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .pelanggaran-count {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .pelanggaran-count.banyak {
             background-color: #ffebee;
             color: #c62828;
        }

        .pelanggaran-count.nol {
             background-color: #e8f5e9;
             color: #2e7d32;
        }
        
        /* === CSS Untuk Ikon Piala === */
        .rank-icon {
            font-size: 1.8rem;
        }
        .rank-1 .rank-icon { color: var(--gold); }
        .rank-2 .rank-icon { color: var(--silver); }
        .rank-3 .rank-icon { color: var(--bronze); }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <h2><i class="fas fa-broom"></i> Rekap Kebersihan per Kamar</h2>
        </div>

        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label><i class="far fa-calendar-alt"></i> Dari Tanggal</label>
                    <input type="date" name="tanggal_awal" value="<?= htmlspecialchars($tanggal_awal) ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="far fa-calendar-alt"></i> Sampai Tanggal</label>
                    <input type="date" name="tanggal_akhir" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort-amount-down"></i> Urutkan</label>
                    <select name="sort">
                        <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Terbanyak</option>
                        <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>Tersedikit</option>
                    </select>
                </div>
                
                <button type="submit"><i class="fas fa-filter"></i> Terapkan Filter</button>
            </form>
        </div>

    <div class="table-wrapper">
        <table id="rekapTable">
            <thead>
                <tr>
                    <th style="width: 10%;">Peringkat</th>
                    <th>Kamar</th>
                    <th>Jumlah Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Tentukan class untuk styling (misal: piala hanya untuk urutan terbanyak)
                        $rank_class = ($sort_order == 'desc' && $no <=3) ? "rank-$no" : "";
                        echo "<tr class='$rank_class'>";

                        // === PENAMBAHAN LOGIKA PIALA ===
                        echo "<td class='text-center'>";
                        if ($sort_order == 'desc' && $no <= 3) {
                             echo "<i class='fas fa-trophy rank-icon'></i>";
                        } else {
                             echo $no;
                        }
                        echo "</td>";
                        
                        echo "<td>Kamar " . htmlspecialchars($row['kamar']) . "</td>";
                        
                        // Tentukan class untuk badge poin
                        $count_class = $row['total_pelanggaran'] > 0 ? 'banyak' : 'nol';
                        echo "<td><span class='pelanggaran-count $count_class'>{$row['total_pelanggaran']}</span></td>";

                        echo "</tr>";
                        $no++;
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center p-4'>Tidak ada data ditemukan</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <!-- Hapus DataTables karena kita sudah sorting via PHP -->
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>