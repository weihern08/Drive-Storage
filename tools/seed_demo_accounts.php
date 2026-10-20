<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$accounts = [
    [
        'email' => 'admin@gmail.com',
        'password' => 'Admin123!',
        'display_name' => 'Vaultora Admin',
        'role' => 'admin',
        'quota' => VAULTORA_DEFAULT_QUOTA * 2,
    ],
    [
        'email' => 'user@gmail.com',
        'password' => 'User123!',
        'display_name' => 'Demo User',
        'role' => 'user',
        'quota' => VAULTORA_DEFAULT_QUOTA,
    ],
];

$pdo = vaultora_db();

foreach ($accounts as $account) {
    $hash = password_hash($account['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM vault_users WHERE email = ? LIMIT 1');
    $stmt->execute([$account['email']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $pdo->prepare(
            'UPDATE vault_users
             SET password_hash = ?, display_name = ?, role = ?, status = ?, storage_quota_bytes = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $upd->execute([
            $hash,
            $account['display_name'],
            $account['role'],
            'active',
            $account['quota'],
            (int) $existing['id'],
        ]);
        echo "Updated: {$account['email']}\n";
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO vault_users
             (email, password_hash, display_name, role, status, storage_quota_bytes, storage_used_bytes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        $ins->execute([
            $account['email'],
            $hash,
            $account['display_name'],
            $account['role'],
            'active',
            $account['quota'],
        ]);
        $id = (int) $pdo->lastInsertId();
        vaultora_user_storage_dir($id);
        echo "Created: {$account['email']}\n";
    }
}

echo "Done.\n";
