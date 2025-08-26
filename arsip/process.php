<?php
include '../db.php';
session_start();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $judul = trim($_POST['judul'] ?? '');
    $tgl_mulai = $_POST['tgl_mulai'] ?? '';
    $tgl_selesai = $_POST['tgl_selesai'] ?? '';

    // Validasi sederhana
    if ($judul === '' || $tgl_mulai === '' || $tgl_selesai === '') {
        die('⚠ Input tidak lengkap.');
    }
    if ($tgl_mulai > $tgl_selesai) {
        die('⚠ Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
    }

    // 1. Simpan meta arsip
    $stmt = mysqli_prepare($conn, "INSERT INTO arsip (judul, tanggal_mulai, tanggal_selesai) VALUES (?,?,?)");
    mysqli_stmt_bind_param($stmt, 'sss', $judul, $tgl_mulai, $tgl_selesai);
    mysqli_stmt_execute($stmt);
    $arsip_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // 2. Masukkan data pelanggaran ke arsip_pelanggaran
    mysqli_query($conn, "
        INSERT INTO arsip_pelanggaran (arsip_id, pelanggaran_id)
        SELECT $arsip_id, p.id
        FROM pelanggaran p
        WHERE p.tanggal BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59'
    ");

    // 3. Masukkan data kebersihan ke arsip_pelanggaran_kebersihan
    mysqli_query($conn, "
        INSERT INTO arsip_pelanggaran_kebersihan (arsip_id, kebersihan_id)
        SELECT $arsip_id, k.id
        FROM pelanggaran_kebersihan k
        WHERE k.tanggal BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59'
    ");

    // ✅ 4. Copy snapshot data santri ke arsip_santri
    mysqli_query($conn, "
        INSERT INTO arsip_santri (arsip_id, santri_id, nama, kelas, kamar)
        SELECT $arsip_id, s.id, s.nama, s.kelas, s.kamar
        FROM santri s
    ");

    // Redirect ke view arsip
    header('Location: view.php?id=' . $arsip_id);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) die('⚠ ID tidak valid.');

    mysqli_query($conn, "DELETE FROM arsip WHERE id = $id");
    header('Location: index.php');
    exit;
}

die('⚠ Aksi tidak dikenali.');