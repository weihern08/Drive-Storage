<?php
/**
 * One-time database connection checker for cPanel.
 * Open in browser once, then DELETE this file.
 */
declare(strict_types=1);

$cfg = require __DIR__ . '/config/database.php';
$lines = [];
$lines[] = 'Database: ' . $cfg['name'];
$lines[] = 'User: ' . $cfg['user'];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4'),
        $cfg['user'],
        $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $lines[] = 'Connection: OK';

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $lines[] = 'Tables (' . count($tables) . '): ' . ($tables ? implode(', ', $tables) : 'NONE - import database/cpanel_ready.sql');

    if (in_array('vault_users', $tables, true)) {
        foreach ($pdo->query('SELECT id, email, role, status FROM vault_users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $lines[] = sprintf('User #%d %s (%s, %s)', $u['id'], $u['email'], $u['role'], $u['status']);
        }
    }
} catch (Throwable $e) {
    $lines[] = 'ERROR: ' . $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines), "\n\nDelete db_check.php after use.\n";
