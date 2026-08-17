<?php
// File: config/app.php

if (!defined('BASE_URL')) {
    // Cek protokol (http atau https)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $protocol = $is_https ? "https://" : "http://";

    // Ambil nama domain (cth: localhost atau rekap-mukholif.test)
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Logika penentuan subfolder
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
    $project_dir = dirname(__DIR__);

    if (!empty($doc_root)) {
        $subfolder = str_replace(str_replace('\\', '/', $doc_root), '', str_replace('\\', '/', $project_dir));
    } else {
        $subfolder = '';
    }

    $base_url = rtrim($protocol . $host . $subfolder, '/');
    define('BASE_URL', $base_url);
}
?>