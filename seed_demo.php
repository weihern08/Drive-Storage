<?php
/**
 * Vaultora — one-time demo account seed for cPanel
 * Visit once in the browser, then DELETE this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!vaultora_is_installed()) {
    http_response_code(403);
    echo 'Please finish installation first (create installed.lock), then run this page again.';
    exit;
}

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

$messages = [];
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
        $messages[] = 'Updated: ' . $account['email'] . ' / ' . $account['password'];
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
        $messages[] = 'Created: ' . $account['email'] . ' / ' . $account['password'];
    }
}

// Ensure default settings exist
$defaults = [
    'app_name' => vaultora_setting('app_name', 'Vaultora') ?: 'Vaultora',
    'max_upload_bytes' => (string) VAULTORA_DEFAULT_MAX_UPLOAD,
    'default_quota_bytes' => (string) VAULTORA_DEFAULT_QUOTA,
    'trash_retention_days' => (string) VAULTORA_DEFAULT_TRASH_DAYS,
    'allowed_extensions' => implode(',', VAULTORA_DEFAULT_ALLOWED_EXTENSIONS),
];
$insSetting = $pdo->prepare(
    'INSERT INTO vault_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
);
foreach ($defaults as $key => $value) {
    $insSetting->execute([$key, $value]);
}

vaultora_ensure_storage_dirs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seed demo accounts</title>
    <link href="<?= htmlspecialchars(vaultora_url('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-card">
        <h1 class="auth-title">Demo accounts ready</h1>
        <p class="auth-lead">The user and admin accounts have been created/updated.</p>
        <ul>
            <?php foreach ($messages as $msg): ?>
                <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="demo-creds mb-3">
            <div class="demo-creds-row">
                <strong>Admin login</strong>
                <span>gmail: <code>admin@gmail.com</code></span>
                <span>password: <code>Admin123!</code></span>
                <a href="<?= htmlspecialchars(vaultora_url('admin/login.php'), ENT_QUOTES, 'UTF-8') ?>">Open admin login</a>
            </div>
            <div class="demo-creds-row">
                <strong>User login</strong>
                <span>gmail: <code>user@gmail.com</code></span>
                <span>password: <code>User123!</code></span>
                <a href="<?= htmlspecialchars(vaultora_url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Open user login</a>
            </div>
        </div>
        <div class="alert alert-warning mb-0">
            For security, delete <code>seed_demo.php</code> from the server now.
        </div>
    </div>
</div>
</body>
</html>
