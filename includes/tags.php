<?php
/**
 * Vaultora — Tag system
 */

declare(strict_types=1);

function vaultora_list_tags(int $userId): array
{
    $stmt = vaultora_db()->prepare(
        'SELECT t.*, COUNT(ft.file_id) AS file_count
         FROM vault_tags t
         LEFT JOIN vault_file_tags ft ON ft.tag_id = t.id
         WHERE t.user_id = ?
         GROUP BY t.id
         ORDER BY t.name ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function vaultora_get_tag(int $tagId, int $userId): ?array
{
    $stmt = vaultora_db()->prepare('SELECT * FROM vault_tags WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$tagId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_create_tag(int $userId, string $name, string $color = '#0d9488'): array
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 60) {
        return ['success' => false, 'message' => 'Enter a valid tag name.'];
    }
    $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#0d9488';

    $check = vaultora_db()->prepare('SELECT id FROM vault_tags WHERE user_id = ? AND name = ? LIMIT 1');
    $check->execute([$userId, $name]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Tag already exists.'];
    }

    $stmt = vaultora_db()->prepare(
        'INSERT INTO vault_tags (user_id, name, color, created_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$userId, $name, $color]);
    $id = (int) vaultora_db()->lastInsertId();

    return ['success' => true, 'message' => 'Tag created.', 'id' => $id, 'tag' => ['id' => $id, 'name' => $name, 'color' => $color]];
}

function vaultora_delete_tag(int $tagId, int $userId): array
{
    $tag = vaultora_get_tag($tagId, $userId);
    if (!$tag) {
        return ['success' => false, 'message' => 'Tag not found.'];
    }
    vaultora_db()->prepare('DELETE FROM vault_file_tags WHERE tag_id = ?')->execute([$tagId]);
    vaultora_db()->prepare('DELETE FROM vault_tags WHERE id = ? AND user_id = ?')->execute([$tagId, $userId]);
    return ['success' => true, 'message' => 'Tag deleted.'];
}

function vaultora_tags_for_file(int $fileId, int $userId): array
{
    $stmt = vaultora_db()->prepare(
        'SELECT t.* FROM vault_tags t
         INNER JOIN vault_file_tags ft ON ft.tag_id = t.id
         INNER JOIN vault_files f ON f.id = ft.file_id
         WHERE ft.file_id = ? AND t.user_id = ? AND f.user_id = ?
         ORDER BY t.name ASC'
    );
    $stmt->execute([$fileId, $userId, $userId]);
    return $stmt->fetchAll();
}

function vaultora_attach_tag(int $fileId, int $tagId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId);
    $tag = vaultora_get_tag($tagId, $userId);
    if (!$file || !$tag) {
        return ['success' => false, 'message' => 'File or tag not found.'];
    }
    $stmt = vaultora_db()->prepare(
        'INSERT IGNORE INTO vault_file_tags (file_id, tag_id) VALUES (?, ?)'
    );
    $stmt->execute([$fileId, $tagId]);
    return ['success' => true, 'message' => 'Tag attached.', 'tags' => vaultora_tags_for_file($fileId, $userId)];
}

function vaultora_detach_tag(int $fileId, int $tagId, int $userId): array
{
    $file = vaultora_get_file($fileId, $userId);
    $tag = vaultora_get_tag($tagId, $userId);
    if (!$file || !$tag) {
        return ['success' => false, 'message' => 'File or tag not found.'];
    }
    vaultora_db()->prepare('DELETE FROM vault_file_tags WHERE file_id = ? AND tag_id = ?')->execute([$fileId, $tagId]);
    return ['success' => true, 'message' => 'Tag removed.', 'tags' => vaultora_tags_for_file($fileId, $userId)];
}

function vaultora_files_by_tag(int $tagId, int $userId): array
{
    $tag = vaultora_get_tag($tagId, $userId);
    if (!$tag) {
        return [];
    }
    $stmt = vaultora_db()->prepare(
        'SELECT f.*, fo.name AS folder_name
         FROM vault_files f
         INNER JOIN vault_file_tags ft ON ft.file_id = f.id
         LEFT JOIN vault_folders fo ON fo.id = f.folder_id
         WHERE ft.tag_id = ? AND f.user_id = ? AND f.is_deleted = 0 AND f.is_purged = 0
         ORDER BY f.updated_at DESC'
    );
    $stmt->execute([$tagId, $userId]);
    return $stmt->fetchAll();
}
