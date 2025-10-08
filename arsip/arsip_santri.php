<?php
require_once __DIR__ . '/../header.php';
guard('arsip_view');

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

// Ambil semua data santri dari arsip
$stmt_santri = $conn->prepare("SELECT * FROM arsip_data_santri WHERE arsip_id = ? ORDER BY total_poin_saat_arsip DESC, santri_nama ASC");
$stmt_santri->bind_param('i', $arsip_id);
$stmt_santri->execute();
$data_santri = $stmt_santri->get_result();
?>

<style>
    :root {
        --primary-color: #4f46e5;
        --border-color: #e5e7eb;
        --card-bg: #ffffff;
        --text-dark: #1f2937;
        --text-light: #6b7280;
        --danger-bg: #fee2e2;
        --danger-text: #ef4444;
    }

    .page-header {
        background-color: var(--card-bg);
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }

    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-dark);
    }

    .page-header .text-muted {
        color: var(--text-light) !important;
        font-size: 0.95rem;
    }

    .card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .table th {
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-light);
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        white-space: nowrap; /* Biar judul kolom nggak ke-wrap */
    }

    .table td {
        vertical-align: middle;
    }
    
    .table-responsive {
        overflow-x: auto;
    }

    .poin-badge {
        display: inline-block;
        padding: 0.35em 0.8em;
        font-size: 0.85em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 50rem; /* pill shape */
        color: var(--danger-text);
        background-color: var(--danger-bg);
    }
</style>

<div class="container mt-4 mb-5">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">Rekap Poin Santri Arsip: <?= htmlspecialchars($meta['judul']) ?></h2>
            <p class="text-muted mb-0">Total <strong><?= $data_santri->num_rows ?> santri</strong> terekam dalam arsip ini.</p>
        </div>
        <a href="view.php?id=<?= $arsip_id ?>" class="btn btn-outline-secondary mt-3 mt-md-0">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Nama Santri</th>
                            <th class="text-center">Kelas</th>
                            <th class="text-center">Kamar</th>
                            <th class="text-center">Total Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_santri->num_rows > 0): $no = 1; ?>
                            <?php while($row = $data_santri->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['santri_nama']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['santri_kelas']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['santri_kamar']) ?></td>
                                    <td class="text-center">
                                        <?php if($row['total_poin_saat_arsip'] > 0): ?>
                                            <span class="poin-badge">
                                                <?= $row['total_poin_saat_arsip'] ?>
                                            </span>
                                        <?php else: ?>
                                            0
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-5">Tidak ada data santri di arsip ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>