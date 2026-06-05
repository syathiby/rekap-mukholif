<?php
$_SERVER['REQUEST_URI'] = '/rekap-mukholif/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/rekap-mukholif/index.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
require 'public/index.php';
$out = ob_get_clean();

$files = get_included_files();
file_put_contents('included_files.txt', print_r($files, true));
file_put_contents('dashboard_output.html', $out);
