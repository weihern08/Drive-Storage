<?php
/**
 * Vaultora — Activity / audit logging
 */

declare(strict_types=1);

function vaultora_log_activity(
    ?int $userId,
    string $action,
    ?string $resourceType = null,
    ?int $resourceId = null,
    ?string $description = null
): void {
    try {
        $stmt = vaultora_db()->prepare(
            'INSERT INTO vault_activity_logs (user_id, action, resource_type, resource_id, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            mb_substr($action, 0, 80),
            $resourceType !== null ? mb_substr($resourceType, 0, 40) : null,
            $resourceId,
            $description !== null ? mb_substr($description, 0, 500) : null,
            vaultora_client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('Vaultora activity log failed: ' . $e->getMessage());
    }
}

function vaultora_fetch_activity(int $limit = 50, int $offset = 0, ?int $userId = null): array
{
    if ($userId !== null) {
        $stmt = vaultora_db()->prepare(
            'SELECT l.*, u.display_name, u.email
             FROM vault_activity_logs l
             LEFT JOIN vault_users u ON u.id = l.user_id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = vaultora_db()->prepare(
        'SELECT l.*, u.display_name, u.email
         FROM vault_activity_logs l
         LEFT JOIN vault_users u ON u.id = l.user_id
         ORDER BY l.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
