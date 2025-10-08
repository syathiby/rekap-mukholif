<?php 
require_once __DIR__ . '/../header.php';
guard('arsip_view'); 
?>

<?php
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

<style>
    /* === CSS FINAL DENGAN SCOPING ===
      Semua style di bawah ini gw kasih prefix '#arsip-index-page'
      Artinya, style ini HANYA akan berlaku di dalam div <div id="arsip-index-page">
      dan TIDAK AKAN BISA merusak header utama lu lagi.
    */
    #arsip-index-page .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    #arsip-index-page .page-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    #arsip-index-page .page-title i {
        color: #4f46e5;
        font-size: 1.5rem;
    }

    #arsip-index-page .card-table-wrapper {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        padding: 1rem;
        overflow: hidden;
    }

    #arsip-index-page .table-responsive {
        overflow-x: auto;
    }

    #arsip-index-page table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    #arsip-index-page th, 
    #arsip-index-page td {
        padding: 1rem 1.5rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
        font-size: 0.9rem;
    }
    
    #arsip-index-page tbody tr:last-child td {
        border-bottom: none;
    }

    #arsip-index-page th {
        color: #6b7280;
        text-transform: uppercase;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    #arsip-index-page tbody tr:hover {
        background-color: #f8fafc;
    }

    #arsip-index-page .btn {
        padding: 0.6rem 1.25rem;
        border-radius: 0.5rem;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    #arsip-index-page .btn-primary { background-color: #4f46e5; color: white; }
    #arsip-index-page .btn-lihat { background-color: #eef2ff; color: #4338ca; }
    #arsip-index-page .btn-hapus { background-color: #fee2e2; color: #b91c1c; }

    #arsip-index-page .desktop-table { display: block; }
    #arsip-index-page .mobile-cards { display: none; }

    @media (max-width: 992px) {
        #arsip-index-page { padding: 1rem; }
        #arsip-index-page .desktop-table { display: none; }
        #arsip-index-page .mobile-cards { display: block; }
        #arsip-index-page .card-table-wrapper { background: none; border: none; box-shadow: none; padding: 0; }
        
        #arsip-index-page .page-header { justify-content: flex-start; }

        #arsip-index-page .arsip-card-mobile {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        #arsip-index-page .arsip-card-mobile h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4f46e5;
            margin: 0 0 1rem 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        #arsip-index-page .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0;
            font-size: 0.9rem;
        }

        #arsip-index-page .info-row .label { color: #6b7280; }
        #arsip-index-page .info-row .value { font-weight: 500; text-align: right; }

        #arsip-index-page .actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 0.75rem;
        }
        #arsip-index-page .actions .btn { flex-grow: 1; }
    }
</style>

<div id="arsip-index-page" class="container-fluid mt-4">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-archive"></i>Manajemen Arsip</h1>
        <?php if (has_permission('arsip_create')): ?>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Arsip Baru</a>
        <?php endif; ?>
    </div>
    
    <?php display_flash_message(); ?>

    <div class="card-table-wrapper">
        <div class="table-responsive desktop-table">
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
                                <?= date('d M Y', strtotime($row['tanggal_mulai'])); ?>
                                <i class="fas fa-arrow-right mx-2 text-muted fa-sm"></i>
                                <?= date('d M Y', strtotime($row['tanggal_selesai'])); ?>
                            </td>
                            <td class="text-center fw-bold"><?= $row['total_data']; ?></td>
                            <td><?= date('d M Y, H:i', strtotime($row['dibuat_pada'])); ?></td>
                            <td class="text-end">
                                <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-lihat"><i class="fas fa-eye"></i> Lihat</a>
                                <?php if (has_permission('arsip_delete')): ?>
                                    <form class="d-inline" action="process.php" method="post" onsubmit="return confirm('Yakin hapus arsip ini? Data di dalamnya akan hilang permanen!');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id']; ?>">
                                        <button type="submit" class="btn btn-hapus"><i class="fas fa-trash-alt"></i> Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted">Belum ada data arsip.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mobile-cards">
            <?php if($res && $res->num_rows > 0): mysqli_data_seek($res, 0); ?>
                <?php while($row = $res->fetch_assoc()): ?>
                    <div class="arsip-card-mobile">
                        <h5><a href="view.php?id=<?= $row['id']; ?>" class="text-decoration-none"><?= htmlspecialchars($row['judul']); ?></a></h5>
                        <div class="info-row">
                            <span class="label">Periode</span>
                            <span class="value"><?= date('d M y', strtotime($row['tanggal_mulai'])); ?> - <?= date('d M y', strtotime($row['tanggal_selesai'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Total Data</span>
                            <span class="value fw-bold"><?= $row['total_data']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Dibuat</span>
                            <span class="value"><?= date('d M Y, H:i', strtotime($row['dibuat_pada'])); ?></span>
                        </div>
                        <div class="actions">
                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-lihat"><i class="fas fa-eye"></i> Lihat</a>
                            <?php if (has_permission('arsip_delete')): ?>
                                <form class="w-100" action="process.php" method="post" onsubmit="return confirm('Yakin hapus arsip ini? Data di dalamnya akan hilang permanen!');">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id']; ?>">
                                    <button type="submit" class="btn btn-hapus"><i class="fas fa-trash-alt"></i> Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card"><div class="card-body text-center p-5 text-muted">Belum ada data arsip.</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../footer.php'; ?>