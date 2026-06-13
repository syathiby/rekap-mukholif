<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
require 'app/Core/Database.php';

$db = \App\Core\Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE santri');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
