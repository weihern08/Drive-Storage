<?php
/**
 * Vaultora — File version history
 */

declare(strict_types=1);

function vaultora_list_versions(int $fileId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId, true);
    if (!$file) {
        return [];
    }
    $stmt = vaultora_db()->prepare(
        'SELECT * FROM vault_file_versions WHERE file_id = ? AND user_id = ? ORDER BY version_number DESC'
    );
    $stmt->execute([$fileId, $userId]);
    return $stmt->fetchAll();
}

function vaultora_get_version(int $versionId, int $userId): ?array
{
    $stmt = vaultora_db()->prepare(
        'SELECT v.*, f.original_name, f.is_deleted, f.is_purged
         FROM vault_file_versions v
         INNER JOIN vault_files f ON f.id = v.file_id
         WHERE v.id = ? AND v.user_id = ? AND f.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$versionId, $userId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_restore_version(int $versionId, int $userId): array
{
    $version = vaultora_get_version($versionId, $userId);
    if (!$version || (int) $version['is_purged'] === 1 || (int) $version['is_deleted'] === 1) {
        return ['success' => false, 'message' => 'Version not found.'];
    }
    if ((int) $version['is_current'] === 1) {
        return ['success' => false, 'message' => 'This version is already current.'];
    }

    $fileId = (int) $version['file_id'];
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }

    $source = vaultora_resolve_stored_path($userId, $version['stored_name']);
    if (!$source || !is_file($source)) {
        return ['success' => false, 'message' => 'Version file is missing on disk.'];
    }

    $newStored = vaultora_stored_filename($file['original_name']);
    $dest = vaultora_resolve_stored_path($userId, $newStored);
    if ($dest === null || !copy($source, $dest)) {
        return ['success' => false, 'message' => 'Could not restore version file.'];
    }

    $pdo = vaultora_db();
    try {
        $pdo->beginTransaction();
        $newVersion = (int) $file['current_version'] + 1;
        $pdo->prepare('UPDATE vault_file_versions SET is_current = 0 WHERE file_id = ?')->execute([$fileId]);

        $ins = $pdo->prepare(
            'INSERT INTO vault_file_versions (file_id, user_id, version_number, stored_name, size_bytes, mime_type, is_current, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        $ins->execute([
            $fileId,
            $userId,
            $newVersion,
            $newStored,
            (int) $version['size_bytes'],
            $version['mime_type'],
        ]);

        $upd = $pdo->prepare(
            'UPDATE vault_files
             SET stored_name = ?, size_bytes = ?, mime_type = ?, current_version = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $upd->execute([
            $newStored,
            (int) $version['size_bytes'],
            $version['mime_type'],
            $newVersion,
            $fileId,
            $userId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($dest);
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not restore version.'];
    }

    vaultora_recalculate_storage($userId);
    vaultora_log_activity($userId, 'rollback_version', 'file', $fileId, 'Restored version ' . $version['version_number']);

    return ['success' => true, 'message' => 'Version restored as current.'];
}

function vaultora_download_version(int $versionId, int $userId): never
{
    $version = vaultora_get_version($versionId, $userId);
    if (!$version || (int) $version['is_purged'] === 1) {
        http_response_code(404);
        echo 'Version not found.';
        exit;
    }

    $path = vaultora_resolve_stored_path($userId, $version['stored_name']);
    if (!$path || !is_file($path)) {
        http_response_code(404);
        echo 'Version file missing.';
        exit;
    }

    $name = pathinfo($version['original_name'], PATHINFO_FILENAME)
        . '_v' . $version['version_number']
        . (pathinfo($version['original_name'], PATHINFO_EXTENSION) ? '.' . pathinfo($version['original_name'], PATHINFO_EXTENSION) : '');

    vaultora_log_activity($userId, 'download', 'file_version', $versionId, 'Downloaded version ' . $version['version_number']);

    header('Content-Type: ' . ($version['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
