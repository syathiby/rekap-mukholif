<?php
require 'vendor/autoload.php';
try {
    var_dump(class_exists('App\Controllers\AuthController'));
} catch (Exception $e) {
    echo $e->getMessage();
}

