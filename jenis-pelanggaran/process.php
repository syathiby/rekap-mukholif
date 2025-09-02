<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('jenis_pelanggaran_manage');
?>

<?php

$allowed_kategori = ['Ringan', 'Sedang', 'Berat', 'Sangat Berat'];
$allowed_bagian = ['Kesantrian', 'Bahasa', 'Diniyyah', 'Pengabdian'];

// =======================================================
// === LOGIKA UNTUK TAMBAH SATU DATA (FORM BIASA)
// =======================================================
if (isset($_POST['create_single'])) {
    // --- MODIFIKASI KECIL ---
    $nama_pelanggaran = ucfirst(trim($_POST['nama_pelanggaran'])); // Dirapikan di sini
    $bagian = trim($_POST['bagian']);
    $poin = (int)$_POST['poin'];
    $kategori = trim($_POST['kategori']);

    // ... sisa validasi ...
    if (empty($nama_pelanggaran) || empty($bagian) || empty($kategori) || $poin < 0) {
        // ...
    }

    $query = "INSERT INTO jenis_pelanggaran (nama_pelanggaran, bagian, poin, kategori) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssis", $nama_pelanggaran, $bagian, $poin, $kategori);
    
    // ... sisa kode ...
    if (mysqli_stmt_execute($stmt)) { //...
    }
    header("Location: index.php");
    exit();
}

// =======================================================
// === LOGIKA UNTUK TAMBAH BANYAK DATA (BULK)
// =======================================================
if (isset($_POST['create_bulk'])) {
    // ...
    foreach ($lines as $index => $line) {
        // ...
        list($nama_pelanggaran, $bagian, $poin, $kategori) = $data;

        // --- MODIFIKASI KECIL ---
        // Rapikan nama pelanggaran dengan cara yang lebih sopan
        $nama_pelanggaran_clean = ucfirst(trim($nama_pelanggaran));

        // Standarisasi untuk data kategorikal
        $bagian_standard = ucwords(strtolower($bagian));
        $kategori_standard = ucwords(strtolower($kategori));
        
        // ... sisa validasi ...
        if (empty($nama_pelanggaran_clean)) { // Validasi pake data yg sudah bersih
            $errors[] = "Baris $line_number: Nama Pelanggaran tidak boleh kosong.";
            $is_valid = false;
        }
        // ...

        if (!$is_valid) {
            $error_count++;
            continue;
        }

        $poin_int = (int)$poin;
        // Gunakan variabel yang sudah bersih dan distandarisasi
        mysqli_stmt_bind_param($stmt, "ssis", $nama_pelanggaran_clean, $bagian_standard, $poin_int, $kategori_standard);

        // ... sisa kode ...
    }
    // ...
    header("Location: index.php");
    exit();
}

// =======================================================
// === PROSES UPDATE DATA
// =======================================================
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nama_pelanggaran = ucfirst(trim($_POST['nama_pelanggaran'])); // Kita standarisasi juga
    $bagian = trim($_POST['bagian']);
    $poin = (int)$_POST['poin'];
    $kategori = trim($_POST['kategori']);

    // Validasi super penting biar data invalid nggak bisa masuk
    if (!in_array($bagian, $allowed_bagian) || !in_array($kategori, $allowed_kategori) || empty($nama_pelanggaran) || $poin < 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data yang dimasukkan tidak valid.'];
        header("Location: edit.php?id=" . $id); // Kembali ke form edit
        exit();
    }
    
    $query = "UPDATE jenis_pelanggaran SET nama_pelanggaran = ?, bagian = ?, poin = ?, kategori = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    // Ini bagian yang kemarin error, sekarang udah bener urutan dan tipenya: s, s, i, s, i
    mysqli_stmt_bind_param($stmt, "ssisi", $nama_pelanggaran, $bagian, $poin, $kategori, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggaran berhasil diupdate.'];
    } else {
        // Jika gagal, kasih tau errornya apa biar gampang dilacak
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal mengupdate data: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    
    header("Location: index.php");
    exit();
}

// ...
header("Location: index.php");
exit();
?>