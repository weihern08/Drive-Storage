<?php
/**
 * Vaultora — File operations
 */

declare(strict_types=1);

function vaultora_get_file(int $fileId, int $userId, bool $includeDeleted = false): ?array
{
    $sql = 'SELECT * FROM vault_files WHERE id = ? AND user_id = ? AND is_purged = 0';
    if (!$includeDeleted) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute([$fileId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_file_name_exists(int $userId, string $name, ?int $folderId, ?int $excludeId = null): bool
{
    $sql = 'SELECT id FROM vault_files WHERE user_id = ? AND original_name = ? AND is_deleted = 0 AND is_purged = 0 AND ';
    $params = [$userId, $name];
    if ($folderId === null) {
        $sql .= 'folder_id IS NULL';
    } else {
        $sql .= 'folder_id = ?';
        $params[] = $folderId;
    }
    if ($excludeId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function vaultora_detect_mime(string $tmpPath, string $originalName): string
{
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    }
    return $mime;
}

function vaultora_upload_file(array $user, array $file, ?int $folderId = null, ?int $replaceFileId = null): array
{
    $userId = (int) $user['id'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => vaultora_upload_error_message((int) $file['error'])];
    }

    $original = vaultora_safe_filename((string) ($file['name'] ?? 'file'));
    $ext = vaultora_extension($original);
    $size = (int) ($file['size'] ?? 0);
    $tmp = (string) ($file['tmp_name'] ?? '');

    if ($size <= 0 || $tmp === '' || !is_uploaded_file($tmp)) {
        return ['success' => false, 'message' => 'Invalid upload.'];
    }

    if ($size > vaultora_max_upload_bytes()) {
        return ['success' => false, 'message' => 'File too large. Maximum size is ' . vaultora_format_bytes(vaultora_max_upload_bytes()) . '.'];
    }

    if (!vaultora_extension_allowed($ext)) {
        return ['success' => false, 'message' => 'Unsupported or blocked file type.'];
    }

    // Fresh user stats
    $fresh = vaultora_current_user() ?: $user;
    if (!vaultora_can_store($fresh, $size)) {
        return ['success' => false, 'message' => 'Storage quota exceeded.'];
    }

    if ($folderId !== null && $folderId > 0) {
        $folder = vaultora_get_folder($folderId, $userId);
        if (!$folder) {
            return ['success' => false, 'message' => 'Destination folder not found.'];
        }
    } else {
        $folderId = null;
    }

    $mime = vaultora_detect_mime($tmp, $original);
    // Block obvious PHP content even if renamed
    if (str_contains(strtolower($mime), 'php') || str_contains(strtolower($mime), 'x-httpd-php')) {
        return ['success' => false, 'message' => 'Unsupported or blocked file type.'];
    }

    if (!vaultora_ensure_storage_dirs()) {
        return ['success' => false, 'message' => 'Storage folder is not writable. In cPanel set storage/ (and subfolders) to 755 or 775.'];
    }

    $stored = vaultora_stored_filename($original);
    $dest = vaultora_resolve_stored_path($userId, $stored);
    if ($dest === null) {
        return ['success' => false, 'message' => 'Could not prepare storage path. Create storage/vault and set permission 755/775.'];
    }

    if ($replaceFileId) {
        return vaultora_upload_revision($user, $replaceFileId, $tmp, $original, $stored, $dest, $size, $mime, $ext);
    }

    if (!vaultora_save_upload($tmp, $dest)) {
        error_log('Vaultora upload save failed dest=' . $dest . ' tmp=' . $tmp);
        return ['success' => false, 'message' => 'Failed to save uploaded file. Set storage/ folder permission to 755 or 775 in cPanel File Manager.'];
    }

    $pdo = vaultora_db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO vault_files
             (user_id, folder_id, original_name, stored_name, mime_type, extension, size_bytes, is_starred, is_deleted, is_purged, current_version, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 1, NOW(), NOW())'
        );
        $stmt->execute([$userId, $folderId, $original, $stored, $mime, $ext, $size]);
        $fileId = (int) $pdo->lastInsertId();

        $vstmt = $pdo->prepare(
            'INSERT INTO vault_file_versions (file_id, user_id, version_number, stored_name, size_bytes, mime_type, is_current, created_at)
             VALUES (?, ?, 1, ?, ?, ?, 1, NOW())'
        );
        $vstmt->execute([$fileId, $userId, $stored, $size, $mime]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($dest);
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not save file record.'];
    }

    vaultora_recalculate_storage($userId);
    vaultora_log_activity($userId, 'upload', 'file', $fileId, 'Uploaded file: ' . $original);

    return ['success' => true, 'message' => 'File uploaded successfully.', 'id' => $fileId];
}

function vaultora_upload_revision(
    array $user,
    int $fileId,
    string $tmp,
    string $original,
    string $stored,
    string $dest,
    int $size,
    string $mime,
    string $ext
): array {
    $userId = (int) $user['id'];
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }

    if (!vaultora_extension_allowed($ext)) {
        return ['success' => false, 'message' => 'Unsupported or blocked file type.'];
    }

    // Move current physical file into versions storage archive naming stays as stored_name in user dir
    // New content becomes current stored_name
    if (!vaultora_save_upload($tmp, $dest) && !(@rename($tmp, $dest) || @copy($tmp, $dest))) {
        return ['success' => false, 'message' => 'Failed to save revision. Check storage/ permissions (755/775).'];
    }
    if (is_file($dest)) {
        @chmod($dest, 0644);
    }

    $pdo = vaultora_db();
    try {
        $pdo->beginTransaction();
        $newVersion = (int) $file['current_version'] + 1;

        $pdo->prepare('UPDATE vault_file_versions SET is_current = 0 WHERE file_id = ?')->execute([$fileId]);

        $vstmt = $pdo->prepare(
            'INSERT INTO vault_file_versions (file_id, user_id, version_number, stored_name, size_bytes, mime_type, is_current, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        $vstmt->execute([$fileId, $userId, $newVersion, $stored, $size, $mime]);

        $ustmt = $pdo->prepare(
            'UPDATE vault_files
             SET original_name = ?, stored_name = ?, mime_type = ?, extension = ?, size_bytes = ?, current_version = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $ustmt->execute([$original, $stored, $mime, $ext, $size, $newVersion, $fileId, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($dest);
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not save revision.'];
    }

    vaultora_recalculate_storage($userId);
    vaultora_log_activity($userId, 'upload_revision', 'file', $fileId, 'Uploaded revision of: ' . $original);

    return ['success' => true, 'message' => 'New version uploaded.', 'id' => $fileId];
}

function vaultora_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
        default => 'Upload failed.',
    };
}

function vaultora_list_files(int $userId, ?int $folderId = null, bool $starredOnly = false, int $limit = 100, int $offset = 0): array
{
    $sql = 'SELECT f.*, fo.name AS folder_name
            FROM vault_files f
            LEFT JOIN vault_folders fo ON fo.id = f.folder_id
            WHERE f.user_id = ? AND f.is_deleted = 0 AND f.is_purged = 0';

    if ($starredOnly) {
        $sql .= ' AND f.is_starred = 1';
    } elseif ($folderId === null) {
        $sql .= ' AND f.folder_id IS NULL';
    } else {
        $sql .= ' AND f.folder_id = ?';
    }

    $sql .= ' ORDER BY f.updated_at DESC LIMIT ? OFFSET ?';
    $stmt = vaultora_db()->prepare($sql);
    $bind = 1;
    $stmt->bindValue($bind++, $userId, PDO::PARAM_INT);
    if (!$starredOnly && $folderId !== null) {
        $stmt->bindValue($bind++, $folderId, PDO::PARAM_INT);
    }
    $stmt->bindValue($bind++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bind++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function vaultora_count_files(int $userId, bool $includeDeleted = false): int
{
    $sql = 'SELECT COUNT(*) AS c FROM vault_files WHERE user_id = ? AND is_purged = 0';
    if (!$includeDeleted) {
        $sql .= ' AND is_deleted = 0';
    }
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute([$userId]);
    return (int) ($stmt->fetch()['c'] ?? 0);
}

function vaultora_rename_file(int $fileId, int $userId, string $name): array
{
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }
    $error = vaultora_validate_name($name);
    if ($error) {
        return ['success' => false, 'message' => $error];
    }

    // Preserve extension if user omitted it
    $oldExt = $file['extension'];
    $newExt = vaultora_extension($name);
    if ($newExt === '' && $oldExt !== '') {
        $name .= '.' . $oldExt;
    } elseif ($newExt !== '' && vaultora_is_blocked_extension($newExt)) {
        return ['success' => false, 'message' => 'That file extension is not allowed.'];
    }

    $name = vaultora_safe_filename($name);
    $folderId = $file['folder_id'] !== null ? (int) $file['folder_id'] : null;
    if (vaultora_file_name_exists($userId, $name, $folderId, $fileId)) {
        return ['success' => false, 'message' => 'A file with that name already exists here.'];
    }

    $ext = vaultora_extension($name);
    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET original_name = ?, extension = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$name, $ext, $fileId, $userId]);
    vaultora_log_activity($userId, 'rename_file', 'file', $fileId, 'Renamed file to: ' . $name);

    return ['success' => true, 'message' => 'File renamed.'];
}

function vaultora_move_file(int $fileId, int $userId, ?int $destinationId): array
{
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }

    if ($destinationId !== null && $destinationId > 0) {
        $dest = vaultora_get_folder($destinationId, $userId);
        if (!$dest) {
            return ['success' => false, 'message' => 'Destination folder not found.'];
        }
    } else {
        $destinationId = null;
    }

    if (vaultora_file_name_exists($userId, $file['original_name'], $destinationId, $fileId)) {
        return ['success' => false, 'message' => 'A file with that name already exists in the destination.'];
    }

    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET folder_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$destinationId, $fileId, $userId]);
    vaultora_log_activity($userId, 'move_file', 'file', $fileId, 'Moved file: ' . $file['original_name']);

    return ['success' => true, 'message' => 'File moved.'];
}

