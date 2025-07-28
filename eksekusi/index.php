<?php
include '../db.php';
include '../header.php';

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil pelanggaran yang belum dieksekusi
$pelanggaranQuery = mysqli_query($conn, "
    SELECT pk.id AS pelanggaran_id, pk.kamar, pk.tanggal AS tanggal_pelanggaran
    FROM pelanggaran_kebersihan pk
    LEFT JOIN eksekusi_kebersihan ek ON ek.pelanggaran_id = pk.id
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
    <title>Form Eksekusi Pelanggaran Kebersihan</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #5d69b3;
            --secondary-color: #57c4b0;
            --accent-color: #f5a623;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #4caf50;
            --info-color: #2196f3;
            --warning-color: #ff9800;
            --danger-color: #f44336;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease-out;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .table {
            animation: slideInUp 0.6s ease-out;
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        .table th {
            border: none;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(87, 196, 176, 0.08);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 12px 24px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(93, 105, 179, 0.2);
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(87, 196, 176, 0.25);
        }
        .no-data {
            animation: fadeIn 1s ease-out;
            background-color: rgba(245, 166, 35, 0.05);
        }
        .icon-cell {
            text-align: center;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        h4 {
            color: var(--primary-color);
            border-bottom: 2px solid rgba(93, 105, 179, 0.1);
            padding-bottom: 12px;
            margin-bottom: 25px;
            position: relative;
            font-weight: 600;
        }
        h4::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 3px;
        }
        .input-group-text {
            background-color: rgba(87, 196, 176, 0.1);
            color: var(--secondary-color);
            border: 1px solid rgba(87, 196, 176, 0.3);
        }
        .form-label {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 8px;
        }
        .table td {
            color: #555;
            vertical-align: middle;
        }
        .table td:first-child {
            color: var(--primary-color);
            font-weight: 500;
        }
        .fa-door-closed {
            color: var(--primary-color);
        }
        .fa-clock {
            color: var(--secondary-color);
        }
        .fa-smile-beam {
            color: var(--accent-color);
            opacity: 0.7;
        }
    </style>
</head>
<body>
<div class="container animate__animated animate__fadeIn">
    <h4 class="mb-4">
        <i class="fas fa-broom me-2" style="color: var(--secondary-color);"></i>Form Eksekusi Pelanggaran Kebersihan
    </h4>

    <form action="process.php" method="POST">
        <table class="table table-bordered table-hover animate__animated animate__slideInUp">
            <thead>
                <tr>
                    <th>No</th>
                    <th class="icon-cell"><i class="fas fa-check-circle"></i></th>
                    <th><i class="fas fa-door-open me-2"></i>Kamar</th>
                    <th><i class="fas fa-calendar-alt me-2"></i>Tanggal Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;
            $adaData = false;
            while ($row = mysqli_fetch_assoc($pelanggaranQuery)):
                $adaData = true;
            ?>
                <tr class="animate__animated animate__fadeIn">
                    <td><?= $no++ ?></td>
                    <td class="icon-cell"><input type="checkbox" name="pelanggaran_id[]" value="<?= $row['pelanggaran_id'] ?>" class="form-check-input" style="cursor: pointer;"></td>
                    <td><i class="fas fa-door-closed me-2"></i><?= htmlspecialchars($row['kamar']) ?></td>
                    <td><i class="far fa-clock me-2"></i><?= date('d M Y H:i', strtotime($row['tanggal_pelanggaran'])) ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if (!$adaData): ?>
                <tr class="no-data"><td colspan="4" class="text-center py-4" style="color: var(--accent-color);"><i class="far fa-smile-beam fa-2x mb-2"></i><br>Tidak ada pelanggaran yang bisa dieksekusi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="mb-4 animate__animated animate__fadeIn">
            <label class="form-label"><i class="fas fa-gavel me-2" style="color: var(--accent-color);"></i>Jenis Hukuman:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-exclamation-circle"></i></span>
                <input type="text" name="jenis_hukuman" class="form-control" required>
            </div>
        </div>

        <div class="mb-4 animate__animated animate__fadeIn">
            <label class="form-label"><i class="far fa-calendar-check me-2" style="color: var(--secondary-color);"></i>Tanggal Eksekusi:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                <input type="datetime-local" name="tanggal" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
            </div>
        </div>

        <div class="mb-4 animate__animated animate__fadeIn">
            <label class="form-label"><i class="fas fa-sticky-note me-2" style="color: var(--primary-color);"></i>Catatan (Opsional):</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-pen"></i></span>
                <textarea name="catatan" class="form-control" rows="3" style="resize: none;"></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-submit btn-lg animate__animated animate__pulse animate__infinite animate__slower">
            <i class="fas fa-save me-2"></i>Simpan Eksekusi
        </button>
    </form>
</div>

<?php include '../../../footer.php'; mysqli_close($conn); ?>

<!-- Animation trigger script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation class to each table row with delay
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.1}s`;
        });
    });
</script>
</body>
</html>