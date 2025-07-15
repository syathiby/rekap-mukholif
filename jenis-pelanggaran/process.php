<?php
include '../db.php';

if (isset($_POST['create'])) {
    $nama_pelanggaran = mysqli_real_escape_string($conn, $_POST['nama_pelanggaran']);
    
    $query = "INSERT INTO jenis_pelanggaran (nama_pelanggaran) VALUES ('$nama_pelanggaran')";
    mysqli_query($conn, $query);
    
    header("Location: index.php");
    exit;
}

if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $nama_pelanggaran = mysqli_real_escape_string($conn, $_POST['nama_pelanggaran']);
    
    $query = "UPDATE jenis_pelanggaran SET nama_pelanggaran = '$nama_pelanggaran' WHERE id = $id";
    mysqli_query($conn, $query);
    
    header("Location: index.php");
    exit;
}
?>