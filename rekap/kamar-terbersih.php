<?php
include '../db.php';
include '../header.php';

// Ambil filter tanggal (optional)
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

// Query: 3 kamar terbersih + jumlah pelanggaran kebersihannya
$query = "
    SELECT s.kamar, COUNT(pk.id) AS jumlah_pelanggaran
    FROM (
        SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != ''
    ) s
    LEFT JOIN pelanggaran_kebersihan pk
        ON pk.kamar = s.kamar
";

// Filter tanggal jika ada
if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
    $query .= " AND DATE(pk.tanggal) BETWEEN '$tanggal_awal' AND '$tanggal_akhir' ";
}

$query .= " GROUP BY s.kamar ORDER BY jumlah_pelanggaran ASC LIMIT 3"; // Ambil 3 kamar terbersih

$result = mysqli_query($conn, $query) or die("Query Error: " . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Top 3 Kamar Terbersih</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --success: #4cc9f0;
            --clean: #2ecc71;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
        }
        
        .container {
            padding: 30px 20px 60px; /* dari: padding: 30px */
            max-width: 800px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }
        
        .header-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 5px solid var(--clean);
            text-align: center;
        }
        
        h2 {
            margin: 0;
            color: var(--clean);
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
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
            gap: 25px;
            align-items: flex-end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
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
        
        input[type="date"] {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            min-width: 180px;
        }
        
        input[type="date"]:focus {
            outline: none;
            border-color: var(--clean);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
        }
        
        button {
            padding: 12px 24px;
            background-color: var(--clean);
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
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        #terbersihTable {
            width: 100% !important;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        #terbersihTable thead th {
            background-color: var(--clean) !important;
            color: white !important;
            font-weight: 500;
            padding: 16px 12px;
            text-align: center;
        }
        
        #terbersihTable tbody tr {
            transition: all 0.2s ease;
            text-align: center;
        }
        
        #terbersihTable tbody tr:hover {
            background-color: rgba(46, 204, 113, 0.05) !important;
        }
        
        #terbersihTable tbody td {
            padding: 18px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
        }
        
        .medal {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 8px;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            color: white;
        }
        
        .gold {
            background: linear-gradient(135deg, #FFD700 0%, #D4AF37 100%);
        }
        
        .silver {
            background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);
        }
        
        .bronze {
            background: linear-gradient(135deg, #CD7F32 0%, #A97142 100%);
        }
        
        .clean-count {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            background-color: #e8f5e9;
            color: var(--clean);
        }
        
        .perfect-clean {
            background-color: #d4edda;
            font-weight: bold;
        }
        
        .perfect-clean .clean-count {
            background-color: #c3e6cb;
            color: #155724;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes rowEntrance {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            input[type="date"] {
                width: 100%;
            }
        }

        .header-card {
            margin-bottom: 40px;
        }

        .filter-card {
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        button {
            width: 100%;
            justify-content: center;
        }
    }

    /* === Responsive Table Fix === */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive table {
            min-width: 600px;
        }

        @media (max-width: 768px) {
            #terbersihTable thead th,
            #terbersihTable tbody td {
                white-space: nowrap;
                font-size: 14px;
                padding: 12px 8px;
            }

            .clean-count {
                font-size: 13px;
                padding: 5px 10px;
            }

            .medal {
                width: 20px;
                height: 20px;
                font-size: 12px;
                line-height: 20px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <h2><i class="fas fa-award"></i> Top 3 Kamar Terbersih</h2>
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
                
                <button type="submit"><i class="fas fa-filter"></i> Terapkan Filter</button>
            </form>
        </div>

    <div class="table-responsive">
        <table id="terbersihTable" class="display">

            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Kamar</th>
                    <th>Jumlah Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $medal = '';
                        if ($no === 1) {
                            $medal = '<span class="medal gold"><i class="fas fa-trophy"></i></span>';
                        } elseif ($no === 2) {
                            $medal = '<span class="medal silver">2</span>';
                        } elseif ($no === 3) {
                            $medal = '<span class="medal bronze">3</span>';
                        }
                        
                        $highlightClass = $row['jumlah_pelanggaran'] == 0 ? 'perfect-clean' : '';
                        
                        echo "<tr class='{$highlightClass}'>
                                <td>{$medal} Peringkat {$no}</td>
                                <td>Kamar {$row['kamar']}</td>
                                <td><span class='clean-count'>{$row['jumlah_pelanggaran']} pelanggaran</span></td>
                              </tr>";
                        $no++;
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align: center;'>Tidak ada data ditemukan</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#terbersihTable').DataTable({
                "paging": false,
                "info": false,
                "searching": false,
                "order": [[ 0, "asc" ]],
                "language": {
                    "emptyTable": "Tidak ada data yang tersedia"
                }
            });
            
            // Add animation to table rows
            $('#terbersihTable tbody tr').each(function(i) {
                $(this).css('opacity', 0)
                       .delay(i * 150)
                       .animate({'opacity': 1, 'marginLeft': '0'}, 400);
            });
        });
    </script>
</body>
</html>

<?php include '../footer.php'; ?>