function vaultora_soft_delete_file(int $fileId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }
    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$fileId, $userId]);
    vaultora_log_activity($userId, 'delete', 'file', $fileId, 'Moved file to trash: ' . $file['original_name']);
    return ['success' => true, 'message' => 'File moved to trash.'];
}

function vaultora_restore_file(int $fileId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId, true);
    if (!$file || !(int) $file['is_deleted']) {
        return ['success' => false, 'message' => 'Deleted file not found.'];
    }

    $folderId = $file['folder_id'] !== null ? (int) $file['folder_id'] : null;
    if ($folderId !== null) {
        $folder = vaultora_get_folder($folderId, $userId);
        if (!$folder) {
            $folderId = null;
        }
    }

    $name = $file['original_name'];
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $i = 1;
    while (vaultora_file_name_exists($userId, $name, $folderId)) {
        $name = $base . ' (' . $i . ')' . ($ext !== '' ? '.' . $ext : '');
        $i++;
    }

    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET is_deleted = 0, deleted_at = NULL, folder_id = ?, original_name = ?, updated_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$folderId, $name, $fileId, $userId]);
    vaultora_log_activity($userId, 'restore', 'file', $fileId, 'Restored file: ' . $name);

    return ['success' => true, 'message' => 'File restored.'];
}

