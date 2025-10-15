<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_per_kamar');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php'; 

// Cek apakah parameter kamar ada di URL
if (!isset($_GET['kamar']) || empty($_GET['kamar'])) {
    // Tampilkan pesan error dengan style yang lebih baik
    echo '<div class="content-wrapper"><div class="container-fluid"><div class="alert alert-danger">Nama kamar tidak ditemukan.</div></div></div>';
    require_once __DIR__ . '/../footer.php';
    exit();
}

$nama_kamar = htmlspecialchars($_GET['kamar']);

// Ambil periode aktif dari pengaturan sebagai default
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row ? $row['nilai'] : '2000-01-01';

// Ambil filter tanggal dari URL, jika tidak ada, gunakan default
$tanggal_awal = $_GET['tanggal_awal'] ?? $periode_aktif;
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date("Y-m-d");

// Ambil jumlah pelanggaran yang belum dieksekusi (SESUAI FILTER TANGGAL)
$sql_belum_eksekusi = "SELECT COUNT(pk.id) AS jumlah_belum_eksekusi
                       FROM pelanggaran_kebersihan pk
                       LEFT JOIN eksekusi_kebersihan ek ON pk.id = ek.pelanggaran_id
                       WHERE pk.kamar = ? AND ek.id IS NULL AND DATE(pk.tanggal) BETWEEN ? AND ?";
$stmt_belum_eksekusi = $conn->prepare($sql_belum_eksekusi);
$stmt_belum_eksekusi->bind_param("sss", $nama_kamar, $tanggal_awal, $tanggal_akhir);
$stmt_belum_eksekusi->execute();
$result_belum_eksekusi = $stmt_belum_eksekusi->get_result();
$data_belum_eksekusi = $result_belum_eksekusi->fetch_assoc();
$jumlah_belum_eksekusi = $data_belum_eksekusi['jumlah_belum_eksekusi'];
$stmt_belum_eksekusi->close();

// Ambil data pelanggaran kebersihan (SESUAI FILTER TANGGAL)
$sql_pelanggaran = "SELECT id, tanggal, catatan FROM pelanggaran_kebersihan WHERE kamar = ? AND DATE(tanggal) BETWEEN ? AND ? ORDER BY tanggal DESC";
$stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
$stmt_pelanggaran->bind_param("sss", $nama_kamar, $tanggal_awal, $tanggal_akhir);
$stmt_pelanggaran->execute();
$result_pelanggaran = $stmt_pelanggaran->get_result();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pelanggaran Kamar: <?= $nama_kamar ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --danger: #f72585;
            --warning: #f8961e;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            margin: 0;
        }

        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        .header-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Agar responsif */
            gap: 15px; /* Jarak antar item */
        }

        h2 {
            margin: 0;
            color: var(--primary);
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .table-wrapper {
             background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            /* === INI DIA KUNCINYA === */
            overflow-x: auto;
        }
        
        .info-card.filter-info {
            grid-column: 1 / -1; /* Span full width */
            background-color: #eef2ff;
            border-left: 5px solid var(--secondary);
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px; /* <<< INI DIA PERBAIKANNYA */
        }

        .info-card .icon {
            font-size: 2.5rem;
            padding: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .info-card.danger .icon {
            background-color: #ffebee;
            color: #c62828;
        }

        .info-card .content h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #6c757d;
            font-weight: 500;
        }
        .info-card .content p {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .table-wrapper h3 {
            margin-top: 0;
            font-size: 20px;
            color: var(--dark);
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead th {
            background-color: var(--light);
            font-weight: 600;
            padding: 16px 12px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
            white-space: nowrap; /* Biar judul kolom gak turun */
        }
        
        table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap; /* Biar isi tabel gak turun */
        }
        
        table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Kolom catatan boleh wrap biar gak terlalu panjang */
        table th:last-child, table td:last-child {
            white-space: normal; 
            min-width: 300px; /* Lebar minimal biar gak aneh */
        }


        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: #e9ecef;
            color: #495057;
            white-space: nowrap;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .container { padding: 15px; }
            h2 { font-size: 22px; }
            .header-card { flex-direction: column; align-items: flex-start; }
            .info-grid { grid-template-columns: 1fr; }
            .table-wrapper, .info-card { padding: 15px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-card">
        <h2><i class="fas fa-door-open"></i> Detail Kamar <?= $nama_kamar ?></h2>
        <a href="per-kamar.php?tanggal_awal=<?= urlencode($tanggal_awal) ?>&tanggal_akhir=<?= urlencode($tanggal_akhir) ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="info-card filter-info">
        <i class="fas fa-filter" style="color: var(--secondary); font-size: 1.5rem;"></i>
        <div>
            <p style="font-size: 1rem; margin: 0; font-weight: 400;">
                Menampilkan riwayat pelanggaran dari tanggal 
                <strong><?= date('d M Y', strtotime($tanggal_awal)) ?></strong> s/d <strong><?= date('d M Y', strtotime($tanggal_akhir)) ?></strong>.
            </p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card danger">
            <div class="content" style="display: flex; align-items: center; gap: 20px;">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <h3>Pelanggaran Belum Dieksekusi</h3>
                    <p><?= $jumlah_belum_eksekusi ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <h3><i class="fas fa-history"></i> Riwayat Pelanggaran</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">No</th>
                    <th style="width: 30%;">Tanggal & Waktu</th>
                    <th>Catatan Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_pelanggaran->num_rows > 0) : ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $result_pelanggaran->fetch_assoc()) : ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><span class="badge"><i class="far fa-calendar-alt"></i> <?= date('d M Y, H:i', strtotime($row['tanggal'])); ?> WIB</span></td>
                            <td><?= !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : '-'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 20px; white-space: normal;">
                            <i class="fas fa-check-circle" style="color: var(--success); font-size: 2rem; margin-bottom: 10px;"></i><br>
                            Alhamdulillah, tidak ada pelanggaran tercatat pada periode ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>

<?php
$stmt_pelanggaran->close();
$conn->close();
require_once __DIR__ . '/../footer.php';
?>