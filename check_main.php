<?php
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/views');
if (file_exists(VIEW_PATH . '/layouts/main.php')) {
    echo "EXISTS\n";
} else {
    echo "NOT EXISTS\n";
}
