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

// Query
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

// Prepared Statement
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
            margin: 0; /* <<< Perbaikan kecil */
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
            flex-grow: 1; /* <<< Perbaikan kecil */
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
            width: 100%; /* <<< Perbaikan kecil */
            box-sizing: border-box; /* <<< Perbaikan kecil */
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
            /* === INI KUNCI UTAMANYA === */
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead th {
            background-color: var(--light);
            color: var(--dark);
            font-weight: 600;
            padding: 16px 12px;
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            /* === TAMBAHAN BIAR RAPI PAS DI-SCROLL === */
            white-space: nowrap;
        }
        
        table tbody tr {
            transition: all 0.2s ease;
        }
        
        table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
             /* === TAMBAHAN BIAR RAPI PAS DI-SCROLL === */
            white-space: nowrap;
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
        
        .rank-icon {
            font-size: 1.8rem;
        }
        .rank-1 .rank-icon { color: var(--gold); }
        .rank-2 .rank-icon { color: var(--silver); }
        .rank-3 .rank-icon { color: var(--bronze); }
        
        /* === STYLE BARU UNTUK TOMBOL DETAIL (SESUAI GAMBAR) === */
        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            background-color: #eef2ff; /* Warna lavender muda */
            color: var(--secondary); /* Warna ungu tua untuk teks & ikon */
            border-radius: 9999px; /* Bikin jadi bentuk pil */
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .btn-detail:hover {
            background-color: #e0e7ff; /* Sedikit lebih gelap pas di-hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(63, 55, 201, 0.15);
        }
        /* === /STYLE BARU === */
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* === BAGIAN RESPONSIVE UNTUK MOBILE === */
        @media (max-width: 768px) {
            .container {
                padding: 15px; /* Padding dikecilin biar gak mepet */
            }

            h2 {
                font-size: 22px; /* Ukuran judul dikecilin */
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .header-card, .filter-card, .table-wrapper {
                padding: 15px; /* Padding di dalem card dikecilin */
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
        <table>
             <thead>
                <tr>
                    <th style="width: 10%;">Peringkat</th>
                    <th>Kamar</th>
                    <th>Jumlah Pelanggaran</th>
                    <!-- PENAMBAHAN KOLOM AKSI -->
                    <th style="width: 15%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rank_class = ($sort_order == 'desc' && $no <=3) ? "rank-$no" : "";
                        echo "<tr class='$rank_class'>";

                        echo "<td class='text-center'>";
                        if ($sort_order == 'desc' && $no <= 3) {
                                echo "<i class='fas fa-trophy rank-icon'></i>";
                        } else {
                                echo $no;
                        }
                        echo "</td>";
                        
                        echo "<td>Kamar " . htmlspecialchars($row['kamar']) . "</td>";
                        
                        $count_class = $row['total_pelanggaran'] > 0 ? 'banyak' : 'nol';
                        echo "<td><span class='pelanggaran-count $count_class'>{$row['total_pelanggaran']}</span></td>";

                        // <!-- **INI BAGIAN PENTINGNYA** Menambahkan parameter tanggal ke link -->
                        $link_detail = "detail-kamar.php?kamar=" . urlencode($row['kamar']) . "&tanggal_awal=" . urlencode($tanggal_awal) . "&tanggal_akhir=" . urlencode($tanggal_akhir);
                        echo "<td><a href='" . $link_detail . "' class='btn-detail'><i class='fas fa-info-circle'></i> Detail</a></td>";
                        
                        echo "</tr>";
                        $no++;
                    }
                } else {
                    // <!-- Colspan diubah jadi 4 -->
                    echo "<tr><td colspan='4' class='text-center p-4'>Tidak ada data ditemukan</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>