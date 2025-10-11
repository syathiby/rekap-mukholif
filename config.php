<?php
// File: config.php

// Jurus Sakti v2: Bikin Base URL Dinamis & Stabil
// ===================================================

// Cek protokol (http atau https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Ambil nama domain (cth: localhost atau domainkamu.com)
$host = $_SERVER['HTTP_HOST'];

// --- BAGIAN TERPENTING YANG DIPERBAIKI ---
// Logika ini akan selalu menemukan subfolder aplikasi, tidak peduli seberapa dalam file diakses.
$doc_root = $_SERVER['DOCUMENT_ROOT'];
$project_dir = __DIR__; // Mengambil path absolut di server (cth: C:/xampp/htdocs/rekap-mukholif)

// Menghapus path document root dari path project untuk mendapatkan subfolder
$subfolder = str_replace(str_replace('\\', '/', $doc_root), '', str_replace('\\', '/', $project_dir));

// Gabungin semua jadi satu alamat lengkap yang STABIL!
$base_url = $protocol . $host . $subfolder;

// Definisikan sebagai konstanta biar bisa dipanggil dari mana aja
define('BASE_URL', $base_url);

?>