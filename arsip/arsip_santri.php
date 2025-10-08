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
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1">Rekap Poin Santri Arsip: <?= htmlspecialchars($meta['judul']) ?></h2>
            <p class="text-muted">Total <?= $data_santri->num_rows ?> santri terekam dalam arsip ini.</p>
        </div>
        <a href="view.php?id=<?= $arsip_id ?>" class="btn btn-light"> &larr; Kembali ke Dashboard Arsip</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Santri</th>
                            <th>Kelas</th>
                            <th>Kamar</th>
                            <th class="text-center">Total Poin Saat Arsip</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_santri->num_rows > 0): $no = 1; ?>
                            <?php while($row = $data_santri->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['santri_nama']) ?></td>
                                    <td><?= htmlspecialchars($row['santri_kelas']) ?></td>
                                    <td><?= htmlspecialchars($row['santri_kamar']) ?></td>
                                    <td class="text-center fw-bold <?= $row['total_poin_saat_arsip'] > 0 ? 'text-danger' : '' ?>">
                                        <?= $row['total_poin_saat_arsip'] ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">Tidak ada data santri di arsip ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>