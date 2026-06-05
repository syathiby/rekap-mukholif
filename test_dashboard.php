<?php
require 'vendor/autoload.php';
require 'bootstrap/init.php';

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Bypass auth by setting session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$router = new \App\Core\Router();
$router->get('/', 'DashboardController', 'index');

ob_start();
try {
    $router->dispatch('/', 'GET');
} catch (Exception $e) {
    echo $e->getMessage();
}
$out = ob_get_clean();

file_put_contents('test_out.html', $out);
