<?php
// File: config/app.php

if (!defined('BASE_URL')) {
    // 1. Deteksi Protokol (HTTP vs HTTPS)
    // Mendukung direct SSL, Apache/Nginx SSL, Reverse Proxy, Cloudflare, Ngrok, LocalTunnel, AWS ELB, dll.
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on')
        || (isset($_SERVER['HTTP_X_URL_SCHEME']) && strtolower($_SERVER['HTTP_X_URL_SCHEME']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && (int)$_SERVER['HTTP_X_FORWARDED_PORT'] === 443);

    $protocol = $is_https ? "https://" : "http://";

    // 2. Deteksi Host & Domain (Mendukung VirtualHost, IP, Ngrok Tunnel, Cloudflare)
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, ',') !== false) {
        $host = trim(explode(',', $host)[0]);
    }

    // 3. Deteksi Subfolder secara Otomatis & Presisi
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $project_dir = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

    $subfolder = '';
    if (!empty($doc_root)) {
        $doc_root_clean = rtrim($doc_root, '/');
        $proj_dir_clean = rtrim($project_dir, '/');
        if (strpos($proj_dir_clean, $doc_root_clean) === 0) {
            $subfolder = substr($proj_dir_clean, strlen($doc_root_clean));
        }
    }

    // Fallback jika DOCUMENT_ROOT tidak cocok (alias / symlink / proxy forwarding)
    if (empty($subfolder) && isset($_SERVER['SCRIPT_NAME'])) {
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $proj_name = basename($project_dir);
        $pos = strpos($script_dir, '/' . $proj_name);
        if ($pos !== false) {
            $subfolder = substr($script_dir, 0, $pos + strlen('/' . $proj_name));
        }
    }
    $subfolder = rtrim($subfolder, '/');

    $base_url = rtrim($protocol . $host . $subfolder, '/');
    define('BASE_URL', $base_url);
    define('BASE_PATH', $subfolder);
    define('IS_HTTPS', $is_https);
}
?>