<?php
require_once '../../init.php'; // Load DB & Auth

// --- 1. PROSES TAMBAH BARU ---
if (isset($_POST['add_jenis'])) {
    guard('jenis_reward_create');
    
    $nama = mysqli_real_escape_string($conn, $_POST['nama_reward']);
    $poin = (int) $_POST['poin_reward'];
    $desc = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    $query = "INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES ('$nama', '$poin', '$desc')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Reward baru berhasil ditambahkan!'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal: ' . mysqli_error($conn)];
    }
    header("Location: index.php");
    exit;
}

// --- 2. PROSES EDIT SATUAN ---
if (isset($_POST['edit_jenis'])) {
    guard('jenis_reward_edit');

    $id   = (int) $_POST['id'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama_reward']);
    $poin = (int) $_POST['poin_reward'];
    $desc = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    $query = "UPDATE jenis_reward SET nama_reward='$nama', poin_reward='$poin', deskripsi='$desc' WHERE id=$id";

    if (mysqli_query($conn, $query)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data reward berhasil diperbarui.'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal update: ' . mysqli_error($conn)];
    }
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

    foreach ($ids as $id) {
        $id = (int) $id;
        $nama = mysqli_real_escape_string($conn, $namas[$id]);
        $poin = (int) $poins[$id];
        $desc = mysqli_real_escape_string($conn, $descs[$id]);

        $query = "UPDATE jenis_reward SET nama_reward='$nama', poin_reward='$poin', deskripsi='$desc' WHERE id=$id";
        if (mysqli_query($conn, $query)) {
            $success_count++;
        }
    }

    $_SESSION['message'] = ['type' => 'success', 'text' => "$success_count data reward berhasil diperbarui sekaligus."];
    header("Location: index.php");
    exit;
}
?>