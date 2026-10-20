<?php
/**
 * Vaultora — Folder operations
 */

declare(strict_types=1);

function vaultora_get_folder(int $folderId, int $userId, bool $includeDeleted = false): ?array
{
    $sql = 'SELECT * FROM vault_folders WHERE id = ? AND user_id = ?';
    if (!$includeDeleted) {
        $sql .= ' AND is_deleted = 0';
    }
    $sql .= ' LIMIT 1';
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute([$folderId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_create_folder(int $userId, string $name, ?int $parentId = null, string $color = '#0d9488'): array
{
    $error = vaultora_validate_name($name);
    if ($error) {
        return ['success' => false, 'message' => $error];
    }

    if ($parentId !== null && $parentId > 0) {
        $parent = vaultora_get_folder($parentId, $userId);
        if (!$parent) {
            return ['success' => false, 'message' => 'Parent folder not found.'];
        }
    } else {
        $parentId = null;
    }

    if (vaultora_folder_name_exists($userId, $name, $parentId)) {
        return ['success' => false, 'message' => 'A folder with that name already exists here.'];
    }

    $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#0d9488';

    $stmt = vaultora_db()->prepare(
        'INSERT INTO vault_folders (user_id, parent_id, name, color, is_starred, is_deleted, created_at, updated_at)
         VALUES (?, ?, ?, ?, 0, 0, NOW(), NOW())'
    );
    $stmt->execute([$userId, $parentId, $name, $color]);
    $id = (int) vaultora_db()->lastInsertId();

    vaultora_log_activity($userId, 'create_folder', 'folder', $id, 'Created folder: ' . $name);

    return ['success' => true, 'message' => 'Folder created.', 'id' => $id];
}

function vaultora_folder_name_exists(int $userId, string $name, ?int $parentId, ?int $excludeId = null): bool
{
    $sql = 'SELECT id FROM vault_folders WHERE user_id = ? AND name = ? AND is_deleted = 0 AND ';
    $params = [$userId, $name];
    if ($parentId === null) {
        $sql .= 'parent_id IS NULL';
    } else {
        $sql .= 'parent_id = ?';
        $params[] = $parentId;
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

function vaultora_rename_folder(int $folderId, int $userId, string $name): array
{
    $folder = vaultora_get_folder($folderId, $userId);
    if (!$folder) {
        return ['success' => false, 'message' => 'Folder not found.'];
    }
    $error = vaultora_validate_name($name);
    if ($error) {
        return ['success' => false, 'message' => $error];
    }
    $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
    if (vaultora_folder_name_exists($userId, $name, $parentId, $folderId)) {
        return ['success' => false, 'message' => 'A folder with that name already exists here.'];
    }

    $stmt = vaultora_db()->prepare('UPDATE vault_folders SET name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$name, $folderId, $userId]);
    vaultora_log_activity($userId, 'rename_folder', 'folder', $folderId, 'Renamed folder to: ' . $name);

    return ['success' => true, 'message' => 'Folder renamed.'];
}

function vaultora_folder_is_descendant(int $userId, int $possibleAncestorId, int $folderId): bool
{
    $current = $folderId;
    $guard = 0;
    while ($current > 0 && $guard < 100) {
        if ($current === $possibleAncestorId) {
            return true;
        }
        $folder = vaultora_get_folder($current, $userId, true);
        if (!$folder || $folder['parent_id'] === null) {
            return false;
        }
        $current = (int) $folder['parent_id'];
        $guard++;
    }
    return false;
}

function vaultora_move_folder(int $folderId, int $userId, ?int $destinationId): array
{
    $folder = vaultora_get_folder($folderId, $userId);
    if (!$folder) {
        return ['success' => false, 'message' => 'Folder not found.'];
    }

    if ($destinationId !== null && $destinationId > 0) {
        if ($destinationId === $folderId) {
            return ['success' => false, 'message' => 'Cannot move a folder into itself.'];
        }
        $dest = vaultora_get_folder($destinationId, $userId);
        if (!$dest) {
            return ['success' => false, 'message' => 'Destination folder not found.'];
        }
        if (vaultora_folder_is_descendant($userId, $folderId, $destinationId)) {
            return ['success' => false, 'message' => 'Cannot move a folder into its own subfolder.'];
        }
    } else {
        $destinationId = null;
    }

    if (vaultora_folder_name_exists($userId, $folder['name'], $destinationId, $folderId)) {
        return ['success' => false, 'message' => 'A folder with that name already exists in the destination.'];
    }

    $stmt = vaultora_db()->prepare(
        'UPDATE vault_folders SET parent_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$destinationId, $folderId, $userId]);
    vaultora_log_activity($userId, 'move_folder', 'folder', $folderId, 'Moved folder');

    return ['success' => true, 'message' => 'Folder moved.'];
}

function vaultora_soft_delete_folder(int $folderId, int $userId): array
{
    $folder = vaultora_get_folder($folderId, $userId);
    if (!$folder) {
        return ['success' => false, 'message' => 'Folder not found.'];
    }

    $pdo = vaultora_db();
    $pdo->beginTransaction();
    try {
        vaultora_soft_delete_folder_tree($folderId, $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not delete folder.'];
    }

    vaultora_log_activity($userId, 'delete', 'folder', $folderId, 'Moved folder to trash: ' . $folder['name']);
    return ['success' => true, 'message' => 'Folder moved to trash.'];
}

function vaultora_soft_delete_folder_tree(int $folderId, int $userId): void
{
    $childStmt = vaultora_db()->prepare(
        'SELECT id FROM vault_folders WHERE parent_id = ? AND user_id = ? AND is_deleted = 0'
    );
    $childStmt->execute([$folderId, $userId]);
    foreach ($childStmt->fetchAll() as $child) {
        vaultora_soft_delete_folder_tree((int) $child['id'], $userId);
    }

    $fileStmt = vaultora_db()->prepare(
        'UPDATE vault_files SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW()
         WHERE folder_id = ? AND user_id = ? AND is_deleted = 0'
    );
    $fileStmt->execute([$folderId, $userId]);

    $folderStmt = vaultora_db()->prepare(
        'UPDATE vault_folders SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $folderStmt->execute([$folderId, $userId]);
}

function vaultora_restore_folder(int $folderId, int $userId): array
{
    $folder = vaultora_get_folder($folderId, $userId, true);
    if (!$folder || !(int) $folder['is_deleted']) {
        return ['success' => false, 'message' => 'Deleted folder not found.'];
    }

    // If parent is deleted, restore to root
    $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
    if ($parentId !== null) {
        $parent = vaultora_get_folder($parentId, $userId);
        if (!$parent) {
            $parentId = null;
        }
    }

    $name = $folder['name'];
    $base = $name;
    $i = 1;
    while (vaultora_folder_name_exists($userId, $name, $parentId)) {
        $name = $base . ' (' . $i . ')';
        $i++;
    }

    $pdo = vaultora_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE vault_folders SET is_deleted = 0, deleted_at = NULL, parent_id = ?, name = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$parentId, $name, $folderId, $userId]);

        // Restore direct files that were deleted with the folder (same deleted_at window loosely: all deleted files in folder)
        $files = $pdo->prepare(
            'UPDATE vault_files SET is_deleted = 0, deleted_at = NULL, updated_at = NOW()
             WHERE folder_id = ? AND user_id = ? AND is_deleted = 1 AND is_purged = 0'
        );
        $files->execute([$folderId, $userId]);

        // Restore child folders recursively
        vaultora_restore_child_folders($folderId, $userId);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Could not restore folder.'];
    }

    vaultora_log_activity($userId, 'restore', 'folder', $folderId, 'Restored folder: ' . $name);
    return ['success' => true, 'message' => 'Folder restored.'];
}

function vaultora_restore_child_folders(int $parentId, int $userId): void
{
    $stmt = vaultora_db()->prepare(
        'SELECT id FROM vault_folders WHERE parent_id = ? AND user_id = ? AND is_deleted = 1'
    );
    $stmt->execute([$parentId, $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        $upd = vaultora_db()->prepare(
            'UPDATE vault_folders SET is_deleted = 0, deleted_at = NULL, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $upd->execute([$id, $userId]);
        $files = vaultora_db()->prepare(
            'UPDATE vault_files SET is_deleted = 0, deleted_at = NULL, updated_at = NOW()
             WHERE folder_id = ? AND user_id = ? AND is_deleted = 1 AND is_purged = 0'
        );
        $files->execute([$id, $userId]);
        vaultora_restore_child_folders($id, $userId);
    }
}

function vaultora_folder_breadcrumbs(int $userId, ?int $folderId): array
{
    $crumbs = [['id' => null, 'name' => 'My Storage']];
    if (!$folderId) {
        return $crumbs;
    }

    $chain = [];
    $current = $folderId;
    $guard = 0;
    while ($current && $guard < 50) {
        $folder = vaultora_get_folder($current, $userId, true);
        if (!$folder) {
            break;
        }
        array_unshift($chain, ['id' => (int) $folder['id'], 'name' => $folder['name']]);
        $current = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : 0;
        $guard++;
    }

    return array_merge($crumbs, $chain);
}

function vaultora_list_folders(int $userId, ?int $parentId = null, bool $starredOnly = false): array
{
    $sql = 'SELECT * FROM vault_folders WHERE user_id = ? AND is_deleted = 0';
    $params = [$userId];

    if ($starredOnly) {
        $sql .= ' AND is_starred = 1';
    } else {
        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = ?';
            $params[] = $parentId;
        }
    }

    $sql .= ' ORDER BY name ASC';
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function vaultora_folder_tree(int $userId, ?int $excludeId = null): array
{
    $stmt = vaultora_db()->prepare(
        'SELECT id, parent_id, name FROM vault_folders WHERE user_id = ? AND is_deleted = 0 ORDER BY name ASC'
    );
    $stmt->execute([$userId]);
    $all = $stmt->fetchAll();

    $byParent = [];
    foreach ($all as $row) {
        if ($excludeId && (int) $row['id'] === $excludeId) {
            continue;
        }
        if ($excludeId && vaultora_folder_is_descendant($userId, $excludeId, (int) $row['id'])) {
            continue;
        }
        $pid = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
        $byParent[$pid][] = $row;
    }

    $build = function (int $parent) use (&$build, &$byParent): array {
        $nodes = [];
        foreach ($byParent[$parent] ?? [] as $row) {
            $nodes[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'children' => $build((int) $row['id']),
            ];
        }
        return $nodes;
    };

    return $build(0);
}

function vaultora_set_folder_star(int $folderId, int $userId, bool $starred): array
{
    $folder = vaultora_get_folder($folderId, $userId);
    if (!$folder) {
        return ['success' => false, 'message' => 'Folder not found.'];
    }
    $stmt = vaultora_db()->prepare(
        'UPDATE vault_folders SET is_starred = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$starred ? 1 : 0, $folderId, $userId]);
    vaultora_log_activity($userId, $starred ? 'star' : 'unstar', 'folder', $folderId, ($starred ? 'Starred' : 'Unstarred') . ' folder');
    return ['success' => true, 'message' => $starred ? 'Folder starred.' : 'Folder unstarred.', 'starred' => $starred];
}

function vaultora_count_folders(int $userId): int
{
    $stmt = vaultora_db()->prepare('SELECT COUNT(*) AS c FROM vault_folders WHERE user_id = ? AND is_deleted = 0');
    $stmt->execute([$userId]);
    return (int) ($stmt->fetch()['c'] ?? 0);
}
