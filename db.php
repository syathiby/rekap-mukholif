<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "db_mukholif";

$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set timezone ke Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');
?>