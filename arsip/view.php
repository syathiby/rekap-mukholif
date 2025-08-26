<?php
include '../db.php';
include '../header.php';

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('⚠ Arsip tidak ditemukan');

// Ambil meta arsip
$meta_q = mysqli_query($conn, "SELECT * FROM arsip WHERE id = $arsip_id");
$meta = mysqli_fetch_assoc($meta_q);
if (!$meta) die('⚠ Arsip tidak ditemukan');

// Filter tanggal dalam periode arsip
$sub_mulai   = $_GET['mulai']  ?? $meta['tanggal_mulai'];
$sub_selesai = $_GET['selesai'] ?? $meta['tanggal_selesai'];

if ($sub_mulai < $meta['tanggal_mulai']) $sub_mulai = $meta['tanggal_mulai'];
if ($sub_selesai > $meta['tanggal_selesai']) $sub_selesai = $meta['tanggal_selesai'];
if ($sub_mulai > $sub_selesai) { 
    $sub_mulai = $meta['tanggal_mulai']; 
    $sub_selesai = $meta['tanggal_selesai']; 
}
?>

<div class="container my-4">

    <!-- 🔹 Navbar Arsip -->
    <ul class="nav nav-pills justify-content-center mb-4 shadow-sm p-2 bg-light rounded">
        <li class="nav-item"><a class="nav-link" href="#snapshot">📑 Snapshot</a></li>
        <li class="nav-item"><a class="nav-link" href="#pelanggaran">🚨 Pelanggaran</a></li>
        <li class="nav-item"><a class="nav-link" href="#rekapSantri">📊 Rekap Santri</a></li>
        <li class="nav-item"><a class="nav-link" href="#kebersihan">🧹 Kebersihan</a></li>
        <li class="nav-item"><a class="nav-link" href="#rekapKamar">📊 Rekap Kamar</a></li>
    </ul>

    <!-- Info Arsip -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="mb-1">📦 Arsip: <?= htmlspecialchars($meta['judul']); ?></h2>
            <p class="text-muted mb-1">
                Periode: 
                <span class="badge bg-success"><?= $meta['tanggal_mulai']; ?></span>
                s/d 
                <span class="badge bg-success"><?= $meta['tanggal_selesai']; ?></span>
            </p>
            <p class="text-muted">🕒 Dibuat pada: <?= $meta['dibuat_pada']; ?></p>
        </div>
    </div>

    <!-- Filter tanggal -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <input type="hidden" name="id" value="<?= $arsip_id; ?>">
                <div class="col-auto">
                    <label class="form-label">Mulai</label>
                    <input type="date" class="form-control" name="mulai" value="<?= $sub_mulai; ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label">Selesai</label>
                    <input type="date" class="form-control" name="selesai" value="<?= $sub_selesai; ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">🔍 Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ✅ Data Santri Snapshot -->
    <div id="snapshot" class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">📑 Data Santri (Snapshot)</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Kamar</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sqlS = "SELECT nama, kelas, kamar FROM arsip_santri WHERE arsip_id = $arsip_id ORDER BY nama ASC";
                $resS = mysqli_query($conn, $sqlS);
                $no = 1;
                while ($row = mysqli_fetch_assoc($resS)):
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($row['kamar']); ?></span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🚨 Pelanggaran Santri -->
    <div id="pelanggaran" class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white">🚨 Pelanggaran Santri</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Santri</th>
                        <th>Kelas</th>
                        <th>Kamar</th>
                        <th>Jenis Pelanggaran</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sqlP = "
                    SELECT p.tanggal, s.nama, s.kelas, s.kamar, jp.nama_pelanggaran
                    FROM arsip_pelanggaran ap
                    JOIN pelanggaran p ON ap.pelanggaran_id = p.id
                    JOIN santri s ON p.santri_id = s.id
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                    WHERE ap.arsip_id = $arsip_id
                      AND p.tanggal BETWEEN '$sub_mulai 00:00:00' AND '$sub_selesai 23:59:59'
                    ORDER BY p.tanggal ASC";
                $resP = mysqli_query($conn, $sqlP);
                $no = 1;
                while ($row = mysqli_fetch_assoc($resP)):
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= $row['tanggal']; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($row['kamar']); ?></span></td>
                        <td><span class="badge bg-danger text-light"><?= htmlspecialchars($row['nama_pelanggaran']); ?></span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📊 REKAP Pelanggaran Santri -->
    <div id="rekapSantri" class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">📊 Rekap Pelanggaran Santri</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Kamar</th>
                        <th>Jumlah Pelanggaran</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sqlRekapSantri = "
                    SELECT s.nama, s.kelas, s.kamar, COUNT(*) AS total
                    FROM arsip_pelanggaran ap
                    JOIN pelanggaran p ON ap.pelanggaran_id = p.id
                    JOIN santri s ON p.santri_id = s.id
                    WHERE ap.arsip_id = $arsip_id
                      AND p.tanggal BETWEEN '$sub_mulai 00:00:00' AND '$sub_selesai 23:59:59'
                    GROUP BY s.id, s.nama, s.kelas, s.kamar
                    ORDER BY total DESC";
                $resRekapS = mysqli_query($conn, $sqlRekapSantri);
                $no = 1;
                while ($row = mysqli_fetch_assoc($resRekapS)):
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><?= htmlspecialchars($row['kelas']); ?></td>
                        <td><?= htmlspecialchars($row['kamar']); ?></td>
                        <td><span class="badge bg-dark"><?= $row['total']; ?></span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🧹 Pelanggaran Kebersihan -->
    <div id="kebersihan" class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">🧹 Pelanggaran Kebersihan Kamar</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Kamar</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sqlK = "
                    SELECT k.tanggal, k.kamar, k.catatan
                    FROM arsip_pelanggaran_kebersihan apk
                    JOIN pelanggaran_kebersihan k ON apk.kebersihan_id = k.id
                    WHERE apk.arsip_id = $arsip_id
                      AND k.tanggal BETWEEN '$sub_mulai 00:00:00' AND '$sub_selesai 23:59:59'
                    ORDER BY k.tanggal ASC";
                $resK = mysqli_query($conn, $sqlK);
                $no = 1;
                while ($row = mysqli_fetch_assoc($resK)):
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= $row['tanggal']; ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kamar']); ?></span></td>
                        <td><?= htmlspecialchars($row['catatan']); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📊 REKAP Kebersihan -->
    <div id="rekapKamar" class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">📊 Rekap Kebersihan per Kamar</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Kamar</th>
                        <th>Total Pelanggaran</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sqlRekapKamar = "
                    SELECT k.kamar, COUNT(*) AS total
                    FROM arsip_pelanggaran_kebersihan apk
                    JOIN pelanggaran_kebersihan k ON apk.kebersihan_id = k.id
                    WHERE apk.arsip_id = $arsip_id
                      AND k.tanggal BETWEEN '$sub_mulai 00:00:00' AND '$sub_selesai 23:59:59'
                    GROUP BY k.kamar
                    ORDER BY total DESC";
                $resRekapK = mysqli_query($conn, $sqlRekapKamar);
                $no = 1;
                while ($row = mysqli_fetch_assoc($resRekapK)):
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['kamar']); ?></td>
                        <td><span class="badge bg-dark"><?= $row['total']; ?></span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>