function vaultora_purge_file(int $fileId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId, true);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }

    $pdo = vaultora_db();
    try {
        $pdo->beginTransaction();

        $vstmt = $pdo->prepare('SELECT stored_name FROM vault_file_versions WHERE file_id = ? AND user_id = ?');
        $vstmt->execute([$fileId, $userId]);
        $names = $vstmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare('DELETE FROM vault_file_tags WHERE file_id = ?')->execute([$fileId]);
        $pdo->prepare('DELETE FROM vault_shares WHERE resource_type = ? AND resource_id = ? AND user_id = ?')
            ->execute(['file', $fileId, $userId]);
        $pdo->prepare('DELETE FROM vault_file_versions WHERE file_id = ?')->execute([$fileId]);
        $pdo->prepare('DELETE FROM vault_files WHERE id = ? AND user_id = ?')->execute([$fileId, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not permanently delete file.'];
    }

    $names[] = $file['stored_name'];
    foreach (array_unique($names) as $storedName) {
        $path = vaultora_resolve_stored_path($userId, (string) $storedName);
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    vaultora_recalculate_storage($userId);
    vaultora_log_activity($userId, 'permanent_delete', 'file', $fileId, 'Permanently deleted: ' . $file['original_name']);

    return ['success' => true, 'message' => 'File permanently deleted.'];
}

function vaultora_purge_folder(int $folderId, int $userId): array
{
    $folder = vaultora_get_folder($folderId, $userId, true);
    if (!$folder) {
        return ['success' => false, 'message' => 'Folder not found.'];
    }

    // Purge child folders first
    $childStmt = vaultora_db()->prepare('SELECT id FROM vault_folders WHERE parent_id = ? AND user_id = ?');
    $childStmt->execute([$folderId, $userId]);
    foreach ($childStmt->fetchAll() as $child) {
        vaultora_purge_folder((int) $child['id'], $userId);
    }

    $files = vaultora_db()->prepare('SELECT id FROM vault_files WHERE folder_id = ? AND user_id = ?');
    $files->execute([$folderId, $userId]);
    foreach ($files->fetchAll() as $f) {
        vaultora_purge_file((int) $f['id'], $userId);
    }

    vaultora_db()->prepare('DELETE FROM vault_shares WHERE resource_type = ? AND resource_id = ? AND user_id = ?')
        ->execute(['folder', $folderId, $userId]);
    vaultora_db()->prepare('DELETE FROM vault_folders WHERE id = ? AND user_id = ?')->execute([$folderId, $userId]);

    vaultora_log_activity($userId, 'permanent_delete', 'folder', $folderId, 'Permanently deleted folder: ' . $folder['name']);
    return ['success' => true, 'message' => 'Folder permanently deleted.'];
}

function vaultora_set_file_star(int $fileId, int $userId, bool $starred): array
{
    $file = vaultora_get_file($fileId, $userId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }
    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET is_starred = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$starred ? 1 : 0, $fileId, $userId]);
    vaultora_log_activity($userId, $starred ? 'star' : 'unstar', 'file', $fileId, ($starred ? 'Starred' : 'Unstarred') . ' file');
    return ['success' => true, 'message' => $starred ? 'File starred.' : 'File unstarred.', 'starred' => $starred];
}

