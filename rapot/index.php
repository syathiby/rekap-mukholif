<?php
// File: rekap-mukholif/rapot/index.php

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM'
guard('rapot_view');

// 3. Ambil data untuk filter dropdown
try {
    // Ambil daftar kamar unik
    $kamar_list_stmt = $conn->query("
        SELECT DISTINCT kamar FROM santri 
        WHERE kamar IS NOT NULL AND kamar != '' AND kamar != '0'
        ORDER BY CAST(kamar AS UNSIGNED)
    ");
    $kamar_list = $kamar_list_stmt->fetch_all(MYSQLI_ASSOC);

    // Siapin daftar bulan & tahun
    $bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tahun_sekarang = (int)date('Y');
    $tahun_list = [$tahun_sekarang, $tahun_sekarang - 1, $tahun_sekarang - 2];

} catch (Exception $e) {
    die("Error fetching filter data: " . $e->getMessage());
}


// 4. Proses Filter
$filter_kamar = $_GET['kamar'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// 5. Ambil data rapot (Sudah difilter)
try {
    $sql = "
        SELECT 
            r.id, 
            r.bulan, 
            r.tahun, 
            r.dibuat_pada,
            s.nama AS nama_santri,
            s.kamar AS kamar_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        LEFT JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE 1=1 
    "; 
    $params = [];
    $types = "";

    if (!empty($filter_kamar)) {
        $sql .= " AND s.kamar = ?";
        $params[] = $filter_kamar;
        $types .= "s";
    }
    if (!empty($filter_bulan)) {
        $sql .= " AND r.bulan = ?";
        $params[] = $filter_bulan;
        $types .= "s";
    }
    if (!empty($filter_tahun)) {
        $sql .= " AND r.tahun = ?";
        $params[] = $filter_tahun;
        $types .= "i";
    }
    $sql .= " ORDER BY r.tahun DESC, r.id DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rapot_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Error fetching rapot list: " . $e->getMessage());
}

// 6. Panggil Header
$page_title = "Daftar Rapot Kepengasuhan";
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        
        <?php if (has_permission('rapot_create')): ?>
            <a href="create.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i>
                <span class="d-none d-sm-inline">&nbsp;Buat Rapot Baru</span>
            </a>
        <?php endif; ?>
    </div>
    
    <?php 
    // Kalo header lu udah otomatis nampilin, hapus 3 baris ini
    if (function_exists('show_flash_message')) {
        show_flash_message();
    } 
    ?>

    <div class="card shadow mb-4">
        <a href="#collapseFilter" class="d-block card-header py-3" data-toggle="collapse" role="button" aria-expanded="true" aria-controls="collapseFilter">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter Data Rapot</h6>
        </a>
        <div class="collapse show" id="collapseFilter">
            <div class="card-body">
                <form action="index.php" method="GET" class="form-inline">
                    <div class="form-group mr-2 mb-2">
                        <label for="kamar" class="mr-2">Kamar:</label>
                        <select name="kamar" id="kamar" class="form-control" style="min-width: 150px;">
                            <option value="">Semua Kamar</option>
                            <?php foreach ($kamar_list as $kamar): ?>
                                <option value="<?php echo htmlspecialchars($kamar['kamar']); ?>" <?php echo ($filter_kamar == $kamar['kamar']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kamar['kamar']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="bulan" class="mr-2">Bulan:</label>
                        <select name="bulan" id="bulan" class="form-control" style="min-width: 150px;">
                            <option value="">Semua Bulan</option>
                            <?php foreach ($bulan_list as $bulan): ?>
                                <option value="<?php echo $bulan; ?>" <?php echo ($filter_bulan == $bulan) ? 'selected' : ''; ?>>
                                    <?php echo $bulan; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="tahun" class="mr-2">Tahun:</label>
                        <select name="tahun" id="tahun" class="form-control" style="min-width: 100px;">
                            <option value="">Semua Tahun</option>
                            <?php foreach ($tahun_list as $tahun): ?>
                                <option value="<?php echo $tahun; ?>" <?php echo ($filter_tahun == $tahun) ? 'selected' : ''; ?>>
                                    <?php echo $tahun; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success mb-2 mr-2"><i class="fas fa-search"></i> Terapkan</button>
                    <a href="index.php" class="btn btn-secondary mb-2"><i class="fas fa-sync"></i> Reset</a>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Rapot Tersimpan</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th>Nama Santri</th>
                            <th width="10%">Kamar</th>
                            <th width="15%">Periode</th>
                            <th width="20%" class="d-none d-md-table-cell">Dicatat Oleh</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rapot_list)): ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <?php if (!empty($filter_kamar) || !empty($filter_bulan) || !empty($filter_tahun)): ?>
                                        Data rapot tidak ditemukan dengan filter yang dipilih.
                                    <?php else: ?>
                                        Belum ada data rapot yang dibuat.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rapot_list as $index => $rapot): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($rapot['nama_santri'] ?? 'Santri Dihapus'); ?></td>
                                    <td><?php echo htmlspecialchars($rapot['kamar_santri'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($rapot['bulan']) . ' ' . $rapot['tahun']; ?></td>
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($rapot['nama_musyrif'] ?? 'User Dihapus'); ?></td>
                                    
                                    <td class="text-nowrap">
                                        
                                        <?php if (has_permission('rapot_view')): ?>
                                            <a href="view.php?id=<?php echo $rapot['id']; ?>" class="btn btn-success btn-sm mr-1" data-toggle="tooltip" title="View Rapot" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (has_permission('rapot_cetak')): ?>
                                            <a href="generate_pdf.php?id=<?php echo $rapot['id']; ?>" class="btn btn-info btn-sm mr-1" data-toggle="tooltip" title="Cetak PDF" target="_blank">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (has_permission('rapot_delete')): ?>
                                            <a href="delete.php?id=<?php echo $rapot['id']; ?>" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus Rapot" onclick="return confirm('Yakin mau hapus rapot ini? Data yang dihapus tidak bisa kembali.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})
</script>

<?php
// 7. Panggil Footer
require_once __DIR__ . '/../footer.php';
?>