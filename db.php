<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host     = $_ENV['DB_HOST'];
$user     = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$dbname   = $_ENV['DB_NAME'];

$conn = mysqli_connect(hostname: $host, username: $user, password: $password, database: $dbname);

if (!$conn) {
    die("koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Jakarta');

?>