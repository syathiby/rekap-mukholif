<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

// File ini HANYA boleh diakses via POST request dari form
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
        header('Location: create.php'); // Jika error, kembali ke form create
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

        // STEP 3: Snapshot data pelanggaran
        $sql_pelanggaran_snapshot = "INSERT INTO arsip_data_pelanggaran (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_id, jenis_pelanggaran_nama, bagian, poin, tanggal, tipe) SELECT ?, v.santri_id, v.santri_nama, v.santri_kelas, v.santri_kamar, v.jenis_pelanggaran_id, v.jenis_pelanggaran_nama, v.bagian, v.poin, v.tanggal, v.tipe FROM (SELECT p.tanggal, s.id AS santri_id, s.nama AS santri_nama, s.kelas AS santri_kelas, s.kamar AS santri_kamar, jp.id AS jenis_pelanggaran_id, jp.nama_pelanggaran AS jenis_pelanggaran_nama, jp.bagian, jp.poin, 'Umum' AS tipe FROM pelanggaran p JOIN santri s ON p.santri_id = s.id JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? UNION ALL SELECT pk.tanggal, NULL AS santri_id, 'Pelanggaran Kamar' AS santri_nama, NULL AS santri_kelas, pk.kamar AS santri_kamar, NULL AS jenis_pelanggaran_id, 'Kebersihan Kamar' AS jenis_pelanggaran_nama, 'Kebersihan' AS bagian, 0 AS poin, 'Kebersihan' AS tipe FROM pelanggaran_kebersihan pk WHERE DATE(pk.tanggal) BETWEEN ? AND ?) AS v";
        $stmt_pelanggaran_snapshot = $conn->prepare($sql_pelanggaran_snapshot);
        $stmt_pelanggaran_snapshot->bind_param('issss', $arsip_id, $tgl_mulai, $tgl_selesai, $tgl_mulai, $tgl_selesai);
        $stmt_pelanggaran_snapshot->execute();
        $stmt_pelanggaran_snapshot->close();

        $conn->commit();
        $_SESSION['success_message'] = 'Arsip berhasil dibuat!';
        header('Location: view.php?id=' . $arsip_id);
        exit;

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['error_message'] = 'Gagal membuat arsip: ' . $exception->getMessage();
        header('Location: create.php'); // Jika error, kembali ke form create
        exit;
    }
}

// === PROSES PENGHAPUSAN ARSIP (DARI INDEX.PHP) ===
if ($action === 'delete') {
    guard('arsip_delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) die('ID tidak valid.');
    
    $conn->begin_transaction();
    try {
        $stmt_delete_santri = $conn->prepare("DELETE FROM arsip_data_santri WHERE arsip_id = ?");
        $stmt_delete_santri->bind_param('i', $id);
        $stmt_delete_santri->execute();
        $stmt_delete_santri->close();
        
        $stmt_delete_pelanggaran = $conn->prepare("DELETE FROM arsip_data_pelanggaran WHERE arsip_id = ?");
        $stmt_delete_pelanggaran->bind_param('i', $id);
        $stmt_delete_pelanggaran->execute();
        $stmt_delete_pelanggaran->close();
        
        $stmt_delete_arsip = $conn->prepare("DELETE FROM arsip WHERE id = ?");
        $stmt_delete_arsip->bind_param('i', $id);
        $stmt_delete_arsip->execute();
        $stmt_delete_arsip->close();
        
        $conn->commit();
        $_SESSION['success_message'] = 'Arsip berhasil dihapus!';
        header('Location: index.php');
        exit;

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['error_message'] = 'Gagal menghapus arsip: ' . $exception->getMessage();
        header('Location: index.php');
        exit;
    }
}

// Jika action tidak dikenali
header('Location: index.php');
exit;