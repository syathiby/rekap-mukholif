<?php
include '../db.php';
include '../header.php';

// Ambil periode aktif dari pengaturan
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row ? $row['nilai'] : '2000-01-01';

// Ambil data filter dari form
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$sort_order = $_GET['sort'] ?? 'desc'; // default: terbanyak

// Query dasar
$query = "
    SELECT kamar, COUNT(*) AS total_pelanggaran
    FROM pelanggaran_kebersihan
    WHERE tanggal >= '$periode_aktif'
";

// Tambahin filter tanggal kalau ada input
if ($tanggal_awal && $tanggal_akhir) {
    $query .= " AND DATE(tanggal) BETWEEN '$tanggal_awal' AND '$tanggal_akhir'";
}

// Tentukan ASC atau DESC
$order = ($sort_order === 'asc') ? 'ASC' : 'DESC';

$query .= " GROUP BY kamar ORDER BY total_pelanggaran $order";

$result = mysqli_query($conn, $query) or die("Query Error: " . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pelanggaran Kebersihan per Kamar</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        #rekapTable {
            width: 100% !important;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        #rekapTable thead th {
            background-color: var(--primary) !important;
            color: white !important;
            font-weight: 500;
            padding: 16px 12px;
            text-align: center;
        }
        
        #rekapTable tbody tr {
            transition: all 0.2s ease;
        }
        
        #rekapTable tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05) !important;
        }
        
        #rekapTable tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }
        
        .pelanggaran-count {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            background-color: #ffebee;
            color: var(--danger);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 12px !important;
            border-radius: 6px !important;
            margin: 0 3px !important;
            border: 1px solid #ddd !important;
            transition: all 0.3s ease !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: white !important;
            border-color: var(--primary) !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: white !important;
            border-color: var(--primary) !important;
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
            
            input[type="date"], select {
                width: 100%;
            }
        }

        /* === Responsive Fix for Tables === */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive .table {
            min-width: 700px; /* Bisa diubah sesuai isi tabel */
            width: 100%;
        }

        .table th, .table td {
            white-space: nowrap; /* Hindari teks meluber ke bawah */
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        /* Tambahan spacing tombol aksi */
        .table td .btn + .btn {
            margin-left: 0.4rem;
        }

        /* Responsive padding tombol aksi */
        @media (max-width: 576px) {
            .table td .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <h2><i class="fas fa-home"></i> Rekap Pelanggaran Kebersihan per Kamar</h2>
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

    <div class="table-responsive">
        <table id="rekapTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kamar</th>
                    <th>Jumlah Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                                <td>{$no}</td>
                                <td>Kamar {$row['kamar']}</td>
                                <td><span class='pelanggaran-count'>{$row['total_pelanggaran']}</span></td>
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
            $('#rekapTable').DataTable({
                "pageLength": 10,
                "language": {
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Tidak ada data yang ditemukan",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data tersedia",
                    "infoFiltered": "(difilter dari _MAX_ total data)",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "initComplete": function() {
                    $('.dataTables_filter input').attr('placeholder', 'Cari kamar...');
                }
            });
            
            // Add animation to table rows
            $('#rekapTable tbody tr').each(function(i) {
                $(this).css('opacity', 0)
                       .delay(i * 100)
                       .animate({'opacity': 1, 'marginLeft': '0'}, 400);
            });
        });
    </script>
</body>
</html>

<?php include '../footer.php'; ?>