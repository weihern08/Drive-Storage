<?php
/**
 * Vaultora — Storage path & quota helpers
 */

declare(strict_types=1);

function vaultora_ensure_storage_dirs(): bool
{
    $ok = true;
    foreach ([VAULTORA_STORAGE_PATH, VAULTORA_VERSIONS_PATH, VAULTORA_TEMP_PATH] as $dir) {
        if (!vaultora_ensure_dir($dir)) {
            $ok = false;
        }
    }

    $htaccess = VAULTORA_ROOT . '/storage/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }

    // Keep folders in ZIP / deployments
    foreach ([VAULTORA_STORAGE_PATH, VAULTORA_VERSIONS_PATH, VAULTORA_TEMP_PATH] as $dir) {
        $keep = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (is_dir($dir) && !is_file($keep)) {
            @file_put_contents($keep, "<!DOCTYPE html><title>Forbidden</title>\n");
        }
    }

    return $ok;
}

function vaultora_ensure_dir(string $dir): bool
{
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('Vaultora mkdir failed: ' . $dir);
            return false;
        }
    }

    // Shared hosting often needs 0755/0775 rather than 0750
    @chmod($dir, 0755);

    return is_dir($dir) && is_writable($dir);
}

function vaultora_user_storage_dir(int $userId): ?string
{
    vaultora_ensure_storage_dirs();

    $dir = VAULTORA_STORAGE_PATH . DIRECTORY_SEPARATOR . 'u' . $userId;
    if (!vaultora_ensure_dir($dir)) {
        return null;
    }

    return $dir;
}

function vaultora_resolve_stored_path(int $userId, string $storedName): ?string
{
    $storedName = basename($storedName);
    if ($storedName === '' || str_contains($storedName, '..')) {
        return null;
    }

    $userDir = vaultora_user_storage_dir($userId);
    if ($userDir === null) {
        return null;
    }

    $base = realpath($userDir);
    if ($base === false) {
        // realpath can fail on some hosts even when dir exists; fall back
        $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $userDir), DIRECTORY_SEPARATOR);
        if (!is_dir($base)) {
            return null;
        }
    }

    $full = $base . DIRECTORY_SEPARATOR . $storedName;
    $real = realpath($full);
    if ($real === false) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);
        if (!str_starts_with($normalized, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $normalized;
    }

    if (!str_starts_with($real, $base . DIRECTORY_SEPARATOR) && $real !== $base) {
        return null;
    }

    return $real;
}

function vaultora_save_upload(string $tmpPath, string $destPath): bool
{
    $dir = dirname($destPath);
    if (!vaultora_ensure_dir($dir)) {
        return false;
    }

    if (is_uploaded_file($tmpPath)) {
        if (@move_uploaded_file($tmpPath, $destPath)) {
            @chmod($destPath, 0644);
            return true;
        }
    }

    // Fallback for hosts where move_uploaded_file is restricted
    if (@copy($tmpPath, $destPath)) {
        @chmod($destPath, 0644);
        @unlink($tmpPath);
        return is_file($destPath);
    }

    return false;
}

function vaultora_storage_writable_status(): array
{
    vaultora_ensure_storage_dirs();
    $paths = [
        'storage' => VAULTORA_ROOT . '/storage',
        'vault' => VAULTORA_STORAGE_PATH,
        'temp' => VAULTORA_TEMP_PATH,
        'versions' => VAULTORA_VERSIONS_PATH,
    ];
    $report = [];
    foreach ($paths as $label => $path) {
        $report[$label] = [
            'path' => $path,
            'exists' => is_dir($path),
            'writable' => is_dir($path) && is_writable($path),
        ];
    }
    return $report;
}

function vaultora_recalculate_storage(int $userId): int
{
    $stmt = vaultora_db()->prepare(
        'SELECT COALESCE(SUM(size_bytes), 0) AS total
         FROM vault_files
         WHERE user_id = ? AND is_purged = 0'
    );
    $stmt->execute([$userId]);
    $total = (int) ($stmt->fetch()['total'] ?? 0);

    $vstmt = vaultora_db()->prepare(
        'SELECT COALESCE(SUM(v.size_bytes), 0) AS total
         FROM vault_file_versions v
         INNER JOIN vault_files f ON f.id = v.file_id
         WHERE f.user_id = ? AND f.is_purged = 0 AND v.is_current = 0'
    );
    $vstmt->execute([$userId]);
    $total += (int) ($vstmt->fetch()['total'] ?? 0);

    $upd = vaultora_db()->prepare('UPDATE vault_users SET storage_used_bytes = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute([$total, $userId]);

    return $total;
}

function vaultora_storage_stats(array $user): array
{
    $quota = (int) $user['storage_quota_bytes'];
    $used = (int) $user['storage_used_bytes'];
    $remaining = max(0, $quota - $used);
    $percent = $quota > 0 ? min(100, round(($used / $quota) * 100, 1)) : 0;

    return [
        'quota' => $quota,
        'used' => $used,
        'remaining' => $remaining,
        'percent' => $percent,
        'quota_label' => vaultora_format_bytes($quota),
        'used_label' => vaultora_format_bytes($used),
        'remaining_label' => vaultora_format_bytes($remaining),
    ];
}

function vaultora_can_store(array $user, int $incomingBytes): bool
{
    $stats = vaultora_storage_stats($user);
    return ($stats['used'] + $incomingBytes) <= $stats['quota'];
}
