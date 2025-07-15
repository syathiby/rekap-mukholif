<?php
include '../db.php';
include '../header.php';

// Fungsi buat deteksi jenis pelanggaran berdasarkan jam input
function deteksiKategoriPelanggaran($datetime) {
    $jam = date('H:i', strtotime($datetime));

    if ($jam >= '03.30' && $jam <= '05:30') return 'Sholat Subuh';
    elseif ($jam >= '07:30' && $jam <= '08:15') return 'KBM';
    elseif ($jam >= '11:30' && $jam <= '13:00') return 'Sholat Dzuhur';
    elseif ($jam >= '14:45' && $jam <= '16:00') return 'Sholat Ashar';
    elseif ($jam >= '17:45' && $jam <= '19:00') return 'Sholat Maghrib';
    elseif ($jam >= '19:00' && $jam <= '20:00') return 'Sholat Isya';
    else return 'Luar Jam Ketentuan';
}

$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? $tanggal_awal; // Default sama dengan tanggal_awal jika kosong
$kategori_filter = $_GET['kategori'] ?? '';
$sort_order = $_GET['sort'] ?? 'desc';
$rekap_harian = isset($_GET['rekap_harian']) ? true : false;
$order = ($sort_order === 'asc') ? 'ASC' : 'DESC';

$query = "
    SELECT s.nama, s.kamar, p.tanggal
    FROM pelanggaran p
    JOIN santri s ON s.id = p.santri_id
";

if (!empty($tanggal_awal)) {
    if ($rekap_harian) {
        // Filter untuk satu hari saja
        $query .= " WHERE DATE(p.tanggal) = '$tanggal_awal' ";
    } else {
        // Filter untuk rentang tanggal
        if (empty($tanggal_akhir)) {
            $tanggal_akhir = $tanggal_awal;
        }
        $query .= " WHERE DATE(p.tanggal) BETWEEN '$tanggal_awal' AND '$tanggal_akhir' ";
    }
}

$query .= " ORDER BY p.tanggal $order ";

$result = mysqli_query($conn, $query) or die("Query Error: " . mysqli_error($conn));

// Hitung total pelanggaran per kategori untuk chart
$chart_query = "
    SELECT  
        CASE 
            WHEN TIME(tanggal) BETWEEN '03:30:00' AND '05:30:00' THEN 'Sholat Subuh'
            WHEN TIME(tanggal) BETWEEN '07:30:00' AND '08:15:00' THEN 'KBM'
            WHEN TIME(tanggal) BETWEEN '11:30:00' AND '13:00:00' THEN 'Sholat Dzuhur'
            WHEN TIME(tanggal) BETWEEN '14:45:00' AND '16:00:00' THEN 'Sholat Ashar'
            WHEN TIME(tanggal) BETWEEN '17:45:00' AND '19:00:00' THEN 'Sholat Maghrib'
            WHEN TIME(tanggal) BETWEEN '19:00:00' AND '20:00:00' THEN 'Sholat Isya'
            ELSE 'Luar Jam Ketentuan'
        END AS kategori,
        COUNT(*) AS total
    FROM pelanggaran
";

if (!empty($tanggal_awal)) {
    if ($rekap_harian) {
        $chart_query .= " WHERE DATE(tanggal) = '$tanggal_awal' ";
    } else {
        $chart_query .= " WHERE DATE(tanggal) BETWEEN '$tanggal_awal' AND '$tanggal_akhir' ";
    }
}

$chart_query .= " GROUP BY kategori ORDER BY total DESC";
$chart_result = mysqli_query($conn, $chart_query);

