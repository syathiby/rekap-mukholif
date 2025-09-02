<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('arsip_manage');
?>

<?php

$action = $_POST['action'] ?? '';

// ===================================
// === PROSES PEMBUATAN ARSIP BARU ===
// ===================================
if ($action === 'create') {
    $judul = trim($_POST['judul'] ?? '');
    $tgl_mulai = $_POST['tgl_mulai'] ?? '';
    $tgl_selesai = $_POST['tgl_selesai'] ?? '';

    // Validasi
    if (empty($judul) || empty($tgl_mulai) || empty($tgl_selesai) || $tgl_mulai > $tgl_selesai) {
        die('Input tidak lengkap atau periode tidak valid.');
    }

    // Mulai Transaksi Database
    $conn->begin_transaction();

    try {
        // 1. Simpan meta arsip
        $stmt_arsip = $conn->prepare("INSERT INTO arsip (judul, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?)");
        $stmt_arsip->bind_param('sss', $judul, $tgl_mulai, $tgl_selesai);
        $stmt_arsip->execute();
        $arsip_id = $conn->insert_id;
        $stmt_arsip->close();

        // 2. Query sakti: Ambil semua data pelanggaran (umum & kebersihan), gabungkan, lalu masukkan ke tabel arsip
        $sql_snapshot = "
            INSERT INTO arsip_data_pelanggaran 
                (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_id, 
                jenis_pelanggaran_nama, bagian, poin, tanggal, tipe)
            SELECT
                ?, -- arsip_id
                v.santri_id, v.santri_nama, v.santri_kelas, v.santri_kamar, v.jenis_pelanggaran_id,
                v.jenis_pelanggaran_nama, v.bagian, v.poin, v.tanggal, v.tipe
            FROM (
                -- Data dari tabel pelanggaran (umum)
                SELECT 
                    p.tanggal,
                    s.id AS santri_id,
                    s.nama AS santri_nama,
                    s.kelas AS santri_kelas,
                    s.kamar AS santri_kamar,
                    jp.id AS jenis_pelanggaran_id,
                    jp.nama_pelanggaran AS jenis_pelanggaran_nama,
                    jp.bagian,
                    jp.poin,
                    'Umum' AS tipe
                FROM pelanggaran p
                JOIN santri s ON p.santri_id = s.id
                JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?

                UNION ALL

                -- Data dari tabel pelanggaran_kebersihan
                SELECT 
                    pk.tanggal,
                    NULL AS santri_id, -- Kebersihan tidak terikat santri_id
                    'N/A' AS santri_nama,
                    'N/A' AS santri_kelas,
                    pk.kamar AS santri_kamar,
                    3 AS jenis_pelanggaran_id, -- Asumsi ID 3 adalah Kebersihan Kamar
                    'Kebersihan Kamar' AS jenis_pelanggaran_nama,
                    'Kebersihan' AS bagian, -- Asumsi bagian
                    0 AS poin,
                    'Kebersihan' AS tipe
                FROM pelanggaran_kebersihan pk
                WHERE DATE(pk.tanggal) BETWEEN ? AND ?
            ) AS v
        ";
        
        $stmt_snapshot = $conn->prepare($sql_snapshot);
        // bind_param: i = integer (arsip_id), s = string (tanggal)
        $stmt_snapshot->bind_param('issss', $arsip_id, $tgl_mulai, $tgl_selesai, $tgl_mulai, $tgl_selesai);
        $stmt_snapshot->execute();
        $stmt_snapshot->close();

        // Jika semua berhasil, commit transaksi
        $conn->commit();
        
        header('Location: view.php?id=' . $arsip_id);
        exit;

    } catch (mysqli_sql_exception $exception) {
        // Jika ada error, batalkan semua perubahan
        $conn->rollback();
        die('Gagal membuat arsip: ' . $exception->getMessage());
    }
}

// =================================
// === PROSES PENGHAPUSAN ARSIP ===
// =================================
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) die('ID tidak valid.');

    // Cukup hapus dari tabel 'arsip', data di 'arsip_data_pelanggaran' akan ikut terhapus
    // jika kamu set ON DELETE CASCADE di database. Jika tidak, hapus manual dulu.
    
    $conn->begin_transaction();
    try {
        $stmt_delete_data = $conn->prepare("DELETE FROM arsip_data_pelanggaran WHERE arsip_id = ?");
        $stmt_delete_data->bind_param('i', $id);
        $stmt_delete_data->execute();
        $stmt_delete_data->close();
        
        $stmt_delete_arsip = $conn->prepare("DELETE FROM arsip WHERE id = ?");
        $stmt_delete_arsip->bind_param('i', $id);
        $stmt_delete_arsip->execute();
        $stmt_delete_arsip->close();
        
        $conn->commit();

        header('Location: index.php');
        exit;
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        die('Gagal menghapus arsip: ' . $exception->getMessage());
    }
}

die('Aksi tidak dikenali.');