function vaultora_touch_file_access(int $fileId, int $userId): void
{
    $stmt = vaultora_db()->prepare(
        'UPDATE vault_files SET last_accessed_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$fileId, $userId]);
}

function vaultora_send_file_download(array $file, int $userId, bool $inline = false): never
{
    $path = vaultora_resolve_stored_path($userId, $file['stored_name']);
    if (!$path || !is_file($path)) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    vaultora_touch_file_access((int) $file['id'], $userId);
    vaultora_log_activity($userId, 'download', 'file', (int) $file['id'], 'Downloaded: ' . $file['original_name']);

    $mime = $file['mime_type'] ?: 'application/octet-stream';
    $disposition = $inline ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $file['original_name']) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    readfile($path);
    exit;
}

function vaultora_recent_files(int $userId, int $limit = 12): array
{
    $stmt = vaultora_db()->prepare(
        'SELECT f.*, fo.name AS folder_name
         FROM vault_files f
         LEFT JOIN vault_folders fo ON fo.id = f.folder_id
         WHERE f.user_id = ? AND f.is_deleted = 0 AND f.is_purged = 0
         ORDER BY COALESCE(f.last_accessed_at, f.updated_at) DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function vaultora_search_user_items(int $userId, string $query, int $limit = 50): array
{
    $query = trim($query);
    if ($query === '') {
        return ['files' => [], 'folders' => []];
    }
    $like = '%' . $query . '%';

    $fstmt = vaultora_db()->prepare(
        'SELECT f.*, fo.name AS folder_name
         FROM vault_files f
         LEFT JOIN vault_folders fo ON fo.id = f.folder_id
         WHERE f.user_id = ? AND f.is_deleted = 0 AND f.is_purged = 0 AND f.original_name LIKE ?
         ORDER BY f.updated_at DESC
         LIMIT ?'
    );
    $fstmt->bindValue(1, $userId, PDO::PARAM_INT);
    $fstmt->bindValue(2, $like, PDO::PARAM_STR);
    $fstmt->bindValue(3, $limit, PDO::PARAM_INT);
    $fstmt->execute();

    $dstmt = vaultora_db()->prepare(
        'SELECT * FROM vault_folders
         WHERE user_id = ? AND is_deleted = 0 AND name LIKE ?
         ORDER BY updated_at DESC
         LIMIT ?'
    );
    $dstmt->bindValue(1, $userId, PDO::PARAM_INT);
    $dstmt->bindValue(2, $like, PDO::PARAM_STR);
    $dstmt->bindValue(3, $limit, PDO::PARAM_INT);
    $dstmt->execute();

    return ['files' => $fstmt->fetchAll(), 'folders' => $dstmt->fetchAll()];
}

function vaultora_trash_items(int $userId): array
{
    $files = vaultora_db()->prepare(
        'SELECT f.*, fo.name AS folder_name
         FROM vault_files f
         LEFT JOIN vault_folders fo ON fo.id = f.folder_id AND fo.is_deleted = 0
         WHERE f.user_id = ? AND f.is_deleted = 1 AND f.is_purged = 0
         ORDER BY f.deleted_at DESC'
    );
    $files->execute([$userId]);

    $folders = vaultora_db()->prepare(
        'SELECT * FROM vault_folders WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC'
    );
    $folders->execute([$userId]);

    return ['files' => $files->fetchAll(), 'folders' => $folders->fetchAll()];
}

function vaultora_empty_trash(int $userId): array
{
    $items = vaultora_trash_items($userId);
    foreach ($items['folders'] as $folder) {
        vaultora_purge_folder((int) $folder['id'], $userId);
    }
    foreach ($items['files'] as $file) {
        // May already be purged with folder
        $still = vaultora_get_file((int) $file['id'], $userId, true);
        if ($still) {
            vaultora_purge_file((int) $file['id'], $userId);
        }
    }
    return ['success' => true, 'message' => 'Trash emptied.'];
}

function vaultora_purge_expired_trash(): int
{
    $days = vaultora_trash_retention_days();
    $stmt = vaultora_db()->prepare(
        'SELECT id, user_id FROM vault_files
         WHERE is_deleted = 1 AND is_purged = 0 AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$days]);
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        $result = vaultora_purge_file((int) $row['id'], (int) $row['user_id']);
        if ($result['success']) {
            $count++;
        }
    }

    $fst = vaultora_db()->prepare(
        'SELECT id, user_id FROM vault_folders
         WHERE is_deleted = 1 AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $fst->execute([$days]);
    foreach ($fst->fetchAll() as $row) {
        vaultora_purge_folder((int) $row['id'], (int) $row['user_id']);
        $count++;
    }

    return $count;
}

function vaultora_file_details(int $fileId, int $userId): ?array
{
    $file = vaultora_get_file($fileId, $userId, true);
    if (!$file) {
        return null;
    }
    $tags = vaultora_tags_for_file($fileId, $userId);
    $share = vaultora_active_share_for('file', $fileId, $userId);
    $versions = vaultora_list_versions($fileId, $userId);
    $folderName = null;
    if ($file['folder_id']) {
        $folder = vaultora_get_folder((int) $file['folder_id'], $userId, true);
        $folderName = $folder['name'] ?? null;
    }
    $owner = vaultora_db()->prepare('SELECT display_name, email FROM vault_users WHERE id = ?');
    $owner->execute([$userId]);
    $ownerRow = $owner->fetch() ?: [];

    return [
        'file' => $file,
        'tags' => $tags,
        'share' => $share,
        'versions' => $versions,
        'folder_name' => $folderName,
        'owner' => $ownerRow,
    ];
}
