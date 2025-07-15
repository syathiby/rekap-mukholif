<?php
include '../../../db.php';
include '../../../header.php';

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil kamar yang pernah melanggar + info eksekusi terakhir
$filterBelum = isset($_GET['filter']) && $_GET['filter'] === 'belum';

$kamarQuery = mysqli_query($conn, "
    SELECT 
        pk.kamar,
        MAX(pk.tanggal) AS terakhir_pelanggaran,
        (
            SELECT MAX(ek.tanggal_eksekusi)
            FROM eksekusi_kebersihan ek
            WHERE ek.kamar = pk.kamar
        ) AS terakhir_eksekusi
    FROM pelanggaran_kebersihan pk
    WHERE pk.kamar IS NOT NULL AND pk.kamar != ''
    GROUP BY pk.kamar
    HAVING " . ($filterBelum ? "terakhir_eksekusi IS NULL OR terakhir_eksekusi < terakhir_pelanggaran" : "1") . "
    ORDER BY
        REGEXP_REPLACE(pk.kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(pk.kamar, '[^0-9]', '') AS UNSIGNED) ASC
");

if (!$kamarQuery) {
    die("Error dalam query kamar: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Eksekusi Pelanggaran Kebersihan Kamar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --warning-color: #ffb347;
            --danger-color: #ff4d4d;
            --success-color: #4CAF50;
            --info-color: #17a2b8;
        }
        
        body {
            background: linear-gradient(to right, #f5f7fa, #e4e8f0);
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-bottom: none;
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.15rem 0.5rem rgba(0, 0, 0, 0.05);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #1149cb 0%, #1149cb 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(106, 17, 203, 0.05);
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.4em 0.7em;
            font-size: 0.75em;
            letter-spacing: 0.5px;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.6rem 0.85rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 17, 203, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #ff8c00 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 179, 71, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #cc0000 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 77, 77, 0.3);
        }
        
        .mobile-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4em 0.8em;
            border-radius: 50px;
            font-size: 0.75em;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .filter-container {
            background-color: white;
            padding: 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 0.75rem rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .alert {
            border-radius: 0.5rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            border-left-color: var(--success-color);
        }
        
        .alert-danger {
            border-left-color: var(--danger-color);
        }
        
        .kamar-checkbox {
            width: 1.3em;
            height: 1.3em;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        .kamar-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table th, .table td {
                padding: 0.6rem;
            }
            
            .card-header h1 {
                font-size: 1.25rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card shadow-lg">
            <div class="card-header text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h4 mb-0"><i class="fas fa-broom me-2"></i>Eksekusi Pelanggaran Kebersihan</h1>
                    <span class="badge bg-light text-dark">
                        <i class="far fa-calendar-alt me-1"></i><?= date('d F Y') ?>
                    </span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <div><?= htmlspecialchars($_SESSION['success']); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?= htmlspecialchars($_SESSION['error']); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-container mb-4">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="filter" value="belum"
                                    id="filterBelum" onchange="this.form.submit()" 
                                    <?= (isset($_GET['filter']) && $_GET['filter'] === 'belum') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filterBelum">
                                    <i class="fas fa-filter me-1"></i>
                                    Tampilkan hanya kamar yang <strong>belum dihukum</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Total data: <?= mysqli_num_rows($kamarQuery) ?>
                            </span>
                        </div>
                    </form>
                </div>

                <form action="process.php" method="POST" id="pelanggaranForm">
                    <div class="table-responsive mb-4">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="text-center">
                                <tr>
                                    <th scope="col" width="5%">No</th>
                                    <th scope="col" width="10%">Pilih</th>
                                    <th scope="col" width="15%">Kamar</th>
                                    <th scope="col" width="20%">Pelanggaran Terakhir</th>
                                    <th scope="col" width="20%">Eksekusi Terakhir</th>
                                    <th scope="col" width="15%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                            $no = 1;
                            while($row = mysqli_fetch_assoc($kamarQuery)):
                                $kamar = htmlspecialchars($row['kamar']);
                                $pelanggaran = $row['terakhir_pelanggaran'] ? date('d M Y H:i', strtotime($row['terakhir_pelanggaran'])) : '-';
                                $eksekusi = $row['terakhir_eksekusi'] ? date('d M Y H:i', strtotime($row['terakhir_eksekusi'])) : '-';

                                // Cek status eksekusi
                                if (!empty($row['terakhir_eksekusi']) && strtotime($row['terakhir_eksekusi']) >= strtotime($row['terakhir_pelanggaran'])) {
                                    $status = 'Sudah Dihukum';
                                    $badge = 'success';
                                    $status_color = 'var(--success-color)';
                                } else {
                                    $status = 'Belum Dihukum';
                                    $badge = 'danger';
                                    $status_color = 'var(--danger-color)';
                                }
                            ?>
                                <tr class="text-center">
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <input type="checkbox" 
                                            class="form-check-input kamar-checkbox" 
                                            name="kamar[]" 
                                            value="<?= $kamar ?>"
                                            <?= ($status === 'Sudah Dihukum') ? 'disabled title="Kamar ini sudah dihukum"' : '' ?>
                                            style="width: 1.2em; height: 1.2em;">
                                    </td>
                                    <td class="fw-bold"><i class="fas fa-door-open me-2"></i><?= $kamar ?></td>
                                    <td><i class="far fa-calendar-times me-2"></i><?= $pelanggaran ?></td>
                                    <td><i class="far fa-calendar-check me-2"></i><?= $eksekusi ?></td>
                                    <td>
                                        <span class="status-badge bg-<?= $badge ?>-subtle text-<?= $badge ?>">
                                            <span class="status-indicator" style="background-color: <?= $status_color ?>"></span>
                                            <?= $status ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-gradient">
                                <i class="fas fa-gavel me-1"></i>Jenis Hukuman
                            </label>
                            <input type="text" name="jenis_hukuman" class="form-control" 
                                   placeholder="Contoh: Pembersihan kamar mandi, dll" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-gradient">
                                <i class="far fa-clock me-1"></i>Tanggal Eksekusi
                            </label>
                            <input type="datetime-local" name="tanggal" class="form-control" 
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold text-gradient">
                                <i class="far fa-sticky-note me-1"></i>Catatan Tambahan
                            </label>
                            <textarea name="catatan" class="form-control" rows="3" 
                                      placeholder="Deskripsi pelanggaran dan tindakan hukuman..."></textarea>
                        </div>
                    </div>

                    <!-- Desktop Buttons -->
                    <div class="d-none d-md-flex gap-3 justify-content-end mt-4">
                        <button type="button" class="btn btn-danger px-4" onclick="resetSelection()">
                            <i class="fas fa-undo me-1"></i> Reset Pilihan
                        </button>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Simpan Eksekusi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Action Buttons -->
    <div class="mobile-actions d-md-none">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger flex-grow-1" onclick="resetSelection()">
                <i class="fas fa-undo me-1"></i> Reset
            </button>
            <button type="submit" form="pelanggaranForm" class="btn btn-primary flex-grow-1">
                <i class="fas fa-save me-1"></i> Simpan
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk mereset pilihan kamar
        function resetSelection() {
            document.querySelectorAll('.kamar-checkbox:not(:disabled)').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Show feedback
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.innerHTML = `
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-danger text-white">
                        <strong class="me-auto"><i class="fas fa-check-circle me-1"></i> Sukses</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Semua pilihan kamar telah direset
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Optimasi untuk mobile: scroll ke form saat memilih kamar
        document.querySelectorAll('.kamar-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (window.innerWidth < 768 && this.checked) {
                    setTimeout(() => {
                        document.querySelector('#pelanggaranForm').scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 300);
                }
            });
        });
        
        // Add animation to table rows
        document.querySelectorAll('tbody tr').forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            row.style.transition = `all 0.3s ease ${index * 0.05}s`;
            
            setTimeout(() => {
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>

<?php 
include '../../../footer.php'; 
mysqli_close($conn);
?>