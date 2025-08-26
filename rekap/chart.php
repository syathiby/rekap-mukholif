<?php
include '../db.php';
include '../header.php';



// Ambil periode aktif
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row ? $row['nilai'] : date('Y-m-d');

// 1. Total Pelanggaran (gabungan pelanggaran + kebersihan)
$q_total1 = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM pelanggaran 
    WHERE tanggal >= '$periode_aktif'
");

$q_total2 = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM pelanggaran_kebersihan 
    WHERE tanggal >= '$periode_aktif'
");

$total1 = mysqli_fetch_assoc($q_total1)['total'];
$total2 = mysqli_fetch_assoc($q_total2)['total'];
$total_pelanggaran = $total1 + $total2;

// 2. Pelanggaran 7 hari terakhir (gabungan)
$q_harian = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM (
        SELECT tanggal FROM pelanggaran 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT tanggal FROM pelanggaran_kebersihan 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ) AS combined 
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");

// 2a. Pelanggaran 1 Bulan Terakhir (gabungan)
$q_bulanan = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM (
        SELECT tanggal FROM pelanggaran 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        UNION ALL
        SELECT tanggal FROM pelanggaran_kebersihan 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ) AS combined 
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");

// 2b. Pelanggaran 3 Bulan Terakhir (gabungan)
$q_triwulan = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM (
        SELECT tanggal FROM pelanggaran 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        UNION ALL
        SELECT tanggal FROM pelanggaran_kebersihan 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ) AS combined 
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");

// 2c. Pelanggaran 1 Tahun Terakhir (gabungan)
$q_tahunan = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM (
        SELECT tanggal FROM pelanggaran 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        UNION ALL
        SELECT tanggal FROM pelanggaran_kebersihan 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ) AS combined 
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");


