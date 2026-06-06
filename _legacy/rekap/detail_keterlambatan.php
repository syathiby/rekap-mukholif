<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_keterlambatan');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php';

// Ambil parameter dari URL
$santri_id = $_GET['id'] ?? null;
if (!$santri_id) {
    die("? Santri tidak ditemukan");
}
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date']   ?? null;

// Ambil data santri
$stmt_santri = $conn->prepare("SELECT * FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) {
    die("? Data santri tidak ada");
}

// Hitung Poin Bersih Dinamis
$start_dt_time = $start_date ? $start_date . ' 00:00:00' : '1970-01-01 00:00:00';
$end_dt_time   = $end_date   ? $end_date . ' 23:59:59' : '2099-12-31 23:59:59';
$sql_pb = "
    SELECT 
        (SELECT COALESCE(SUM(jp.poin), 0) FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND p.tanggal BETWEEN ? AND ?) -
        (SELECT COALESCE(SUM(jr.poin_reward), 0) FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id WHERE dr.santri_id = ? AND dr.tanggal BETWEEN ? AND ?) 
    AS poin_bersih
";
$stmt_pb = $conn->prepare($sql_pb);
$stmt_pb->bind_param("ississ", $santri_id, $start_dt_time, $end_dt_time, $santri_id, $start_dt_time, $end_dt_time);
$stmt_pb->execute();
$poin_bersih_dinamis = (int)$stmt_pb->get_result()->fetch_assoc()['poin_bersih'];
$poin_bersih_display = $poin_bersih_dinamis < 0 ? 0 : $poin_bersih_dinamis;

