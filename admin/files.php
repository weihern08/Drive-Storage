<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();
$page = max(1, vaultora_get_int('page', 1));
$perPage = 30;

$countStmt = vaultora_db()->query('SELECT COUNT(*) AS c FROM vault_files WHERE is_purged = 0');
$total = (int) $countStmt->fetch()['c'];
$pager = vaultora_paginate($total, $page, $perPage);

$stmt = vaultora_db()->prepare(
    'SELECT f.*, u.display_name, u.email, fo.name AS folder_name
     FROM vault_files f
     INNER JOIN vault_users u ON u.id = f.user_id
     LEFT JOIN vault_folders fo ON fo.id = f.folder_id
     WHERE f.is_purged = 0
     ORDER BY f.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['offset'], PDO::PARAM_INT);
$stmt->execute();
$files = $stmt->fetchAll();

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $fileId = vaultora_post_int('file_id');
    $ownerId = vaultora_post_int('owner_id');
    $mode = vaultora_post_string('mode');
    if ($mode === 'purge') {
        $result = vaultora_purge_file($fileId, $ownerId);
        vaultora_log_activity((int) $vaultUser['id'], 'admin_purge_file', 'file', $fileId, 'Admin permanently deleted file');
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    vaultora_redirect('admin/files.php?page=' . $page);
}

$pageTitle = 'System files';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>System files</h1>
        <p>Inspect files across all user accounts.</p>
    </div>
</div>

<div class="panel-card">
    <div class="table-responsive">
        <table class="table-vault">
            <thead>
            <tr><th>File</th><th>Owner</th><th>Folder</th><th>Type</th><th>Size</th><th>Uploaded</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><?= e($file['original_name']) ?></td>
                    <td><?= e($file['display_name']) ?><div class="small text-secondary"><?= e($file['email']) ?></div></td>
                    <td><?= e($file['folder_name'] ?? 'Root') ?></td>
                    <td><?= e(strtoupper((string) $file['extension']) ?: 'FILE') ?></td>
                    <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                    <td><?= e($file['created_at']) ?></td>
                    <td><?= !empty($file['is_deleted']) ? 'Trash' : 'Active' ?></td>
                    <td class="text-end">
                        <form method="post" onsubmit="return confirm('Permanently delete this file?');">
                            <?= vaultora_csrf_field() ?>
                            <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                            <input type="hidden" name="owner_id" value="<?= (int) $file['user_id'] ?>">
                            <input type="hidden" name="mode" value="purge">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Purge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
        <div class="d-flex gap-2 mt-3">
            <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                <a class="btn btn-sm <?= $i === $pager['page'] ? 'btn-vault' : 'btn-outline-vault' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
