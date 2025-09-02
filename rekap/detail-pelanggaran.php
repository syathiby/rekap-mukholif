<?php
require_once __DIR__ . '/../header.php';
guard('rekap_view_santri');

// Ambil parameter dari URL
$santri_id = $_GET['id'] ?? null;
if (!$santri_id) {
    die("❌ Santri tidak ditemukan");
}
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date']   ?? null;

// Ambil data santri
$stmt_santri = $conn->prepare("SELECT * FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) {
    die("❌ Data santri tidak ada");
}

// ✅ REVISI: Fungsi baru untuk mendeteksi kategori, ikon, dan warnanya
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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

:root {
    --bg-color: #f8f9fa;
    --card-bg: #ffffff;
    --primary-color: #4f46e5;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --border-color: #e5e7eb;
    --accent-red: #ef4444;
}
body {
    background-color: var(--bg-color);
    font-family: 'Poppins', sans-serif;
    color: var(--text-dark);
}
.container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 15px;
}

/* ✅ REVISI: Header responsif */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-light);
    font-weight: 500;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-back:hover {
    background-color: #f3f4f6;
    color: var(--text-dark);
}

.santri-info-card {
    background-color: var(--primary-color);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
}
.santri-info-card h3 { margin: 0; font-size: 1.5rem; font-weight: 600; }
.santri-info-card p { margin: 0.25rem 0 0; opacity: 0.8; }

/* ✅ REVISI: Filter Card responsif */
.filter-card {
    background-color: var(--card-bg);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
}
.filter-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-light);
    margin-bottom: 0.5rem;
}
.form-select {
    width: 100%;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: #f9fafb;
    font-family: 'Poppins', sans-serif;
    font-size: 1rem;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

.pelanggaran-list { display: flex; flex-direction: column; gap: 1rem; }
.pelanggaran-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    background-color: var(--card-bg);
    padding: 1.25rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    animation: fadeIn 0.5s ease-out;
}
.pelanggaran-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
}

/* ✅ REVISI: Ikon tanpa background, tapi dari background abu-abu muda */
.item-icon {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    display: grid;
    place-items: center;
    background-color: #f3f4f6; /* Latar belakang abu-abu muda */
    border-radius: 50%;
}
.item-details { flex-grow: 1; }
.item-details h4 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-dark); }
.item-details p { margin: 0.25rem 0 0; font-size: 0.9rem; color: var(--text-light); }
.item-poin { font-size: 1.25rem; font-weight: 700; color: var(--accent-red); }

.no-data {
    text-align: center;
    padding: 3rem;
    background-color: var(--card-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}
.no-data .icon { font-size: 3rem; margin-bottom: 1rem; }
.no-data p { font-size: 1.1rem; color: var(--text-light); }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Media query untuk layar lebih besar */
@media (min-width: 576px) {
    .page-title { font-size: 2rem; }
    .filter-card {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .filter-label { margin-bottom: 0; }
}
</style>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">Detail Pelanggaran</h1>
        <a href="santri-pelanggar.php?start_date=<?= htmlspecialchars($start_date ?? '') ?>&end_date=<?= htmlspecialchars($end_date ?? '') ?>" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>
            <span>Kembali</span>
        </a>
    </header>

    <div class="santri-info-card">
        <h3><?= htmlspecialchars($santri['nama']) ?></h3>
        <p>Kelas: <?= htmlspecialchars($santri['kelas']) ?> | Kamar: <?= htmlspecialchars($santri['kamar']) ?></p>
    </div>

    <div class="filter-card">
        <label for="filter-waktu" class="filter-label">Filter berdasarkan waktu:</label>
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

    <div class="pelanggaran-list" id="list-pelanggaran">
        <?php
        if ($q_pelanggaran->num_rows === 0) {
            echo "<div class='no-data'>
                    <div class='icon'>🎉</div>
                    <p>Alhamdulillah, tidak ada riwayat<br>pelanggaran sholat atau KBM.</p>
                  </div>";
        } else {
            while ($row = $q_pelanggaran->fetch_assoc()) {
                $detail_kategori = getDetailKategoriWaktu($row['tanggal'], $row['nama_pelanggaran']);
        ?>
            <div class="pelanggaran-item" data-kategori="<?= $detail_kategori['nama'] ?>">
                <div class="item-icon">
                    <?= $detail_kategori['icon'] ?>
                </div>
                <div class="item-details">
                    <h4><?= htmlspecialchars($row['nama_pelanggaran']) ?></h4>
                    <p><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?> &bull; <?= $detail_kategori['nama'] ?></p>
                </div>
                
            </div>
        <?php
            }
        }
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('filter-waktu');
    const listContainer = document.getElementById('list-pelanggaran');
    const allItems = listContainer.querySelectorAll('.pelanggaran-item');
    const noDataMessage = listContainer.querySelector('.no-data');

    // Sembunyikan pesan 'no-data' jika awalnya ada item
    if (noDataMessage && allItems.length > 0) {
        noDataMessage.style.display = 'none';
    }

    filterSelect.addEventListener('change', function() {
        const selectedCategory = this.value;
        let visibleCount = 0;

        allItems.forEach(item => {
            if (selectedCategory === 'semua' || item.dataset.kategori === selectedCategory) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Menampilkan pesan jika tidak ada hasil setelah filter
        if (visibleCount === 0 && allItems.length > 0) {
             if (!listContainer.querySelector('.temp-no-data')) {
                const tempNoData = document.createElement('div');
                tempNoData.className = 'no-data temp-no-data';
                tempNoData.innerHTML = `<div class='icon'>🧐</div><p>Tidak ada pelanggaran untuk<br>kategori waktu yang dipilih.</p>`;
                listContainer.appendChild(tempNoData);
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

<?php require_once __DIR__ . '/../footer.php'; ?>