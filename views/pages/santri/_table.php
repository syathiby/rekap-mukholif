<?php
$can_edit = \App\Helpers\AuthHelper::hasPermission('santri_edit');
$can_delete = \App\Helpers\AuthHelper::hasPermission('santri_delete');

$colspan = 5;
if ($can_delete) $colspan++;
if ($can_edit || $can_delete) $colspan++;
?>

<!-- Hidden input to transport total back to main view via HTMX swap -->
<input type="hidden" id="new-total" value="<?= number_format($total) ?>">

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <?php if ($can_delete): ?>
                        <th class="text-center" width="50px"><input type="checkbox" id="selectAll"></th>
                    <?php endif; ?>
                    <th width="5%" class="text-center">No.</th>
                    <th>Nama Santri</th>
                    <th>Kelas</th>
                    <th>Kamar</th>
                    <th class="text-center">
                        Poin Histori
                    </th>
                    <?php if ($can_edit || $can_delete): ?>
                        <th class="action-cell">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($santri) > 0): ?>
                    <?php $no = 1; foreach ($santri as $row): ?>
                        <tr>
                            <?php if ($can_delete): ?>
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="row-checkbox" value="<?= $row['id'] ?>" name="ids[]" form="bulkDeleteForm">
                                </td>
                            <?php endif; ?>
                            <td class="text-center align-middle"><?= $no++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-sm" 
                                             style="background-color: #<?= substr(md5((string)$row['id']), 0, 6) ?>; width: 40px; height: 40px; font-weight: 600;">
                                            <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <strong class="text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.8rem;">ID: <?= $row['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle"><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill"><?= htmlspecialchars($row['kelas']) ?></span></td>
                            <td class="align-middle">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-door-open me-1"></i>
                                    <?= htmlspecialchars($row['kamar']) ?>
                                </span>
                            </td>
                            <td class="align-middle text-center">
                                <?php 
                                $poin_histori = (int)$row['poin_aktif']; 
                                if ($poin_histori > 0) {
                                    echo '<span class="badge bg-danger rounded-pill px-2 py-1">' . $poin_histori . ' Poin</span>';
                                } elseif ($poin_histori < 0) {
                                    echo '<span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-star me-1 text-warning"></i>' . abs($poin_histori) . ' Poin Reward</span>';
                                } else {
                                    echo '<span class="badge bg-secondary rounded-pill px-2 py-1">0 Poin (Bersih)</span>';
                                }
                                ?>
                            </td>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="align-middle">
                                    <div class="d-flex">
                                        <?php if ($can_edit): ?>
                                            <a href="<?= BASE_URL ?>/santri/<?= $row['id'] ?>/edit" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <form action="<?= BASE_URL ?>/santri/<?= $row['id'] ?>/delete" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus santri ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?= $colspan ?>" class="text-center py-4"><h5>Tidak ada data santri ditemukan.</h5><p class="text-muted">Coba ubah atau reset filter pencarian Anda.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
