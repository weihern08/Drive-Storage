<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$token = vaultora_get_string('t');
$share = $token !== '' ? vaultora_get_share_by_token($token) : null;
$error = null;
$unlocked = false;
$access = null;
$resource = null;

if (!$share) {
    $error = 'This share link is invalid.';
} else {
    $access = vaultora_share_is_accessible($share);
    if (!$access['ok']) {
        $error = $access['message'];
    } else {
        $resource = $access['resource'];
        $needsPassword = !empty($share['password_hash']);
        if ($needsPassword && !vaultora_share_unlocked($token)) {
            if (vaultora_request_method() === 'POST') {
                vaultora_require_csrf();
                if (vaultora_share_password_ok($share, (string) ($_POST['password'] ?? ''))) {
                    vaultora_mark_share_unlocked($token);
                    $unlocked = true;
                } else {
                    $error = 'Incorrect password.';
                }
            }
        } else {
            $unlocked = true;
        }
    }
}

$download = vaultora_get_string('download');
if ($share && $unlocked && $resource && $download !== '') {
    $ownerId = (int) $share['user_id'];
    if ($share['resource_type'] === 'file' && $download === 'file') {
        $path = vaultora_resolve_stored_path($ownerId, $resource['stored_name']);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'File missing.';
            exit;
        }
        vaultora_increment_share_views((int) $share['id']);
        header('Content-Type: ' . ($resource['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $resource['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    if ($share['resource_type'] === 'folder' && $download === 'zip') {
        vaultora_ensure_storage_dirs();
        $zipPath = VAULTORA_TEMP_PATH . DIRECTORY_SEPARATOR . 'share_' . bin2hex(random_bytes(8)) . '.zip';
        if (!vaultora_zip_shared_folder((int) $share['resource_id'], $ownerId, $zipPath)) {
            http_response_code(500);
            echo 'ZIP creation is unavailable on this server.';
            exit;
        }
        vaultora_increment_share_views((int) $share['id']);
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $resource['name']) . '.zip"');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }
    if ($share['resource_type'] === 'file' && $download === 'preview') {
        if (!vaultora_is_previewable($resource['extension'], $resource['mime_type'])) {
            http_response_code(415);
            echo 'Preview unavailable.';
            exit;
        }
        $path = vaultora_resolve_stored_path($ownerId, $resource['stored_name']);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . ($resource['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $resource['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}

$ownerName = '';
$contents = null;
if ($share && $unlocked && $resource) {
    $ostmt = vaultora_db()->prepare('SELECT display_name FROM vault_users WHERE id = ?');
    $ostmt->execute([(int) $share['user_id']]);
    $ownerName = (string) ($ostmt->fetch()['display_name'] ?? 'Vaultora user');
    vaultora_increment_share_views((int) $share['id']);
    if ($share['resource_type'] === 'folder') {
        $contents = vaultora_list_folder_contents_for_share((int) $share['resource_id'], (int) $share['user_id']);
    }
}

$showPasswordForm = $share && $access && $access['ok'] && !$unlocked;
$showContent = $share && $unlocked && $resource;
$showUnavailable = !$showPasswordForm && !$showContent;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shared resource · <?= e(vaultora_app_name()) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(vaultora_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="share-public-card">
    <div class="brand-mark mb-4">
        <span class="brand-glyph"><i class="fa-solid fa-cube"></i></span>
        <div>
            <div class="brand-name"><?= e(vaultora_app_name()) ?></div>
            <div class="brand-tag">Shared resource</div>
        </div>
    </div>

    <?php if ($showUnavailable): ?>
        <div class="empty-state">
            <i class="fa-solid fa-link-slash"></i>
            <h3>Unavailable</h3>
            <p><?= e($error ?: 'This share link is unavailable.') ?></p>
        </div>
    <?php elseif ($showPasswordForm): ?>
        <h1 class="auth-title">Protected <?= e($share['resource_type']) ?></h1>
        <p class="auth-lead">Enter the access password to continue.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="vstack gap-3">
            <?= vaultora_csrf_field() ?>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-control form-control-lg" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-vault btn-lg" type="submit">Unlock</button>
        </form>
    <?php elseif ($showContent && $share['resource_type'] === 'file'): ?>
        <h1 class="auth-title"><?= e($resource['original_name']) ?></h1>
        <p class="auth-lead">
            <?= e(strtoupper((string) $resource['extension']) ?: 'File') ?> ·
            <?= e(vaultora_format_bytes((int) $resource['size_bytes'])) ?> ·
            Shared by <?= e($ownerName) ?>
        </p>
        <ul class="list-unstyled small text-secondary mb-4">
            <li>Shared: <?= e($share['created_at']) ?></li>
            <li>Expires: <?= e($share['expires_at'] ?: 'Never') ?></li>
            <li>Views: <?= (int) $share['view_count'] ?></li>
        </ul>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-vault" href="<?= e(vaultora_url('public_share.php?t=' . urlencode($token) . '&download=file')) ?>">Download</a>
            <?php if (vaultora_is_previewable($resource['extension'], $resource['mime_type'])): ?>
                <a class="btn btn-outline-vault" href="<?= e(vaultora_url('public_share.php?t=' . urlencode($token) . '&download=preview')) ?>" target="_blank" rel="noopener">Preview</a>
            <?php endif; ?>
        </div>
    <?php elseif ($showContent): ?>
        <h1 class="auth-title"><?= e($resource['name']) ?></h1>
        <p class="auth-lead">Shared folder by <?= e($ownerName) ?></p>
        <div class="mb-3">
            <a class="btn btn-vault" href="<?= e(vaultora_url('public_share.php?t=' . urlencode($token) . '&download=zip')) ?>">Download folder ZIP</a>
        </div>
        <div class="table-responsive">
            <table class="table-vault">
                <thead><tr><th>Name</th><th>Type</th><th>Size</th></tr></thead>
                <tbody>
                <?php foreach (($contents['folders'] ?? []) as $folder): ?>
                    <tr><td><i class="fa-solid fa-folder me-2"></i><?= e($folder['name']) ?></td><td>Folder</td><td>—</td></tr>
                <?php endforeach; ?>
                <?php foreach (($contents['files'] ?? []) as $file): ?>
                    <tr>
                        <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></td>
                        <td>File</td>
                        <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
