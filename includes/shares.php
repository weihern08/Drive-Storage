<?php
/**
 * Vaultora — Public sharing
 */

declare(strict_types=1);

function vaultora_active_share_for(string $type, int $resourceId, int $userId): ?array
{
    $stmt = vaultora_db()->prepare(
        'SELECT * FROM vault_shares
         WHERE user_id = ? AND resource_type = ? AND resource_id = ? AND is_active = 1
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $type, $resourceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_get_share_by_token(string $token): ?array
{
    $stmt = vaultora_db()->prepare('SELECT * FROM vault_shares WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_share_is_accessible(array $share): array
{
    if (!(int) $share['is_active']) {
        return ['ok' => false, 'message' => 'This share link is no longer active.'];
    }
    if (!empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'This share link has expired.'];
    }

    if ($share['resource_type'] === 'file') {
        $stmt = vaultora_db()->prepare(
            'SELECT * FROM vault_files WHERE id = ? AND user_id = ? AND is_deleted = 0 AND is_purged = 0 LIMIT 1'
        );
        $stmt->execute([(int) $share['resource_id'], (int) $share['user_id']]);
        $resource = $stmt->fetch();
        if (!$resource) {
            return ['ok' => false, 'message' => 'Shared file is unavailable.'];
        }
        return ['ok' => true, 'resource' => $resource];
    }

    $stmt = vaultora_db()->prepare(
        'SELECT * FROM vault_folders WHERE id = ? AND user_id = ? AND is_deleted = 0 LIMIT 1'
    );
    $stmt->execute([(int) $share['resource_id'], (int) $share['user_id']]);
    $resource = $stmt->fetch();
    if (!$resource) {
        return ['ok' => false, 'message' => 'Shared folder is unavailable.'];
    }
    return ['ok' => true, 'resource' => $resource];
}

function vaultora_create_share(
    int $userId,
    string $type,
    int $resourceId,
    ?string $password,
    ?string $expiresAt
): array {
    if (!in_array($type, ['file', 'folder'], true)) {
        return ['success' => false, 'message' => 'Invalid share type.'];
    }

    if ($type === 'file') {
        $resource = vaultora_get_file($resourceId, $userId);
    } else {
        $resource = vaultora_get_folder($resourceId, $userId);
    }
    if (!$resource) {
        return ['success' => false, 'message' => 'Resource not found.'];
    }

    if ($expiresAt !== null && $expiresAt !== '') {
        $ts = strtotime($expiresAt);
        if ($ts === false || $ts < time()) {
            return ['success' => false, 'message' => 'Expiration must be a future date.'];
        }
        $expiresAt = date('Y-m-d H:i:s', $ts);
    } else {
        $expiresAt = null;
    }

    $passwordHash = null;
    if ($password !== null && $password !== '') {
        if (strlen($password) < 4) {
            return ['success' => false, 'message' => 'Share password must be at least 4 characters.'];
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    // Deactivate previous shares for same resource
    vaultora_db()->prepare(
        'UPDATE vault_shares SET is_active = 0 WHERE user_id = ? AND resource_type = ? AND resource_id = ?'
    )->execute([$userId, $type, $resourceId]);

    $token = vaultora_random_token(24);
    $stmt = vaultora_db()->prepare(
        'INSERT INTO vault_shares
         (user_id, resource_type, resource_id, token, password_hash, is_active, expires_at, view_count, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, 0, NOW())'
    );
    $stmt->execute([$userId, $type, $resourceId, $token, $passwordHash, $expiresAt]);
    $id = (int) vaultora_db()->lastInsertId();

    vaultora_log_activity($userId, 'create_share', $type, $resourceId, 'Created share link');

    return [
        'success' => true,
        'message' => 'Share link created.',
        'token' => $token,
        'url' => vaultora_absolute_url('public_share.php?t=' . urlencode($token)),
        'id' => $id,
    ];
}

function vaultora_disable_share(int $shareId, int $userId): array
{
    $stmt = vaultora_db()->prepare('SELECT * FROM vault_shares WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$shareId, $userId]);
    $share = $stmt->fetch();
    if (!$share) {
        return ['success' => false, 'message' => 'Share not found.'];
    }
    vaultora_db()->prepare('UPDATE vault_shares SET is_active = 0 WHERE id = ? AND user_id = ?')
        ->execute([$shareId, $userId]);
    vaultora_log_activity($userId, 'disable_share', $share['resource_type'], (int) $share['resource_id'], 'Disabled share link');
    return ['success' => true, 'message' => 'Share link disabled.'];
}

function vaultora_share_password_ok(array $share, string $password): bool
{
    if (empty($share['password_hash'])) {
        return true;
    }
    return password_verify($password, $share['password_hash']);
}

function vaultora_share_unlocked(string $token): bool
{
    return !empty($_SESSION['vaultora_share_ok'][$token]);
}

function vaultora_mark_share_unlocked(string $token): void
{
    $_SESSION['vaultora_share_ok'][$token] = true;
}

function vaultora_increment_share_views(int $shareId): void
{
    vaultora_db()->prepare('UPDATE vault_shares SET view_count = view_count + 1 WHERE id = ?')->execute([$shareId]);
}

function vaultora_list_folder_contents_for_share(int $folderId, int $ownerId): array
{
    $folders = vaultora_db()->prepare(
        'SELECT id, name, updated_at FROM vault_folders WHERE parent_id = ? AND user_id = ? AND is_deleted = 0 ORDER BY name'
    );
    $folders->execute([$folderId, $ownerId]);

    $files = vaultora_db()->prepare(
        'SELECT id, original_name, extension, size_bytes, mime_type, updated_at
         FROM vault_files WHERE folder_id = ? AND user_id = ? AND is_deleted = 0 AND is_purged = 0 ORDER BY original_name'
    );
    $files->execute([$folderId, $ownerId]);

    return ['folders' => $folders->fetchAll(), 'files' => $files->fetchAll()];
}

function vaultora_zip_shared_folder(int $folderId, int $ownerId, string $zipPath): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $addFolder = function (int $fid, string $prefix) use (&$addFolder, $ownerId, $zip): void {
        $contents = vaultora_list_folder_contents_for_share($fid, $ownerId);
        foreach ($contents['folders'] as $folder) {
            $name = $prefix . $folder['name'] . '/';
            $zip->addEmptyDir($name);
            $addFolder((int) $folder['id'], $name);
        }
        foreach ($contents['files'] as $file) {
            $full = vaultora_get_file((int) $file['id'], $ownerId);
            if (!$full) {
                continue;
            }
            $path = vaultora_resolve_stored_path($ownerId, $full['stored_name']);
            if ($path && is_file($path)) {
                $zip->addFile($path, $prefix . $full['original_name']);
            }
        }
    };

    $root = vaultora_get_folder($folderId, $ownerId);
    $rootName = $root['name'] ?? 'folder';
    $zip->addEmptyDir($rootName . '/');
    $addFolder($folderId, $rootName . '/');
    $zip->close();

    return is_file($zipPath) && filesize($zipPath) >= 0;
}
