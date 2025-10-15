<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Akses ditolak.');
}

$action = $_POST['action'] ?? '';

// === PROSES PEMBUATAN ARSIP BARU (DARI CREATE.PHP) ===
if ($action === 'create') {
    guard('arsip_create');
    
    $judul = trim($_POST['judul'] ?? '');
    $tgl_mulai = $_POST['tgl_mulai'] ?? '';
    $tgl_selesai = $_POST['tgl_selesai'] ?? '';

    if (empty($judul) || empty($tgl_mulai) || empty($tgl_selesai) || $tgl_mulai > $tgl_selesai) {
        $_SESSION['error_message'] = 'Input tidak lengkap atau periode tidak valid.';
        header('Location: create.php');
        exit;
    }

    $conn->begin_transaction();
    try {
        // STEP 1: Simpan meta arsip
        $stmt_arsip = $conn->prepare("INSERT INTO arsip (judul, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?)");
        $stmt_arsip->bind_param('sss', $judul, $tgl_mulai, $tgl_selesai);
        $stmt_arsip->execute();
        $arsip_id = $conn->insert_id;
        $stmt_arsip->close();

        // STEP 2: Snapshot data santri
        $sql_santri_snapshot = "INSERT INTO arsip_data_santri (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip) SELECT ?, id, nama, kelas, kamar, poin_aktif FROM santri";
        $stmt_santri_snapshot = $conn->prepare($sql_santri_snapshot);
        $stmt_santri_snapshot->bind_param('i', $arsip_id);
        $stmt_santri_snapshot->execute();
        $stmt_santri_snapshot->close();

        // STEP 3: Snapshot data pelanggaran umum
        $sql_pelanggaran_snapshot = "
            INSERT INTO arsip_data_pelanggaran 
                (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_id, jenis_pelanggaran_nama, bagian, poin, tanggal, tipe) 
            SELECT 
                ?, p.santri_id, s.nama, s.kelas, s.kamar, 
                p.jenis_pelanggaran_id, jp.nama_pelanggaran, jp.bagian, jp.poin, p.tanggal, 'Umum' AS tipe 
            FROM pelanggaran p 
            JOIN santri s ON p.santri_id = s.id 
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
            WHERE DATE(p.tanggal) BETWEEN ? AND ?";
        
        $stmt_pelanggaran_snapshot = $conn->prepare($sql_pelanggaran_snapshot);
        $stmt_pelanggaran_snapshot->bind_param('iss', $arsip_id, $tgl_mulai, $tgl_selesai);
        $stmt_pelanggaran_snapshot->execute();
        $stmt_pelanggaran_snapshot->close();

        // STEP 4: Snapshot data pelanggaran kebersihan
        $sql_kebersihan_snapshot = "
            INSERT INTO arsip_data_pelanggaran_kebersihan 
                (arsip_id, kamar, catatan, tanggal, dicatat_oleh_user_id, dicatat_oleh_nama)
            SELECT ?, pk.kamar, pk.catatan, pk.tanggal, pk.dicatat_oleh, u.nama_lengkap
            FROM pelanggaran_kebersihan pk
            LEFT JOIN users u ON pk.dicatat_oleh = u.id
            WHERE DATE(pk.tanggal) BETWEEN ? AND ?";
        
        $stmt_kebersihan_snapshot = $conn->prepare($sql_kebersihan_snapshot);
        $stmt_kebersihan_snapshot->bind_param('iss', $arsip_id, $tgl_mulai, $tgl_selesai);
        $stmt_kebersihan_snapshot->execute();
        $stmt_kebersihan_snapshot->close();

        $conn->commit();
        $_SESSION['success_message'] = 'Arsip berhasil dibuat!';
        header('Location: view.php?id=' . $arsip_id);
        exit;

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['error_message'] = 'Gagal membuat arsip: ' . $exception->getMessage();
        header('Location: create.php');
        exit;
    }
}

// === PROSES PENGHAPUSAN ARSIP (DARI INDEX.PHP) ===
if ($action === 'delete') {
    guard('arsip_delete');

    if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = 'ID Arsip tidak valid.';
        header('Location: index.php');
        exit;
    }
    $arsip_id = (int)$_POST['id'];

    // Cuma butuh ini doang!
    try {
        // Hapus data induknya aja, anak-anaknya bakal ikut kehapus otomatis
        // berkat ON DELETE CASCADE di database lu.
        $stmt = $conn->prepare("DELETE FROM arsip WHERE id = ?");
        $stmt->bind_param('i', $arsip_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = 'Arsip berhasil dihapus permanen!';

    } catch (mysqli_sql_exception $exception) {
        // Nggak perlu transaksi karena cuma 1 query
        $_SESSION['error_message'] = 'Gagal menghapus arsip: ' . $exception->getMessage();
    }
    
    header('Location: index.php');
    exit;
}

// Jika action tidak dikenali, tendang balik
header('Location: index.php');
exit;