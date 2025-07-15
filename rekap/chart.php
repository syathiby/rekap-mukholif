<?php
include '../db.php';
include '../header.php';

// 1. Rekap Harian (7 hari terakhir)
$q_harian = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) AS total 
    FROM pelanggaran 
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal) 
    ORDER BY tanggal ASC
");
$label_harian = [];
$data_harian = [];
while ($row = mysqli_fetch_assoc($q_harian)) {
    $label_harian[] = date('d M', strtotime($row['tanggal']));
    $data_harian[] = $row['total'];
}

// 2. Rekap per Kamar (termasuk kamar terbersih dan terkotor)
$q_kamar = mysqli_query($conn, "
    SELECT p.kamar, COUNT(*) AS total 
    FROM pelanggaran_kebersihan p 
    GROUP BY p.kamar 
    ORDER BY total DESC
    LIMIT 10
");
$label_kamar = [];
$data_kamar = [];
while ($row = mysqli_fetch_assoc($q_kamar)) {
    $label_kamar[] = "Kamar " . $row['kamar'];
    $data_kamar[] = $row['total'];
}

// 3. Top 10 Santri Terbanyak Pelanggaran
$q_santri = mysqli_query($conn, "
    SELECT s.id, s.nama, COUNT(*) AS total 
    FROM pelanggaran p 
    JOIN santri s ON s.id = p.santri_id 
    GROUP BY s.id 
    ORDER BY total DESC 
    LIMIT 10
");
$label_santri = [];
$data_santri = [];
$santri_ids = [];
$total_top_santri = 0;
while ($row = mysqli_fetch_assoc($q_santri)) {
    $label_santri[] = $row['nama'];
    $data_santri[] = $row['total'];
    $santri_ids[] = $row['id'];
    $total_top_santri += $row['total'];
}

// 4. Rekap per Kelas
$q_kelas = mysqli_query($conn, "
    SELECT s.kelas, COUNT(*) AS total 
    FROM pelanggaran p 
    JOIN santri s ON s.id = p.santri_id 
    GROUP BY s.kelas
    ORDER BY total DESC
");
$label_kelas = [];
$data_kelas = [];
while ($row = mysqli_fetch_assoc($q_kelas)) {
    $label_kelas[] = "Kelas " . $row['kelas'];
    $data_kelas[] = $row['total'];
}

// 5. Rekap Jenis Pelanggaran
$q_jenis = mysqli_query($conn, "
    SELECT j.nama_pelanggaran, COUNT(*) as total
    FROM pelanggaran p
    JOIN jenis_pelanggaran j ON p.jenis_pelanggaran_id = j.id
    GROUP BY j.id
    ORDER BY total DESC
    LIMIT 5
");
$label_jenis = [];
$data_jenis = [];
while ($row = mysqli_fetch_assoc($q_jenis)) {
    $label_jenis[] = $row['nama_pelanggaran'];
    $data_jenis[] = $row['total'];
}

// 6. Rekap Jam Pelanggaran
$q_jam = mysqli_query($conn, "
    SELECT HOUR(tanggal) as jam, COUNT(*) as total
    FROM pelanggaran
    GROUP BY HOUR(tanggal)
    ORDER BY jam ASC
");
$label_jam = [];
$data_jam = [];
while ($row = mysqli_fetch_assoc($q_jam)) {
    $label_jam[] = $row['jam'] . ':00';
    $data_jam[] = $row['total'];
}

// Hitung total statistik
$total_pelanggaran = array_sum($data_harian);
$total_hari = count($label_harian);
$total_kamar = count($label_kamar);
$total_kelas = count($label_kelas);
$total_jenis = count($label_jenis);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Statistik Pelanggaran Lengkap</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --danger: #f72585;
            --warning: #f8961e;
            --success: #4cc9f0;
            --info: #7209b7;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
        }
        .container {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .dashboard-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            animation: fadeInDown 0.6s ease-out;
        }
        h1 {
            margin: 0;
            color: var(--primary);
            font-size: 28px;
            font-weight: 600;
            width: 100%;
            margin-bottom: 15px;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            width: 100%;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.8s ease-out;
            border-left: 4px solid var(--primary);
        }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.info { border-left-color: var(--info); }
        .stat-icon {
            font-size: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .stat-icon.calendar { background: var(--danger); }
        .stat-icon.bed { background: var(--info); }
        .stat-icon.user { background: var(--primary); }
        .stat-icon.bullhorn { background: var(--warning); }
        .stat-icon.school { background: var(--secondary); }
        .stat-icon.clock { background: var(--success); }
        .stat-icon.list { background: var(--accent); }
        .stat-value {
            font-weight: 600;
            font-size: 18px;
            color: var(--dark);
            line-height: 1.2;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            line-height: 1.2;
        }
        h2 {
            margin: 30px 0 15px;
            font-size: 20px;
            font-weight: 500;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        canvas {
            width: 100% !important;
            height: 300px !important;
        }
        .chart-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            background: #f1f3ff;
            color: var(--primary);
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
            .stats-summary {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="dashboard-header">
        <h1><i class="fas fa-chart-line"></i> Dashboard Statistik Pelanggaran</h1>
        <div class="stats-summary">
            <div class="stat-card danger">
                <div class="stat-icon calendar">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $total_pelanggaran ?></div>
                    <div class="stat-label">Total Pelanggaran</div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon bullhorn">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $total_hari ?></div>
                    <div class="stat-label">Hari dengan Pelanggaran</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon user">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?= count($label_santri) ?></div>
                    <div class="stat-label">Santri dengan Pelanggaran</div>
                </div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon school">
                    <i class="fas fa-school"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $total_kelas ?></div>
                    <div class="stat-label">Total Kelas</div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon bed">
                    <i class="fas fa-bed"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $total_kamar ?></div>
                    <div class="stat-label">Kamar Terdata</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon list">
                    <i class="fas fa-list"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $total_jenis ?></div>
                    <div class="stat-label">Jenis Pelanggaran</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="chart-grid">
        <div class="chart-container">
            <h2><i class="fas fa-chart-line"></i> Pelanggaran 7 Hari Terakhir</h2>
            <div class="filter-section">
                <button class="filter-btn active" data-range="7">7 Hari</button>
                <button class="filter-btn" data-range="30">30 Hari</button>
                <button class="filter-btn" data-range="90">3 Bulan</button>
                <button class="filter-btn" data-range="365">1 Tahun</button>
            </div>
            <canvas id="harianChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h2><i class="fas fa-home"></i> 10 Kamar Terbanyak Pelanggaran</h2>
            <canvas id="kamarChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h2><i class="fas fa-user-graduate"></i> Top 10 Santri Terbanyak Pelanggaran</h2>
            <canvas id="santriChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h2><i class="fas fa-chalkboard-teacher"></i> Distribusi Pelanggaran per Kelas</h2>
            <canvas id="kelasChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h2><i class="fas fa-list"></i> Jenis Pelanggaran Terbanyak</h2>
            <canvas id="jenisChart"></canvas>
        </div>
        
        <div class="chart-container">
            <h2><i class="fas fa-clock"></i> Pelanggaran per Jam</h2>
            <canvas id="jamChart"></canvas>
        </div>
    </div>
</div>

<script>
    // Register plugin
    Chart.register(ChartDataLabels);
    
    // Generate colors
    const generateColors = (count, opacity = 0.7) => {
        const colors = [];
        const hueStep = 360 / count;
        for (let i = 0; i < count; i++) {
            colors.push(`hsla(${i * hueStep}, 70%, 60%, ${opacity})`);
        }
        return colors;
    }

    // Chart configuration
    const chartConfig = (id, labels, data, type = 'bar', label = 'Jumlah Pelanggaran') => {
        const ctx = document.getElementById(id).getContext('2d');
        const bgColors = generateColors(labels.length);
        
        return new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: type === 'pie' || type === 'doughnut' ? bgColors : bgColors[0],
                    borderColor: type === 'pie' || type === 'doughnut' ? 
                        bgColors.map(c => c.replace('0.7', '1')) : 
                        bgColors[0].replace('0.7', '1'),
                    borderWidth: 1,
                    hoverBackgroundColor: type === 'pie' || type === 'doughnut' ? 
                        bgColors.map(c => c.replace('60%', '70%')) : 
                        bgColors[0].replace('60%', '70%'),
                    hoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: type === 'pie' || type === 'doughnut',
                        position: 'right'
                    },
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 },
                        padding: 12
                    },
                    datalabels: {
                        display: type === 'pie' || type === 'doughnut',
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value > 0 ? value : '';
                        }
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeOutQuart'
                    }
                },
                scales: (type === 'pie' || type === 'doughnut') ? {} : {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    // Initialize charts
    let harianChart = chartConfig('harianChart', <?= json_encode($label_harian) ?>, <?= json_encode($data_harian) ?>, 'line');
    let kamarChart = chartConfig('kamarChart', <?= json_encode($label_kamar) ?>, <?= json_encode($data_kamar) ?>, 'bar');
    let santriChart = new Chart(document.getElementById('santriChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($label_santri) ?>,
        datasets: [{
            label: 'Jumlah Pelanggaran',
            data: <?= json_encode($data_santri) ?>,
            backgroundColor: generateColors(<?= count($label_santri) ?>),
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 10
            },
            datalabels: {
                anchor: 'center',
                align: 'center',
                color: '#fff',
                formatter: (value) => value > 0 ? value : ''
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            },
            y: {
                grid: {
                    display: false
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
    let kelasChart = chartConfig('kelasChart', <?= json_encode($label_kelas) ?>, <?= json_encode($data_kelas) ?>, 'doughnut');
    let jenisChart = chartConfig('jenisChart', <?= json_encode($label_jenis) ?>, <?= json_encode($data_jenis) ?>, 'pie');
    let jamChart = chartConfig('jamChart', <?= json_encode($label_jam) ?>, <?= json_encode($data_jam) ?>, 'bar');
    
    // Filter functionality
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const days = parseInt(this.dataset.range);
            
            // AJAX request to get filtered data
            fetch(`get_harian_data.php?days=${days}`)
                .then(response => response.json())
                .then(data => {
                    harianChart.data.labels = data.labels;
                    harianChart.data.datasets[0].data = data.data;
                    harianChart.update();
                })
                .catch(error => console.error('Error:', error));
        });
    });
</script>
</body>
</html>
<?php include '../footer.php'; ?>