$chart_labels = [];
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_labels[] = $row['kategori'];
    $chart_data[] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Kategori Pelanggaran</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #4fc3f7;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h2 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h2 i {
            color: var(--accent-color);
        }
        
        .filter-form {
            background-color: var(--light-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .filter-form input, 
        .filter-form select,
        .filter-form .checkbox-group {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background-color: white;
        }
        
        .filter-form .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
        }
        
        .filter-form input:focus, 
        .filter-form select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            outline: none;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        button i {
            font-size: 0.9em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }
        
        tr:hover td {
            background-color: rgba(79, 195, 247, 0.1);
        }
        
        .kategori-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            color: white;
        }
        
        .Sholat-Subuh { background-color: #3498db; }
        .KBM { background-color: #2ecc71; }
        .Sholat-Dzuhur { background-color: #f39c12; }
        .Sholat-Ashar { background-color: #e74c3c; }
        .Sholat-Maghrib { background-color: #9b59b6; }
        .Sholat-Isya { background-color: #1abc9c; }
        .Luar-Jam-Ketentuan { background-color: #95a5a6; }
        
        .dataTables_wrapper {
            margin-top: 20px;
        }
        
        .dataTables_filter input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 3px;
            border-radius: 5px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background-color: var(--primary-color);
            color: white !important;
            border-color: var(--primary-color);
        }
        
        .dataTables_paginate .paginate_button.current {
            background-color: var(--primary-color);
            color: white !important;
            border-color: var(--primary-color);
        }
        
        .empty-table {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        .empty-table i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .chart-container {
            margin-top: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .chart-title {
            color: var(--secondary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-title i {
            color: var(--accent-color);
        }
        
        .summary-card {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .summary-icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background-color: var(--primary-color);
        }
        
        .summary-content h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 18px;
        }
        
        .summary-content p {
            margin: 5px 0 0;
            color: var(--dark-color);
            font-weight: 700;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-clipboard-list"></i> Rekap Pelanggaran Santri per Kategori Waktu</h2>

        <form method="GET" class="filter-form">
            <div>
                <label><i class="fas fa-calendar-day"></i> Tanggal</label>
                <input type="date" name="tanggal_awal" value="<?= $tanggal_awal ?>" required>
            </div>
            <div id="tanggal-akhir-container" style="<?= $rekap_harian ? 'display: none;' : '' ?>">
                <label><i class="fas fa-calendar-day"></i> Sampai Tanggal</label>
                <input type="date" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
            </div>
            <div>
                <label><i class="fas fa-sort"></i> Urutkan</label>
                <select name="sort">
                    <option value="desc" <?= $sort_order == 'desc' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="asc" <?= $sort_order == 'asc' ? 'selected' : '' ?>>Terlama</option>
                </select>
            </div>
            <div>
                <label><i class="fas fa-filter"></i> Kategori Waktu</label>
                <select name="kategori">
                    <option value="">Semua</option>
                    <option value="Sholat Subuh" <?= $kategori_filter == 'Sholat Subuh' ? 'selected' : '' ?>>Sholat Subuh</option>
                    <option value="KBM" <?= $kategori_filter == 'KBM' ? 'selected' : '' ?>>KBM</option>
                    <option value="Sholat Dzuhur" <?= $kategori_filter == 'Sholat Dzuhur' ? 'selected' : '' ?>>Sholat Dzuhur</option>
                    <option value="Sholat Ashar" <?= $kategori_filter == 'Sholat Ashar' ? 'selected' : '' ?>>Sholat Ashar</option>
                    <option value="Sholat Maghrib" <?= $kategori_filter == 'Sholat Maghrib' ? 'selected' : '' ?>>Sholat Maghrib</option>
                    <option value="Sholat Isya" <?= $kategori_filter == 'Sholat Isya' ? 'selected' : '' ?>>Sholat Isya</option>
                    <option value="Luar Jam Ketentuan" <?= $kategori_filter == 'Luar Jam Ketentuan' ? 'selected' : '' ?>>Luar Jam Ketentuan</option>
                </select>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="rekap_harian" name="rekap_harian" <?= $rekap_harian ? 'checked' : '' ?>>
                <label for="rekap_harian">Rekap Harian</label>
            </div>
            <div>
                <button type="submit"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>

        <?php if (!empty($tanggal_awal)): ?>
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="summary-content">
                    <h3>Periode Rekap</h3>
                    <p>
                        <?= date('d M Y', strtotime($tanggal_awal)) ?>
                        <?php if (!$rekap_harian && !empty($tanggal_akhir) && $tanggal_awal != $tanggal_akhir): ?>
                            - <?= date('d M Y', strtotime($tanggal_akhir)) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (count($chart_data) > 0): ?>
                <div class="chart-container">
                    <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Distribusi Pelanggaran per Kategori</h3>
                    <canvas id="kategoriChart"></canvas>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <table id="rekapTable" class="display">
            <thead>
                <tr>
                    <th>No</th>
                    <th><i class="fas fa-user"></i> Nama Santri</th>
                    <th><i class="fas fa-bed"></i> Kamar</th>
                    <th><i class="fas fa-clock"></i> Waktu Input</th>
                    <th><i class="fas fa-tag"></i> Kategori Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kategori = deteksiKategoriPelanggaran($row['tanggal']);
                        if ($kategori_filter && $kategori_filter !== $kategori) continue;
                        
                        $kategori_class = str_replace(' ', '-', $kategori);
                        
                        echo "<tr>
                            <td>{$no}</td>
                            <td>{$row['nama']}</td>
                            <td><i class='fas fa-door-open'></i> Kamar {$row['kamar']}</td>
                            <td><i class='far fa-calendar-alt'></i> {$row['tanggal']}</td>
                            <td><span class='kategori-badge {$kategori_class}'><i class='fas fa-".getIconForCategory($kategori)."'></i> {$kategori}</span></td>
                        </tr>";
                        $no++;
                    }
                } else {
                    echo "<tr><td colspan='5' class='empty-table'>
                            <i class='far fa-folder-open'></i>
                            <div>Tidak ada data ditemukan</div>
                          </td></tr>";
                }
                
                function getIconForCategory($kategori) {
                    switch($kategori) {
                        case 'Sholat Subuh': return 'sun';
                        case 'KBM': return 'book';
                        case 'Sholat Dzuhur': return 'sun';
                        case 'Sholat Ashar': return 'sun';
                        case 'Sholat Maghrib': return 'moon';
                        case 'Sholat Isya': return 'moon';
                        default: return 'clock';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#rekapTable').DataTable({
                "language": {
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Data tidak ditemukan",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data tersedia",
                    "infoFiltered": "(disaring dari _MAX_ total data)",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "initComplete": function() {
                    $('.dataTables_wrapper').addClass('animated fadeIn');
                }
            });
            
            // Toggle tanggal akhir berdasarkan rekap harian
            $('#rekap_harian').change(function() {
                if ($(this).is(':checked')) {
                    $('#tanggal-akhir-container').hide();
                } else {
                    $('#tanggal-akhir-container').show();
                }
            });
            
            // Animasi saat hover di tombol
            $('button').hover(
                function() {
                    $(this).css('transform', 'translateY(-2px)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                }
            );
            
            <?php if (count($chart_data) > 0): ?>
                // Chart untuk distribusi kategori pelanggaran
                const ctx = document.getElementById('kategoriChart').getContext('2d');
                const chart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?= json_encode($chart_labels) ?>,
                        datasets: [{
                            data: <?= json_encode($chart_data) ?>,
                            backgroundColor: [
                                '#3498db', // Sholat Subuh
                                '#2ecc71', // KBM
                                '#f39c12', // Sholat Dzuhur
                                '#e74c3c', // Sholat Ashar
                                '#9b59b6', // Sholat Maghrib
                                '#1abc9c', // Sholat Isya
                                '#95a5a6'  // Luar Jam Ketentuan
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php include '../footer.php'; ?>
