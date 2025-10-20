<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
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
// === PROSES UPDATE DATA (DENGAN SINKRONISASI POIN)
// =======================================================
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $nama_pelanggaran = ucfirst(trim($_POST['nama_pelanggaran']));
    $bagian = trim($_POST['bagian']);
    $poin_baru = (int)$_POST['poin'];
    $kategori = trim($_POST['kategori']);

    // Validasi input dulu
    if (!in_array($bagian, $allowed_bagian) || !in_array($kategori, $allowed_kategori) || empty($nama_pelanggaran) || $poin_baru < 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data yang dimasukkan tidak valid.'];
        header("Location: edit.php?id=" . $id);
        exit();
    }
    
    // Mulai 'mode aman' dengan transaksi
    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil dulu poin LAMA dari database sebelum diubah
        $stmt_get_old_poin = mysqli_prepare($conn, "SELECT poin FROM jenis_pelanggaran WHERE id = ?");
        mysqli_stmt_bind_param($stmt_get_old_poin, "i", $id);
        mysqli_stmt_execute($stmt_get_old_poin);
        $result_old_poin = mysqli_stmt_get_result($stmt_get_old_poin);
        $old_data = mysqli_fetch_assoc($result_old_poin);
        
        if (!$old_data) {
            throw new Exception("Jenis pelanggaran tidak ditemukan.");
        }
        $poin_lama = (int)$old_data['poin'];
        mysqli_stmt_close($stmt_get_old_poin);

        // 2. Update data di tabel 'jenis_pelanggaran'
        $query_update_jp = "UPDATE jenis_pelanggaran SET nama_pelanggaran = ?, bagian = ?, poin = ?, kategori = ? WHERE id = ?";
        $stmt_update_jp = mysqli_prepare($conn, $query_update_jp);
        mysqli_stmt_bind_param($stmt_update_jp, "ssisi", $nama_pelanggaran, $bagian, $poin_baru, $kategori, $id);
        
        if (!mysqli_stmt_execute($stmt_update_jp)) {
            throw new Exception(mysqli_stmt_error($stmt_update_jp));
        }
        mysqli_stmt_close($stmt_update_jp);

        // 3. Hitung selisih poin & lakukan sinkronisasi JIKA poinnya berubah
        if ($poin_baru !== $poin_lama) {
            $selisih_poin = $poin_baru - $poin_lama;

            // Query canggih buat update poin semua santri yang relevan sekaligus
            $query_sync = "
                UPDATE santri s
                JOIN (
                    SELECT santri_id, COUNT(*) AS jumlah_pelanggaran
                    FROM pelanggaran
                    WHERE jenis_pelanggaran_id = ?
                    GROUP BY santri_id
                ) p ON s.id = p.santri_id
                SET s.poin_aktif = s.poin_aktif + (? * p.jumlah_pelanggaran)
            ";
            
            $stmt_sync = mysqli_prepare($conn, $query_sync);
            mysqli_stmt_bind_param($stmt_sync, "ii", $id, $selisih_poin);
            
            if (!mysqli_stmt_execute($stmt_sync)) {
                throw new Exception(mysqli_stmt_error($stmt_sync));
            }
            mysqli_stmt_close($stmt_sync);
        }

        // 4. Kalau semua aman, 'simpan permanen' perubahannya
        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggaran berhasil diupdate dan poin santri telah disinkronkan.'];

    } catch (Exception $e) {
        // 5. Kalau ada 1 aja yang gagal, batalkan SEMUA perubahan
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal total! Terjadi kesalahan: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit();
}

