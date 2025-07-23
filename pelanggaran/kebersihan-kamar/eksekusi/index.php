<?php
include '../../../db.php';
include '../../../header.php';

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pelanggaranQuery = mysqli_query($conn, "
    SELECT pk.id AS pelanggaran_id, pk.kamar, pk.tanggal AS tanggal_pelanggaran
    FROM pelanggaran_kebersihan pk
    LEFT JOIN eksekusi_kebersihan ek 
        ON ek.kamar = pk.kamar AND ek.tanggal_eksekusi >= pk.tanggal
    WHERE ek.id IS NULL AND pk.kamar IS NOT NULL AND pk.kamar != ''
    ORDER BY
        REGEXP_REPLACE(pk.kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(pk.kamar, '[^0-9]', '') AS UNSIGNED) ASC,
        pk.tanggal ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelanggaran Kebersihan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --warning-color: #f0ad4e;
            --danger-color: #d9534f;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.5s ease-in-out;
        }
        
        h1 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            animation: fadeInUp 0.5s ease-in-out;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s ease;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        th i {
            margin-right: 8px;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #f1f3ff;
        }
        
        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-in-out;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        input[type="text"],
        input[type="datetime-local"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            transition: all 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="datetime-local"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(76, 201, 240, 0.2);
            outline: none;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-custom:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-custom i {
            margin-right: 8px;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary-color);
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        input[type="checkbox"]:hover {
            transform: scale(1.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(75, 181, 67, 0.2);
            color: var(--success-color);
        }
        
        .badge-warning {
            background-color: rgba(240, 173, 78, 0.2);
            color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: rgba(217, 83, 79, 0.2);
            color: var(--danger-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        
        .btn-edit {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            animation: fadeIn 0.8s ease-in-out;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
            display: block;
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
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            h1 {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="animate__animated animate__fadeIn">
            <i class="fas fa-broom"></i> 
            <span>Manajemen Pelanggaran Kebersihan</span>
        </h1>
        
        <div class="card animate__animated animate__fadeInUp">
            <h2><i class="fas fa-clipboard-list"></i> Daftar Pelanggaran Belum Dieksekusi</h2>
            
            <form action="process.php" method="POST">
                <?php if(mysqli_num_rows($pelanggaranQuery) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> No</th>
                            <th><i class="fas fa-check-circle"></i> Pilih</th>
                            <th><i class="fas fa-door-open"></i> Kamar</th>
                            <th><i class="fas fa-calendar-times"></i> Tanggal Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; while($row = mysqli_fetch_assoc($pelanggaranQuery)): ?>
                        <tr class="animate__animated animate__fadeIn" style="animation-delay: <?= $no * 0.05 ?>s;">
                            <td><?= $no++ ?></td>
                            <td class="checkbox-container">
                                <input type="checkbox" name="pelanggaran_id[]" value="<?= $row['pelanggaran_id'] ?>">
                            </td>
                            <td>
                                <i class="fas fa-door-closed" style="margin-right: 8px;"></i>
                                <?= htmlspecialchars($row['kamar']) ?>
                            </td>
                            <td>
                                <i class="far fa-clock" style="margin-right: 8px;"></i>
                                <?= date('d M Y H:i', strtotime($row['tanggal_pelanggaran'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-check-circle"></i>
                    <h3>Tidak Ada Pelanggaran yang Perlu Dieksekusi</h3>
                    <p>Semua pelanggaran kebersihan telah ditangani dengan baik.</p>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="jenis_hukuman"><i class="fas fa-exclamation-triangle"></i> Jenis Hukuman</label>
                        <input type="text" name="jenis_hukuman" id="jenis_hukuman" placeholder="Masukkan jenis hukuman" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal"><i class="fas fa-calendar-check"></i> Tanggal Eksekusi</label>
                        <input type="datetime-local" name="tanggal" id="tanggal" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="catatan"><i class="fas fa-edit"></i> Catatan Tambahan</label>
                    <textarea name="catatan" id="catatan" placeholder="Masukkan catatan tambahan"></textarea>
                </div>
                
                <button type="submit" class="btn btn-custom animate__animated animate__pulse animate__infinite animate__slower">
                    <i class="fas fa-check-circle"></i> Simpan Eksekusi
                </button>
            </form>
        </div>
    </div>

    <?php include '../../../footer.php'; mysqli_close($conn); ?>
</body>
</html>