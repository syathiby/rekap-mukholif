<?php 
require_once __DIR__ . '/../header.php';
guard('arsip_manage'); 
?>

<?php

// Ambil semua arsip beserta total data di dalamnya
$sql = "
SELECT 
    a.*,
    (SELECT COUNT(*) FROM arsip_data_pelanggaran adp WHERE adp.arsip_id = a.id) AS total_data
FROM arsip a
ORDER BY a.dibuat_pada DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Arsip Pelanggaran</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
            --danger: #ef4444; --danger-light: #fee2e2; --danger-dark: #dc2626;
            --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
            --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; color: var(--text-dark); }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        
        /* REVISI 2: JUDUL LEBIH KEREN */
        .page-title { font-size: 2rem; font-weight: 700; color: var(--primary); }

        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .form-label { font-weight: 500; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; transition: all 0.2s; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; text-align: center; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-danger { background-color: var(--danger-light); color: var(--danger-dark); padding: 0.375rem 0.75rem; font-size: 0.875rem; }
        .btn-danger:hover { background-color: var(--danger); color: white; }
        .btn-outline-primary { background: none; border: 1px solid var(--primary); color: var(--primary); }
        .btn-outline-primary:hover { background-color: var(--primary); color: white; }
        
        /* Tampilan Tabel Desktop */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .desktop-table { display: table; }
        .mobile-cards { display: none; }

        /* REVISI 1: LOGIKA TABLE-TO-CARD UNTUK MOBILE */
        @media (max-width: 992px) {
            .desktop-table { display: none; }
            .mobile-cards { display: block; }
            .mobile-card {
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 0.75rem;
                padding: 1.5rem;
                margin-bottom: 1rem;
                box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            }
            .mobile-card h5 {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--primary-dark);
                margin-bottom: 1rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid var(--border-color);
            }
            .card-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
            }
            .card-row .label {
                font-weight: 500;
                color: var(--text-light);
            }
            .card-row .value {
                font-weight: 500;
                text-align: right;
            }
            .card-actions {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid var(--border-color);
                display: flex;
                gap: 0.5rem;
            }
            .card-actions .btn {
                flex-grow: 1;
            }
        }
        @media (max-width: 768px) {
            .page-header { justify-content: flex-start; }
            .page-title { font-size: 1.75rem; margin-bottom: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-archive me-3"></i>Manajemen Arsip</h1>
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-plus-circle me-2"></i>Buat Arsip Baru</h5>
            <form action="process.php" method="post" id="arsipForm">
                <input type="hidden" name="action" value="create">
                <div class="row g-3">
                    <div class="col-lg-12">
                        <label for="judul" class="form-label">Judul Arsip</label>
                        <input type="text" class="form-control" name="judul" id="judul" required placeholder="Contoh: Rekap Semester Ganjil 2024/2025">
                    </div>
                    <div class="col-md-5">
                        <label for="tgl_mulai" class="form-label">Periode Mulai</label>
                        <input type="date" class="form-control" name="tgl_mulai" id="tgl_mulai" required>
                    </div>
                    <div class="col-md-5">
                        <label for="tgl_selesai" class="form-label">Periode Selesai</label>
                        <input type="date" class="form-control" name="tgl_selesai" id="tgl_selesai" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-cogs me-2"></i>Proses</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tampilan Tabel untuk Desktop -->
    <div class="table-wrapper desktop-table">
        <div class="card">
            <div class="card-body p-0">
                 <table>
                    <thead>
                        <tr>
                            <th>Judul Arsip</th>
                            <th>Periode</th>
                            <th class="text-center">Total Data</th>
                            <th>Dibuat Pada</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res && $res->num_rows > 0): mysqli_data_seek($res, 0); ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><a href="view.php?id=<?= $row['id']; ?>" class="fw-bold text-decoration-none text-dark"><?= htmlspecialchars($row['judul']); ?></a></td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success-emphasis rounded-pill"><?= date('d M Y', strtotime($row['tanggal_mulai'])); ?></span>
                                    <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                    <span class="badge bg-success bg-opacity-10 text-success-emphasis rounded-pill"><?= date('d M Y', strtotime($row['tanggal_selesai'])); ?></span>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary-emphasis fw-bold rounded-pill fs-6"><?= $row['total_data']; ?></span></td>
                                <td><?= date('d M Y, H:i', strtotime($row['dibuat_pada'])); ?></td>
                                <td class="text-end">
                                    <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-2"><i class="fas fa-eye me-1"></i> Lihat</a>
                                    <form class="d-inline" action="process.php" method="post" onsubmit="return confirm('Yakin hapus arsip ini? Data di dalamnya akan hilang permanen!');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3"><i class="fas fa-trash-alt me-1"></i> Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-5 text-muted">Belum ada data arsip.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Tampilan Kartu untuk Mobile -->
    <div class="mobile-cards">
        <?php if($res && $res->num_rows > 0): mysqli_data_seek($res, 0); ?>
            <?php while($row = $res->fetch_assoc()): ?>
                <div class="mobile-card">
                    <h5><a href="view.php?id=<?= $row['id']; ?>" class="text-decoration-none text-primary"><?= htmlspecialchars($row['judul']); ?></a></h5>
                    <div class="card-row"><span class="label">Periode</span> <span class="value"><?= date('d M y', strtotime($row['tanggal_mulai'])); ?> - <?= date('d M y', strtotime($row['tanggal_selesai'])); ?></span></div>
                    <div class="card-row"><span class="label">Total Data</span> <span class="value"><?= $row['total_data']; ?></span></div>
                    <div class="card-row"><span class="label">Dibuat</span> <span class="value"><?= date('d M Y, H:i', strtotime($row['dibuat_pada'])); ?></span></div>
                    <div class="card-actions">
                        <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i> Lihat</a>
                        <form class="w-100" action="process.php" method="post" onsubmit="return confirm('Yakin hapus arsip ini? Data di dalamnya akan hilang permanen!');">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <button type="submit" class="btn btn-danger w-100"><i class="fas fa-trash-alt me-1"></i> Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card"><div class="card-body text-center p-5 text-muted">Belum ada data arsip.</div></div>
        <?php endif; ?>
    </div>

</div>
<?php include '../footer.php'; ?>
</body>
</html>