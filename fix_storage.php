<?php
/**
 * One-time storage permission fixer for cPanel.
 * Open in browser once, then DELETE this file.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$ok = vaultora_ensure_storage_dirs();
$report = vaultora_storage_writable_status();

// Prepare folders for known users
try {
    $rows = vaultora_db()->query('SELECT id FROM vault_users')->fetchAll();
    foreach ($rows as $row) {
        $dir = vaultora_user_storage_dir((int) $row['id']);
        $report['u' . $row['id']] = [
            'path' => $dir ?? (VAULTORA_STORAGE_PATH . '/u' . $row['id']),
            'exists' => $dir !== null && is_dir($dir),
            'writable' => $dir !== null && is_dir($dir) && is_writable($dir),
        ];
    }
} catch (Throwable $e) {
    $report['users'] = ['error' => $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fix storage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container" style="max-width:720px">
    <h1 class="h3">Storage check</h1>
    <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>">
        <?= $ok ? 'Storage folders are ready.' : 'Some folders are not writable. In cPanel File Manager set storage/ to 755 or 775.' ?>
    </div>
    <table class="table table-bordered">
        <thead><tr><th>Folder</th><th>Exists</th><th>Writable</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach ($report as $label => $info): ?>
            <?php if (!is_array($info) || !isset($info['path'])) continue; ?>
            <tr>
                <td><?= htmlspecialchars((string) $label) ?></td>
                <td><?= !empty($info['exists']) ? 'Yes' : 'No' ?></td>
                <td><?= !empty($info['writable']) ? 'Yes' : 'No' ?></td>
                <td><code><?= htmlspecialchars((string) $info['path']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-danger">Delete <code>fix_storage.php</code> after use.</p>
    <a class="btn btn-primary" href="<?= htmlspecialchars(vaultora_url('files.php')) ?>">Back to files</a>
</div>
</body>
</html>
