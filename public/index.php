<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/views');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// 1. Load .env manual
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
$_ENV['APP_DEBUG'] = 'true';
define('BASE_URL', $_ENV['APP_URL'] ?? '');

// 2. Require composer autoloader
require ROOT_PATH . '/vendor/autoload.php';

// 3. Set exception handler global
set_exception_handler(function (Throwable $e) {
    // Log error
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(); exit;
    
    $isAjax = isset($_SERVER['HTTP_HX_REQUEST']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    
    if ($isAjax) {
        http_response_code(500);
        echo "Internal Server Error";
    } else {
        http_response_code(500);
        if (file_exists(VIEW_PATH . '/errors/500.php')) {
            require VIEW_PATH . '/errors/500.php';
        } else {
            echo "<h1>500 - Internal Server Error</h1>";
            if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
});

// 4. Start session
$sessionName = $_ENV['SESSION_NAME'] ?? 'asuhtrack_sess';
session_name($sessionName);
session_set_cookie_params([
    'lifetime' => 0, // Until browser closes
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// 5. Cek inactivity timeout (10800 detik / 3 jam)
$lifetime = isset($_ENV['SESSION_LIFETIME']) ? (int)$_ENV['SESSION_LIFETIME'] : 10800;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $lifetime) {
    session_unset();
    session_destroy();
    header('Location: ' . ($_ENV['APP_URL'] ?? '') . '/login');
    exit;
} elseif (isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time(); // refresh timeout
}

// 6. Inisialisasi Router
$router = new \App\Core\Router();

// 7. Require routes
require APP_PATH . '/routes.php';

// 8. Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

