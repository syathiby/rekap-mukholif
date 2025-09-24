<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('jenis_pelanggaran_create', 'jenis_pelanggaran_edit');

// Definisikan nilai yang diizinkan untuk validasi
$allowed_kategori = ['Ringan', 'Sedang', 'Berat', 'Sangat Berat'];
$allowed_bagian = ['Kesantrian', 'Bahasa', 'Diniyyah', 'Pengabdian', 'Tahfidz'];

// =======================================================
// === LOGIKA UNTUK TAMBAH SATU DATA (FORM BIASA)
// =======================================================
if (isset($_POST['create_single'])) {
    $nama_pelanggaran = ucfirst(trim($_POST['nama_pelanggaran']));
    $bagian = trim($_POST['bagian']);
    $poin = (int)$_POST['poin'];
    $kategori = trim($_POST['kategori']);

    // Validasi data
    if (empty($nama_pelanggaran) || !in_array($bagian, $allowed_bagian) || $poin < 0 || !in_array($kategori, $allowed_kategori)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data yang dimasukkan tidak valid.'];
        header("Location: create.php");
        exit();
    }

    $query = "INSERT INTO jenis_pelanggaran (nama_pelanggaran, bagian, poin, kategori) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssis", $nama_pelanggaran, $bagian, $poin, $kategori);
    
    if (mysqli_stmt_execute($stmt)) {
        // ==> INI NOTIFIKASINYA
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggaran berhasil ditambahkan.'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal menambahkan data: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit();
}

// =======================================================
// === LOGIKA UNTUK TAMBAH BANYAK DATA (BULK)
// =======================================================
if (isset($_POST['create_bulk'])) {
    $bulk_data = trim($_POST['bulk_data']);
    if (empty($bulk_data)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data bulk tidak boleh kosong.'];
        header("Location: create.php");
        exit();
    }

    $lines = preg_split('/\r\n|\r|\n/', $bulk_data);
    $errors = [];
    $success_count = 0;

    $query = "INSERT INTO jenis_pelanggaran (nama_pelanggaran, bagian, poin, kategori) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    mysqli_begin_transaction($conn);

    foreach ($lines as $index => $line) {
        if (empty(trim($line))) continue;
        $line_number = $index + 1;
        $data = str_getcsv($line);

        if (count($data) !== 4) {
            $errors[] = "Baris $line_number: Format tidak sesuai, harus ada 4 kolom.";
            continue;
        }

        list($nama_pelanggaran, $bagian, $poin, $kategori) = $data;

        $nama_pelanggaran_clean = ucfirst(trim($nama_pelanggaran));
        $bagian_standard = ucwords(strtolower(trim($bagian)));
        $kategori_standard = ucwords(strtolower(trim($kategori)));
        $poin_int = (int)trim($poin);
        
        $is_valid = true;
        if (empty($nama_pelanggaran_clean) || !in_array($bagian_standard, $allowed_bagian) || $poin_int < 0 || !in_array($kategori_standard, $allowed_kategori)) {
            $errors[] = "Baris $line_number: Data tidak valid (Nama, Bagian, Poin, atau Kategori).";
            $is_valid = false;
        }
        
        if (!$is_valid) continue;

        mysqli_stmt_bind_param($stmt, "ssis", $nama_pelanggaran_clean, $bagian_standard, $poin_int, $kategori_standard);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        } else {
            $errors[] = "Baris $line_number: Gagal menyimpan ke database: " . mysqli_stmt_error($stmt);
            break; 
        }
    }

    if (empty($errors)) {
        mysqli_commit($conn);
        // ==> INI NOTIFIKASI SUKSES BULK
        $_SESSION['message'] = ['type' => 'success', 'text' => "Mantap! Berhasil menambahkan $success_count data pelanggaran."];
    } else {
        mysqli_rollback($conn);
        // ==> INI NOTIFIKASI GAGAL BULK
        $error_text = "Proses dibatalkan. Ditemukan error:<br><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        $_SESSION['message'] = ['type' => 'danger', 'text' => $error_text];
    }

    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit();
}

// =======================================================
// === PROSES UPDATE DATA
// =======================================================
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nama_pelanggaran = ucfirst(trim($_POST['nama_pelanggaran']));
    $bagian = trim($_POST['bagian']);
    $poin = (int)$_POST['poin'];
    $kategori = trim($_POST['kategori']);

    if (!in_array($bagian, $allowed_bagian) || !in_array($kategori, $allowed_kategori) || empty($nama_pelanggaran) || $poin < 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data yang dimasukkan tidak valid.'];
        header("Location: edit.php?id=" . $id);
        exit();
    }
    
    $query = "UPDATE jenis_pelanggaran SET nama_pelanggaran = ?, bagian = ?, poin = ?, kategori = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssisi", $nama_pelanggaran, $bagian, $poin, $kategori, $id);

    if (mysqli_stmt_execute($stmt)) {
        // ==> INI NOTIFIKASI UPDATE
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggaran berhasil diupdate.'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal mengupdate data: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit();
}

// =======================================================
// === PROSES DELETE DATA (BONUS)
// =======================================================
if (isset($_POST['delete'])) {
    $id = (int)$_POST['id'];
    
    $query = "DELETE FROM jenis_pelanggaran WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        // ==> INI NOTIFIKASI DELETE
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggaran berhasil dihapus.'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal menghapus data: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit();
}


// Jika tidak ada aksi yang cocok, redirect
header("Location: index.php");
exit();
?>