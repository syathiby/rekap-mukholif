<?php
require_once __DIR__ . '/../../bootstrap/init.php'; // Load DB & Auth

// --- 1. PROSES TAMBAH BARU ---
if (isset($_POST['add_jenis'])) {
    guard('jenis_reward_create');
    
    $nama = $_POST['nama_reward'];
    $poin = (int) $_POST['poin_reward'];
    $desc = $_POST['deskripsi'];

    $query = "INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sis", $nama, $poin, $desc);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Reward baru berhasil ditambahkan!'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal: ' . mysqli_error($conn)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

// --- 2. PROSES EDIT SATUAN ---
if (isset($_POST['edit_jenis'])) {
    guard('jenis_reward_edit');

    $id   = (int) $_POST['id'];
    $nama = $_POST['nama_reward'];
    $poin = (int) $_POST['poin_reward'];
    $desc = $_POST['deskripsi'];

    $query = "UPDATE jenis_reward SET nama_reward=?, poin_reward=?, deskripsi=? WHERE id=?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sisi", $nama, $poin, $desc, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data reward berhasil diperbarui.'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal update: ' . mysqli_error($conn)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

// --- 3. PROSES BULK EDIT (Edit Rame-rame) ---
if (isset($_POST['bulk_update'])) {
    guard('jenis_reward_edit');

    $ids = $_POST['ids']; // Array ID
    $namas = $_POST['nama_reward']; // Array Nama
    $poins = $_POST['poin_reward']; // Array Poin
    $descs = $_POST['deskripsi']; // Array Deskripsi
    
    $success_count = 0;

    $query = "UPDATE jenis_reward SET nama_reward=?, poin_reward=?, deskripsi=? WHERE id=?";
    $stmt = mysqli_prepare($conn, $query);

    foreach ($ids as $id) {
        $id = (int) $id;
        $nama = $namas[$id];
        $poin = (int) $poins[$id];
        $desc = $descs[$id];

        mysqli_stmt_bind_param($stmt, "sisi", $nama, $poin, $desc, $id);
        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        }
    }
    mysqli_stmt_close($stmt);

    $_SESSION['message'] = ['type' => 'success', 'text' => "$success_count data reward berhasil diperbarui sekaligus."];
    header("Location: index.php");
    exit;
}

// --- 4. PROSES TAMBAH BANYAK (Bulk Insert) ---
if (isset($_POST['add_bulk'])) {
    guard('jenis_reward_create');

    $lines = explode("\n", trim($_POST['bulk_input']));
    $success_count = 0;
    $errors = [];

    $query = "INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Pisahkan dengan koma (,)
        $parts = array_map('trim', explode(',', $line));
        
        $nama = $parts[0] ?? '';
        $poin = (int) ($parts[1] ?? 0);
        $desc = $parts[2] ?? '';

        if (empty($nama) || $poin <= 0) {
            $errors[] = "Baris tidak valid: '$line'";
            continue;
        }

        mysqli_stmt_bind_param($stmt, "sis", $nama, $poin, $desc);
        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        } else {
            $errors[] = "Gagal menyimpan: '$nama' - " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);

    if (!empty($errors)) {
        $_SESSION['message'] = [
            'type' => 'warning',
            'text' => "$success_count berhasil ditambahkan. Ada error: " . implode('<br>', array_slice($errors, 0, 3))
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Berhasil menambahkan $success_count reward sekaligus!"
        ];
    }

    header("Location: index.php");
    exit;
}
?>