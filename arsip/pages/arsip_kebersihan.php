<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view');

require_once __DIR__ . '/../../layouts/header.php'; 

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id <= 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Arsip tidak valid.</div></div>");
}

$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
if (!$arsip) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Arsip tidak ditemukan.</div></div>");
}

$sort_order = $_GET['sort'] ?? 'desc'; // default: terbanyak

// Query
$sql = "
    SELECT 
        k.kamar,
        COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran
    FROM 
        (SELECT DISTINCT santri_kamar AS kamar FROM arsip_data_santri WHERE arsip_id = ? AND santri_kamar IS NOT NULL AND santri_kamar != '') k
    LEFT JOIN 
        (SELECT kamar, COUNT(*) AS total_pelanggaran
         FROM arsip_data_pelanggaran_kebersihan
         WHERE arsip_id = ?
         GROUP BY kamar
        ) p ON k.kamar = p.kamar
";

// Tentukan urutan
$order = ($sort_order === 'asc') ? 'ASC' : 'DESC';
$sql .= " ORDER BY total_pelanggaran $order, CAST(k.kamar AS UNSIGNED) ASC";

// Prepared Statement
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $arsip_id, $arsip_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) die("Query Error: " . $stmt->error);
?>

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
    <div class="container">
        <div class="header-card d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-broom"></i> Kebersihan Kamar (Arsip)</h2>
                <p class="text-muted mt-2 mb-0">Arsip: <strong><?= htmlspecialchars($arsip['judul']) ?></strong> (<?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?>)</p>
            </div>
            <div>
                <a href="../view.php?id=<?= $arsip_id ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 9999px; font-weight: 600; font-size: 14px; transition: all 0.2s ease; white-space: nowrap;" onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#e2e8f0';">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <div class="filter-card" style="padding: 1.5rem; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <form method="GET" action="" class="filter-form m-0" id="filterForm">
                <input type="hidden" name="id" value="<?= $arsip_id ?>">
                
                <div class="d-flex align-items-center gap-3 w-100">
                    <label class="fw-semibold text-secondary mb-0" style="white-space: nowrap;"><i class="fas fa-sort-amount-down me-2"></i>Urutkan Berdasarkan:</label>
                    <select name="sort" class="form-select form-select-sm fw-medium border-0 bg-light" style="max-width: 200px; cursor: pointer;" onchange="document.getElementById('loadingOverlay').style.display='flex'; this.form.submit()">
                        <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Paling Banyak Melanggar</option>
                        <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>Paling Sedikit Melanggar</option>
                    </select>
                </div>
            </form>
        </div>

        <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 fw-semibold text-primary">Memuat Data...</p>
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
                        $link_detail = "detail_arsip_kebersihan.php?id=" . $arsip_id . "&kamar=" . urlencode($row['kamar']);
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
    </div> <!-- .table-wrapper -->
</div> <!-- .container -->

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