// ? REVISI: Fungsi baru untuk mendeteksi kategori, ikon, dan warnanya
function getDetailKategoriWaktu($datetime, $nama_pelanggaran)
{
    // Default
    $kategori = 'Lainnya';
    
    // Prioritaskan deteksi 'KBM'
    if (stripos($nama_pelanggaran, 'KBM') !== false) {
        $kategori = 'KBM';
    } else {
        // Deteksi berdasarkan jam
        $time = date('H:i:s', strtotime($datetime));
        if ($time >= '03:30:00' && $time <= '05:30:00') $kategori = 'Subuh';
        if ($time >= '11:30:00' && $time <= '13:00:00') $kategori = 'Dzuhur';
        if ($time >= '14:45:00' && $time <= '16:00:00') $kategori = 'Ashar';
        if ($time >= '17:30:00' && $time <= '18:45:00') $kategori = 'Maghrib';
        if ($time >= '18:50:00' && $time <= '20:30:00') $kategori = 'Isya';
    }

    // Siapkan detail (warna & path SVG) untuk setiap kategori
    $details = [
        'Lainnya' => ['color' => '#9ca3af', 'path' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
        'KBM'     => ['color' => '#3b82f6', 'path' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>'],
        'Subuh'   => ['color' => '#f59e0b', 'path' => '<path d="M12 2v8"></path><path d="m4.93 10.93 1.41 1.41"></path><path d="M2 18h2"></path><path d="M20 18h2"></path><path d="m17.66 12.34 1.41-1.41"></path><path d="M22 22H2"></path><path d="m16 5-4-4-4 4"></path>'],
        'Dzuhur'  => ['color' => '#0ea5e9', 'path' => '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>'],
        'Ashar'   => ['color' => '#f97316', 'path' => '<path d="M12 10V2"></path><path d="m4.93 10.93 1.41 1.41"></path><path d="M2 18h2"></path><path d="M20 18h2"></path><path d="m17.66 12.34 1.41-1.41"></path><path d="M22 22H2"></path><path d="m16 15-4 4-4-4"></path>'],
        'Maghrib' => ['color' => '#8b5cf6', 'path' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path><path d="M19 3v4h-4"></path>'],
        'Isya'    => ['color' => '#6366f1', 'path' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>']
    ];
    
    $color = $details[$kategori]['color'];
    $path  = $details[$kategori]['path'];

    // Gabungkan jadi satu string SVG lengkap dengan warna dinamis
    $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';

    return ['nama' => $kategori, 'icon' => $icon];
}


// Ambil histori pelanggaran
$sql_pelanggaran = "
    SELECT p.tanggal, j.nama_pelanggaran, j.poin
    FROM pelanggaran p
    INNER JOIN jenis_pelanggaran j ON p.jenis_pelanggaran_id = j.id
    WHERE p.santri_id = ? 
      AND p.jenis_pelanggaran_id IN (1, 2)
";
$params = [$santri_id];
$types = 'i';

if ($start_date && $end_date) {
    $sql_pelanggaran .= " AND DATE(p.tanggal) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}
$sql_pelanggaran .= " ORDER BY p.tanggal DESC";

$stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
$stmt_pelanggaran->bind_param($types, ...$params);
$stmt_pelanggaran->execute();
$q_pelanggaran = $stmt_pelanggaran->get_result();

?>

<style>
    :root {
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --secondary: #64748b;
        --light-bg: #f8fafc;
        --card-bg: #ffffff;
        --border-color: #e2e8f0;
        --table-border-color: #f1f5f9;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --text-danger: #dc2626;
        --card-header-bg: #f8fafc;
    }

    body {
        background-color: var(--light-bg);
        font-family: 'Poppins', sans-serif;
        color: var(--text-dark);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        font-weight: 700;
        font-size: 1.75rem;
        margin: 0;
    }

    .btn-back {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-dark);
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-back:hover {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .summary-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        padding: 1.5rem;
    }

    .santri-name {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .santri-info {
        color: var(--text-light);
        margin-bottom: 1.5rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
    }

    .stat-item .stat-number {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--primary-dark);
        line-height: 1;
    }

    .stat-item .stat-label {
        color: var(--text-light);
        font-size: 0.875rem;
    }

    .filters-display {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
    }

    .filters-display .form-select {
        border-radius: 0.5rem;
    }

    .detail-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        overflow: hidden; 
    }

    .detail-card-header {
        padding: 1rem 1.5rem;
        background-color: var(--card-header-bg);
        border-bottom: 1px solid var(--border-color);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .table {
        margin-bottom: 0;
    }

    .table th, .table td {
        vertical-align: middle;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--table-border-color);
    }

    .table thead th {
        color: var(--text-light);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom-width: 2px;
        border-color: var(--border-color);
        background-color: var(--card-header-bg);
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .point-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-danger);
    }

    .icon-container {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background-color: var(--light-bg);
        border-radius: 50%;
        margin-right: 0.75rem;
    }
</style>

<?php
// Tentukan array data agar kita bisa ambil total
$detail_list = $q_pelanggaran->fetch_all(MYSQLI_ASSOC);
$total_pelanggaran = count($detail_list);
$total_poin = array_sum(array_column($detail_list, 'poin'));
?>

<div class="container py-4 py-lg-5">
    
    <div class="page-header">
        <div>
            <h1 class="page-title">Detail Keterlambatan</h1>
            <p class="text-muted mb-0">Rincian lengkap pelanggaran keterlambatan yang tercatat.</p>
        </div>
        <a href="keterlambatan.php?start_date=<?= htmlspecialchars($start_date ?? '') ?>&end_date=<?= htmlspecialchars($end_date ?? '') ?>" class="btn btn-back">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Rekap
        </a>
    </div>

    <div class="row g-4">
        <!-- KIRI: SUMMARY CARD & FILTER -->
        <div class="col-lg-4">
            <div class="summary-card">
                <h2 class="santri-name"><?= htmlspecialchars($santri['nama']) ?></h2>
                <p class="santri-info">Kelas: <strong><?= htmlspecialchars($santri['kelas']) ?></strong> | Kamar: <strong><?= htmlspecialchars($santri['kamar']) ?></strong></p>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_pelanggaran ?></div>
                        <div class="stat-label">Total Keterlambatan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_poin ?></div>
                        <div class="stat-label">Total Poin</div>
                    </div>
                    <div class="stat-item" style="grid-column: 1 / -1; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                        <div class="stat-number <?= $poin_bersih_display > 0 ? 'text-danger' : 'text-success' ?>"><?= $poin_bersih_display ?></div>
                        <div class="stat-label">Poin Bersih Periode</div>
                    </div>
                </div>
            </div>
            
            <div class="filters-display mt-3">
                <label for="filter-waktu" class="form-label fw-bold"><i class="fas fa-filter me-2"></i>Filter Waktu:</label>
                <select id="filter-waktu" class="form-select">
                    <option value="semua">Semua Waktu</option>
                    <option value="KBM">KBM</option>
                    <option value="Subuh">Sholat Subuh</option>
                    <option value="Dzuhur">Sholat Dzuhur</option>
                    <option value="Ashar">Sholat Ashar</option>
                    <option value="Maghrib">Sholat Maghrib</option>
                    <option value="Isya">Sholat Isya</option>
                    <option value="Lainnya">Di Luar Jadwal</option>
                </select>
            </div>
        </div>

        <!-- KANAN: TABEL RINCIAN -->
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="detail-card-header">
                    <i class="fas fa-clock me-2"></i>Rincian Keterlambatan
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th>Tanggal</th>
                                <th>Nama Pelanggaran</th>
                                <th class="text-center">Poin</th>
                            </tr>
                        </thead>
                        <tbody id="list-pelanggaran">
                            <?php if (empty($detail_list)): ?>
                                <tr><td colspan="4" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Alhamdulillah, tidak ada riwayat keterlambatan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($detail_list as $index => $row): 
                                    $detail_kategori = getDetailKategoriWaktu($row['tanggal'], $row['nama_pelanggaran']);
                                ?>
                                <tr class="pelanggaran-item" data-kategori="<?= htmlspecialchars($detail_kategori['nama']) ?>">
                                    <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-500"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['tanggal'])) ?> WIB</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="icon-container">
                                                <?= $detail_kategori['icon'] ?>
                                            </div>
                                            <div>
                                                <div><?= htmlspecialchars($row['nama_pelanggaran']) ?></div>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary mt-1"><?= htmlspecialchars($detail_kategori['nama']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="point-value"><?= $row['poin'] ?></span>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('filter-waktu');
    const listContainer = document.getElementById('list-pelanggaran');
    const allItems = listContainer.querySelectorAll('.pelanggaran-item');

    filterSelect.addEventListener('change', function() {
        const selectedCategory = this.value;
        let visibleCount = 0;

        allItems.forEach(item => {
            if (selectedCategory === 'semua' || item.dataset.kategori === selectedCategory) {
                item.style.display = 'table-row';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Menampilkan pesan jika tidak ada hasil setelah filter
        if (visibleCount === 0 && allItems.length > 0) {
             if (!listContainer.querySelector('.temp-no-data')) {
                const tempRow = document.createElement('tr');
                tempRow.className = 'temp-no-data';
                tempRow.innerHTML = `<td colspan="4" class="text-center p-5 text-muted"><i class="fas fa-search fa-3x mb-3"></i><br>Tidak ada pelanggaran untuk kategori waktu yang dipilih.</td>`;
                listContainer.appendChild(tempRow);
             }
        } else {
            const tempMessage = listContainer.querySelector('.temp-no-data');
            if(tempMessage) {
                tempMessage.remove();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