// 3. Top 5 Santri (hanya dari tabel pelanggaran)
$q_santri = mysqli_query($conn, "
    SELECT s.nama, COUNT(*) AS total 
    FROM pelanggaran p 
    JOIN santri s ON s.id = p.santri_id 
    WHERE p.tanggal >= '$periode_aktif'
    GROUP BY s.id 
    ORDER BY total DESC 
    LIMIT 5
");

// 4. Per Kelas (hanya dari tabel pelanggaran)
$q_kelas = mysqli_query($conn, "
    SELECT s.kelas, COUNT(*) AS total 
    FROM pelanggaran p 
    JOIN santri s ON s.id = p.santri_id 
    WHERE p.tanggal >= '$periode_aktif'
    GROUP BY s.kelas
    ORDER BY total DESC
");

// 5. Per Kamar (dari tabel pelanggaran_kebersihan)
$q_kamar = mysqli_query($conn, "
    SELECT kamar, COUNT(*) AS total 
    FROM pelanggaran_kebersihan 
    WHERE tanggal >= '$periode_aktif'
    GROUP BY kamar 
    ORDER BY total DESC
");

// 6. Jenis Pelanggaran
$q_jenis = mysqli_query($conn, "
    SELECT j.nama_pelanggaran, COUNT(*) as total
    FROM pelanggaran p
    JOIN jenis_pelanggaran j ON p.jenis_pelanggaran_id = j.id
    WHERE p.tanggal >= '$periode_aktif'
    GROUP BY j.id

    UNION ALL

    SELECT 'KEBERSIHAN KAMAR' as nama_pelanggaran, COUNT(*) as total
    FROM pelanggaran_kebersihan pk
    WHERE pk.tanggal >= '$periode_aktif'
    
    ORDER BY total DESC
");

// Function biar gak nulis ulang fetch data chart
function fetchChartData($query) {
    $data = [];
    while($row = mysqli_fetch_assoc($query)) {
        $data[] = [
            'tanggal' => $row['tanggal'],
            'total' => $row['total']
        ];
    }
    return $data;
}

// Simpan data ke variabel
$data_7hari   = fetchChartData($q_harian);
$data_bulan   = fetchChartData($q_bulanan);
$data_3bulan  = fetchChartData($q_triwulan);
$data_tahun   = fetchChartData($q_tahunan);


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pelanggaran - Singkat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4285F4; /* Biru Google */
            --secondary: #34A853; /* Hijau Google */
            --accent: #EA4335; /* Merah Google */
            --highlight: #FBBC05; /* Kuning Google */
            --neutral: #5F6368; /* Abu-abu Google */
            --light: #F8F9FA;
            --dark: #202124;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(to bottom, #f5f7fa 0%, #e8eaed 100%);
            color: #333;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary) 0%, #1a73e8 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        
        header::after {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }
        
        h2 {
            font-size: 28px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        h3 {
            font-size: 22px;
            margin: 25px 0 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--primary);
            color: var(--dark);
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid var(--primary);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            text-align: center;
            border-top: 5px solid var(--primary);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .stat-card:nth-child(2) {
            border-top-color: var(--accent);
        }
        
        .stat-card:nth-child(3) {
            border-top-color: var(--secondary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-card i {
            position: absolute;
            top: -15px;
            right: -15px;
            font-size: 80px;
            opacity: 0.1;
            color: var(--primary);
        }
        
        .stat-card:nth-child(2) i {
            color: var(--accent);
        }
        
        .stat-card:nth-child(3) i {
            color: var(--secondary);
        }
        
        .stat-card h4 {
            font-size: 18px;
            color: var(--neutral);
            margin-bottom: 15px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-card:nth-child(2) .number {
            color: var(--accent);
        }
        
        .stat-card:nth-child(3) .number {
            color: var(--secondary);
        }
        
        .chart-wrapper {
            position: relative;
            margin-bottom: 30px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            min-width: 600px;
        }
        
        select {
            padding: 12px 20px;
            border-radius: 50px;
            border: 2px solid var(--primary);
            margin-bottom: 20px;
            background-color: white;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
            outline: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        select:focus {
            border-color: var(--accent);
            box-shadow: 0 5px 15px rgba(234, 67, 53, 0.2);
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: linear-gradient(135deg, var(--primary) 0%, #1a73e8 100%);
            color: white;
            font-weight: 600;
            font-size: 16px;
            position: sticky;
            top: 0;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f1f3f9;
        }
        
        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            color: white;
        }
        
        .badge-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #1a73e8 100%);
        }
        
        .badge-success {
            background: linear-gradient(135deg, var(--secondary) 0%, #2da44e 100%);
        }
        
        .badge-warning {
            background: linear-gradient(135deg, var(--highlight) 0%, #e6a700 100%);
        }
        
        .badge-danger {
            background: linear-gradient(135deg, var(--accent) 0%, #d93025 100%);
        }
        
        .badge-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .period-info {
            background: linear-gradient(135deg, #ffeaa7 0%, #FFD93D 100%);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: inline-flex;
            align-items: center;
            font-weight: bold;
            color: var(--dark);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .period-info i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .stats {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            h3 {
                font-size: 20px;
            }
            
            .stat-card .number {
                font-size: 30px;
            }
            
            th, td {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            .chart-container {
                height: 300px;
                min-width: 100%;
            }
            
            select {
                max-width: 100%;
            }
            
            .card {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            header {
                padding: 20px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            h3 {
                font-size: 18px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card .number {
                font-size: 26px;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .badge {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary) 0%, #1a73e8 100%);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1a73e8 0%, var(--primary) 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h2>REKAP PELANGGARAN</h2>
            <div class="period-info">
                <i class="fas fa-calendar-alt"></i>
                <span>Periode Aktif: <?= $periode_aktif ?></span>
            </div>
        </header>
        
        <div class="stats">
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Total Pelanggaran</h4>
                <div class="number"><?= $total_pelanggaran ?></div>
                <p>Pelanggaran</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-times"></i>
                <h4>Pelanggaran Umum</h4>
                <div class="number"><?= $total1 ?></div>
                <p>Santri Melanggar</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-broom"></i>
                <h4>Pelanggaran Kebersihan</h4>
                <div class="number"><?= $total2 ?></div>
                <p>Pelanggaran</p>
            </div>
        </div>
        
        <div class="card">
            <h3>Grafik Pelanggaran</h3>
            <select id="filterRange">
                <option value="7">7 Hari Terakhir</option>
                <option value="30">1 Bulan Terakhir</option>
                <option value="90">3 Bulan Terakhir</option>
                <option value="365">1 Tahun Terakhir</option>
            </select>
            
            <div class="chart-wrapper">
                <div class="chart-container">
                    <canvas id="chartPelanggaran"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>Top 5 Santri</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Santri</th>
                            <th>Jumlah Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($q_santri)): ?>
                        <tr>
                            <td><i class="fas fa-user-graduate"></i> <?= $row['nama'] ?></td>
                            <td><span class="badge badge-danger"><?= $row['total'] ?> pelanggaran</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3>Per Kelas</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Jumlah Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($q_kelas)): ?>
                        <tr>
                            <td><i class="fas fa-chalkboard"></i> <?= $row['kelas'] ?></td>
                            <td><span class="badge badge-warning"><?= $row['total'] ?> pelanggaran</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3>Per Kamar (Kebersihan)</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kamar</th>
                            <th>Jumlah Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($q_kamar)): ?>
                        <tr>
                            <td><i class="fas fa-bed"></i> Kamar <?= $row['kamar'] ?></td>
                            <td><span class="badge badge-primary"><?= $row['total'] ?> pelanggaran</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3>Jenis Pelanggaran</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Jenis Pelanggaran</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($q_jenis)): ?>
                        <tr>
                            <td><i class="fas fa-exclamation-circle"></i> <?= $row['nama_pelanggaran'] ?></td>
                            <td><span class="badge badge-success"><?= $row['total'] ?> kali</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const data7Hari  = <?= json_encode($data_7hari) ?>;
        const dataBulan  = <?= json_encode($data_bulan) ?>;
        const data3Bulan = <?= json_encode($data_3bulan) ?>;
        const dataTahun  = <?= json_encode($data_tahun) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const ctx = document.getElementById('chartPelanggaran').getContext('2d');

        function formatChartData(rawData) {
            return {
                labels: rawData.map(d => {
                    const date = new Date(d.tanggal);
                    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                }),
                datasets: [{
                    label: "Jumlah Pelanggaran",
                    data: rawData.map(d => d.total),
                    borderColor: "#4285F4",
                    backgroundColor: "rgba(66, 133, 244, 0.1)",
                    pointBackgroundColor: "#4285F4",
                    pointBorderColor: "#fff",
                    pointHoverBackgroundColor: "#fff",
                    pointHoverBorderColor: "#4285F4",
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHitRadius: 12,
                    fill: true,
                    tension: 0.4
                }]
            };
        }

        let chart = new Chart(ctx, {
            type: 'line',
            data: formatChartData(data7Hari),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                family: "'Roboto', sans-serif"
                            },
                            color: '#2d3436'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(45, 52, 54, 0.9)',
                        titleFont: { 
                            size: 15,
                            family: "'Roboto', sans-serif"
                        },
                        bodyFont: { 
                            size: 14,
                            family: "'Roboto', sans-serif"
                        },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                family: "'Roboto', sans-serif"
                            }
                        },
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: "'Roboto', sans-serif"
                            }
                        }
                    }
                }
            }
        });

        document.getElementById('filterRange').addEventListener('change', function() {
            let selected = this.value;
            let newData;
            if (selected == "7") newData = data7Hari;
            else if (selected == "30") newData = dataBulan;
            else if (selected == "90") newData = data3Bulan;
            else if (selected == "365") newData = dataTahun;

            chart.data = formatChartData(newData);
            chart.update();
        });
    </script>

</body>
</html>

<?php include '../footer.php'; ?>