// =======================================================
// === ✅ PROSES BULK UPDATE DATA (INI BLOK BARU)
// =======================================================
if (isset($_POST['bulk_update'])) {
    
    // Ambil semua data array dari form
    $ids = $_POST['ids'] ?? [];
    $nama_array = $_POST['nama_pelanggaran'] ?? [];
    $bagian_array = $_POST['bagian'] ?? [];
    $poin_array = $_POST['poin'] ?? [];
    $kategori_array = $_POST['kategori'] ?? [];

    if (empty($ids)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tidak ada data untuk diproses.'];
        header("Location: index.php");
        exit();
    }

    // Mulai 'mode aman' dengan transaksi
    mysqli_begin_transaction($conn);
    
    try {
        $updated_count = 0;
        
        // Siapin kueri di luar loop biar kenceng
        $stmt_get_old = mysqli_prepare($conn, "SELECT poin FROM jenis_pelanggaran WHERE id = ?");
        $stmt_update_jp = mysqli_prepare($conn, "UPDATE jenis_pelanggaran SET nama_pelanggaran = ?, bagian = ?, poin = ?, kategori = ? WHERE id = ?");
        $stmt_sync = mysqli_prepare($conn, "
            UPDATE santri s
            JOIN (
                SELECT santri_id, COUNT(*) AS jumlah_pelanggaran
                FROM pelanggaran
                WHERE jenis_pelanggaran_id = ?
                GROUP BY santri_id
            ) p ON s.id = p.santri_id
            SET s.poin_aktif = s.poin_aktif + (? * p.jumlah_pelanggaran)
        ");

        // Loop sebanyak ID yang dikirim
        foreach ($ids as $id) {
            // Ambil data spesifik untuk ID ini
            $id_int = (int)$id; // Pastikan integer
            $nama = ucfirst(trim($nama_array[$id_int] ?? ''));
            $bagian = trim($bagian_array[$id_int] ?? '');
            $poin_baru = (int)($poin_array[$id_int] ?? 0);
            $kategori = trim($kategori_array[$id_int] ?? '');

            // Validasi (penting!)
            if (empty($nama) || !in_array($bagian, $allowed_bagian) || $poin_baru < 0 || !in_array($kategori, $allowed_kategori)) {
                throw new Exception("Data tidak valid untuk ID $id_int. Nama: '$nama', Bagian: '$bagian', Kategori: '$kategori'");
            }

            // 1. Ambil poin LAMA
            mysqli_stmt_bind_param($stmt_get_old, "i", $id_int);
            mysqli_stmt_execute($stmt_get_old);
            $result_old = mysqli_stmt_get_result($stmt_get_old);
            $old_data = mysqli_fetch_assoc($result_old);
            
            if (!$old_data) continue; // Kalo ID-nya aneh, lewatin aja
            $poin_lama = (int)$old_data['poin'];

            // 2. Update tabel jenis_pelanggaran
            mysqli_stmt_bind_param($stmt_update_jp, "ssisi", $nama, $bagian, $poin_baru, $kategori, $id_int);
            if (!mysqli_stmt_execute($stmt_update_jp)) {
                throw new Exception("Gagal update jenis pelanggaran ID $id_int: " . mysqli_stmt_error($stmt_update_jp));
            }

            // 3. Sinkronisasi poin santri HANYA JIKA poinnya berubah
            if ($poin_baru !== $poin_lama) {
                $selisih_poin = $poin_baru - $poin_lama;
                
                mysqli_stmt_bind_param($stmt_sync, "ii", $id_int, $selisih_poin);
                if (!mysqli_stmt_execute($stmt_sync)) {
                    // Jangan langsung throw error, mungkin aja ngga ada santri yg ngelanggar (itu bukan error)
                    if(mysqli_stmt_errno($stmt_sync) != 0) {
                         throw new Exception("Gagal sinkronisasi poin untuk ID $id_int: " . mysqli_stmt_error($stmt_sync));
                    }
                }
            }
            
            $updated_count++;
        }

        // Tutup semua kueri yang udah disiapin
        mysqli_stmt_close($stmt_get_old);
        mysqli_stmt_close($stmt_update_jp);
        mysqli_stmt_close($stmt_sync);

        // 4. Kalo semua aman, simpan permanen
        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => "Mantap! Berhasil mengupdate $updated_count data dan menyinkronkan poin santri."];

    } catch (Exception $e) {
        // 5. Kalo ada 1 aja yang gagal, batalkan SEMUA
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal total! Proses dibatalkan. Error: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit();
}


// =======================================================
// === PROSES DELETE DATA (DARI KODE LAMA LU)
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