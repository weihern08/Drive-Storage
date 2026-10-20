<?php
/**
 * Vaultora — User administration helpers
 */

declare(strict_types=1);

function vaultora_count_admins(): int
{
    $stmt = vaultora_db()->query("SELECT COUNT(*) AS c FROM vault_users WHERE role = 'admin' AND status = 'active'");
    return (int) ($stmt->fetch()['c'] ?? 0);
}

function vaultora_admin_list_users(string $search = '', int $limit = 50, int $offset = 0): array
{
    $sql = 'SELECT id, email, display_name, role, status, storage_quota_bytes, storage_used_bytes, created_at, last_login_at
            FROM vault_users WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (email LIKE ? OR display_name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function vaultora_admin_count_users(string $search = ''): int
{
    $sql = 'SELECT COUNT(*) AS c FROM vault_users WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (email LIKE ? OR display_name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $stmt = vaultora_db()->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetch()['c'] ?? 0);
}

function vaultora_admin_get_user(int $id): ?array
{
    $stmt = vaultora_db()->prepare(
        'SELECT id, email, display_name, role, status, storage_quota_bytes, storage_used_bytes, created_at, updated_at, last_login_at
         FROM vault_users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function vaultora_admin_create_user(
    string $displayName,
    string $email,
    string $password,
    string $role,
    int $quotaBytes,
    string $status = 'active'
): array {
    $displayName = trim($displayName);
    $email = strtolower(trim($email));
    $role = $role === 'admin' ? 'admin' : 'user';
    $status = $status === 'inactive' ? 'inactive' : 'active';

    if ($displayName === '' || mb_strlen($displayName) > 120) {
        return ['success' => false, 'message' => 'Invalid display name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    if ($quotaBytes < 1024 * 1024) {
        return ['success' => false, 'message' => 'Quota must be at least 1 MB.'];
    }

    $check = vaultora_db()->prepare('SELECT id FROM vault_users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = vaultora_db()->prepare(
        'INSERT INTO vault_users (email, password_hash, display_name, role, status, storage_quota_bytes, storage_used_bytes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
    );
    $stmt->execute([$email, $hash, $displayName, $role, $status, $quotaBytes]);
    $id = (int) vaultora_db()->lastInsertId();

    $actor = vaultora_current_user();
    vaultora_log_activity($actor ? (int) $actor['id'] : null, 'create_user', 'user', $id, 'Created user ' . $email);
    vaultora_user_storage_dir($id);

    return ['success' => true, 'message' => 'User created.', 'id' => $id];
}

function vaultora_admin_update_user(
    int $id,
    int $actorId,
    string $displayName,
    string $email,
    string $role,
    string $status,
    int $quotaBytes
): array {
    $user = vaultora_admin_get_user($id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    $displayName = trim($displayName);
    $email = strtolower(trim($email));
    $role = $role === 'admin' ? 'admin' : 'user';
    $status = $status === 'inactive' ? 'inactive' : 'active';

    if ($id === $actorId && $status === 'inactive') {
        return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
    }
    if ($id === $actorId && $role !== 'admin') {
        return ['success' => false, 'message' => 'You cannot remove your own administrator role.'];
    }
    if ($user['role'] === 'admin' && ($role !== 'admin' || $status !== 'active') && vaultora_count_admins() <= 1) {
        return ['success' => false, 'message' => 'Cannot demote or deactivate the only active administrator.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $displayName === '') {
        return ['success' => false, 'message' => 'Invalid profile details.'];
    }
    if ($quotaBytes < 1024 * 1024) {
        return ['success' => false, 'message' => 'Quota must be at least 1 MB.'];
    }

    $check = vaultora_db()->prepare('SELECT id FROM vault_users WHERE email = ? AND id <> ? LIMIT 1');
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Email already in use.'];
    }

    $stmt = vaultora_db()->prepare(
        'UPDATE vault_users
         SET display_name = ?, email = ?, role = ?, status = ?, storage_quota_bytes = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$displayName, $email, $role, $status, $quotaBytes, $id]);
    vaultora_log_activity($actorId, 'edit_user', 'user', $id, 'Updated user ' . $email);

    return ['success' => true, 'message' => 'User updated.'];
}

function vaultora_admin_reset_password(int $id, int $actorId, string $password): array
{
    $user = vaultora_admin_get_user($id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    vaultora_db()->prepare('UPDATE vault_users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$hash, $id]);
    vaultora_log_activity($actorId, 'reset_password', 'user', $id, 'Reset password for ' . $user['email']);
    return ['success' => true, 'message' => 'Password reset successfully.'];
}

function vaultora_admin_delete_user(int $id, int $actorId): array
{
    $user = vaultora_admin_get_user($id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    if ($id === $actorId) {
        return ['success' => false, 'message' => 'You cannot delete your own account.'];
    }
    if ($user['role'] === 'admin' && vaultora_count_admins() <= 1) {
        return ['success' => false, 'message' => 'Cannot delete the only active administrator.'];
    }

    // Purge files physically
    $files = vaultora_db()->prepare('SELECT id FROM vault_files WHERE user_id = ?');
    $files->execute([$id]);
    foreach ($files->fetchAll() as $f) {
        vaultora_purge_file((int) $f['id'], $id);
    }

    $dir = VAULTORA_STORAGE_PATH . DIRECTORY_SEPARATOR . 'u' . $id;
    if (is_dir($dir)) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    vaultora_db()->prepare('DELETE FROM vault_users WHERE id = ?')->execute([$id]);
    vaultora_log_activity($actorId, 'delete_user', 'user', $id, 'Deleted user ' . $user['email']);

    return ['success' => true, 'message' => 'User deleted.'];
}

function vaultora_admin_stats(): array
{
    $pdo = vaultora_db();
    $users = (int) $pdo->query('SELECT COUNT(*) AS c FROM vault_users')->fetch()['c'];
    $active = (int) $pdo->query("SELECT COUNT(*) AS c FROM vault_users WHERE status = 'active'")->fetch()['c'];
    $inactive = $users - $active;
    $files = (int) $pdo->query('SELECT COUNT(*) AS c FROM vault_files WHERE is_purged = 0 AND is_deleted = 0')->fetch()['c'];
    $storage = (int) $pdo->query('SELECT COALESCE(SUM(storage_used_bytes), 0) AS s FROM vault_users')->fetch()['s'];
    $capacity = (int) $pdo->query('SELECT COALESCE(SUM(storage_quota_bytes), 0) AS s FROM vault_users')->fetch()['s'];

    return compact('users', 'active', 'inactive', 'files', 'storage', 'capacity');
}
