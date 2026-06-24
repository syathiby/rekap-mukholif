<?php
require_once __DIR__ . '/../../bootstrap/init.php';

if (!has_permission('rapot_create') && !has_permission('rapot_view')) {
    echo '<div class="alert alert-danger mb-0 small">Akses ditolak</div>';
    exit;
}

$santri_id = isset($_POST['santri_id']) ? (int)$_POST['santri_id'] : 0;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : '';
$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;

$edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

if (!$santri_id || !$bulan || !$tahun) {
    exit;
}

$bulan_list_indo = [
    'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
    'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
    'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
];

$bulan_num = $bulan_list_indo[$bulan] ?? date('n');
$tahun_ajaran_start = ($bulan_num < 7) ? $tahun - 1 : $tahun;
$tahun_ajaran_end = $tahun_ajaran_start + 1;
$tahun_ajaran_label = "$tahun_ajaran_start/$tahun_ajaran_end";

// Count how many reports exist for this student in this academic year.
// Exclude the rapot currently being edited so the progress bar stays accurate.
$sql = "SELECT id, bulan, tahun FROM rapot_kepengasuhan WHERE santri_id = ?";
$params_count = [$santri_id];
$types_count  = "i";

if ($edit_id > 0) {
    $sql          .= " AND id != ?";
    $params_count[] = $edit_id;
    $types_count  .= "i";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_count, ...$params_count);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
$is_editing_current_ta = ($edit_id > 0); // true jika dalam mode edit

while ($row = $result->fetch_assoc()) {
    $b_num = $bulan_list_indo[$row['bulan']] ?? 1;
    $b_tahun = (int)$row['tahun'];
    $b_ta_start = ($b_num < 7) ? $b_tahun - 1 : $b_tahun;
    
    if ($b_ta_start == $tahun_ajaran_start) {
        $count++;
    }
}
$stmt->close();

$percentage = min(100, ($count / 12) * 100);
$bar_color = 'bg-success';
if ($count >= 12) {
    $bar_color = 'bg-danger';
} elseif ($count >= 9) {
    $bar_color = 'bg-warning';
}

?>
<div class="card bg-light border-0 shadow-sm mt-2">
    <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
            <span class="small fw-bold text-secondary lh-sm">Progress Rapot Tahun Ajaran <?php echo $tahun_ajaran_label; ?></span>
            <span class="small fw-bold text-nowrap flex-shrink-0 <?php echo $count >= 12 ? 'text-danger' : 'text-primary'; ?>"><?php echo $count; ?> / 12</span>
        </div>
        <div class="progress" style="height: 8px; border-radius: 4px;">
            <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $count; ?>" aria-valuemin="0" aria-valuemax="12"></div>
        </div>
        <?php if ($count >= 12): ?>
            <?php if ($is_editing_current_ta): ?>
                <div class="small text-info mt-1"><i class="fas fa-info-circle"></i> Kuota penuh (12/12). Anda sedang dalam mode Edit sehingga perubahan tetap dapat disimpan.</div>
            <?php else: ?>
                <div class="small text-danger mt-1"><i class="fas fa-exclamation-triangle"></i> Peringatan: Kuota rapot tahun ajaran ini sudah maksimal (12). Rapot baru tidak dapat ditambahkan.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
