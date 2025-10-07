<?php
// 1. TAMBAHKAN INI! Wajib ada buat baca session dari process.php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../header.php';
guard('history_manage');

// Ambil filter
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$bagian  = $_GET['bagian'] ?? '';

// --- 2. QUERY KITA BUAT JADI AMAN PAKE PREPARED STATEMENT ---
$params = [];
$types  = '';

$query = "
    SELECT 
        p.id, 
        s.nama AS nama_santri, 
        jp.nama_pelanggaran, 
        jp.poin, 
        jp.bagian,
        p.tanggal
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE DATE(p.tanggal) = ?
";
$params[] = $tanggal;
$types .= 's'; // 's' untuk string

if (!empty($bagian)) {
    $query .= " AND jp.bagian = ?";
    $params[] = $bagian;
    $types .= 's';
}
$query .= " ORDER BY p.tanggal DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Kalo query-nya error, kita matiin aja biar jelas errornya apa
    die("Query Gagal: " . $conn->error);
}

$bagian_result = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran ORDER BY bagian ASC");
?>

<div class="container-history">
    <h2><i class="fas fa-history me-2"></i>Riwayat Pelanggaran</h2>

    <form id="filterForm" method="GET" class="mb-3">
        <label for="tanggal">Tanggal:</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="form-control d-inline-block w-auto mx-2">

        <label for="bagian">Bagian:</label>
        <select name="bagian" id="bagian" class="form-select d-inline-block w-auto mx-2">
            <option value="">Semua Bagian</option>
            <?php while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= ($bagian == $b['bagian']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($b['bagian'])) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>No</th>
                <th>Nama Santri</th>
                <th>Jenis Pelanggaran</th>
                <th>Poin</th>
                <th>Tanggal</th>
                <th>Bagian</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_santri']) ?></td>
                        <td><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                        <td><span class="fw-bold text-danger"><?= htmlspecialchars($row['poin']) ?></span></td>
                        <td><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['bagian'])) ?></td>
                        <td>
                            <form action="process.php" method="POST" onsubmit="return confirmCancel(event, this)">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                                <input type="hidden" name="bagian" value="<?= htmlspecialchars($bagian) ?>">
                                <button type="submit" name="batalkan" class="btn btn-sm btn-danger">
                                    <i class="fas fa-times me-1"></i>Batalkan
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted fst-italic py-4">Tidak ada data pelanggaran pada tanggal ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Javascript tidak berubah, sudah bagus
document.getElementById('tanggal').addEventListener('change', () => {
    document.getElementById('filterForm').submit();
});
document.getElementById('bagian').addEventListener('change', () => {
    document.getElementById('filterForm').submit();
});

function confirmCancel(e, form) {
    e.preventDefault();
    Swal.fire({
        title: 'Batalkan pelanggaran?',
        text: "Poin santri akan dikembalikan dan data akan dihapus!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, batalkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}

// --- 3. LOGIKA ALERT KITA UBAH JADI BACA DARI SESSION ---
<?php if (isset($_SESSION['pesan_sukses'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= $_SESSION['pesan_sukses'] ?>',
    timer: 2000,
    showConfirmButton: false
});
<?php unset($_SESSION['pesan_sukses']); // Langsung hapus session setelah ditampilkan ?>
<?php endif; ?>

<?php if (isset($_SESSION['pesan_error'])): ?>
Swal.fire({
    icon: 'error',
    title: 'Oops... Gagal!',
    text: '<?= $_SESSION['pesan_error'] ?>',
    timer: 3000,
    showConfirmButton: false
});
<?php unset($_SESSION['pesan_error']); // Langsung hapus session setelah ditampilkan ?>
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>