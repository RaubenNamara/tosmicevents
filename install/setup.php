<?php
/**
 * One-time setup, run from the command line:
 *   php install/setup.php "Admin Name" admin@example.com "StrongPassword"
 * Creates the database (if allowed), the tables, the starter content and the first admin.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$config = require dirname(__DIR__) . '/config.php';
[$_, $name, $email, $pass] = array_pad($argv, 4, null);
if (!$name || !$email || !$pass) {
    exit("Usage: php install/setup.php \"Admin Name\" admin@example.com \"StrongPassword\"\n");
}
if (strlen($pass) < 8) exit("The password must be at least 8 characters.\n");

$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['db_host'], $config['db_port']), $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $config['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . str_replace('`', '', $config['db_name']) . '`');

$sql = file_get_contents(__DIR__ . '/schema.sql');
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
    if (preg_match('/^(--[^\n]*\n?)*$/', $stmt)) continue;
    $pdo->exec($stmt);
}

$st = $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)');
$st->execute([$name, strtolower($email), password_hash($pass, PASSWORD_DEFAULT)]);

echo "Done. Sign in at /admin/login.php with $email\n";
