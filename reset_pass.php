<?php
require 'vendor/autoload.php';
require 'bootstrap/init.php';
$db = \App\Core\Database::getInstance()->getConnection();
$stmt = $db->query('SELECT * FROM users LIMIT 1');
$user = $stmt->fetch();
echo "Username: " . $user['username'] . "\n";
$hash = password_hash('admin', PASSWORD_DEFAULT);
$db->query("UPDATE users SET password = '$hash' WHERE id = " . $user['id']);
echo "Password reset to 'admin'\n";
