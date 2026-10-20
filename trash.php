<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
vaultora_purge_expired_trash();
$trash = vaultora_trash_items((int) $vaultUser['id']);
$pageTitle = 'Trash';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Trash</h1>
        <p>Deleted items are kept for <?= e((string) vaultora_trash_retention_days()) ?> days before permanent removal.</p>
    </div>
    <?php if ($trash['files'] || $trash['folders']): ?>
        <button class="btn btn-outline-danger" type="button" id="emptyTrashBtn">Empty trash</button>
    <?php endif; ?>
</div>

<div class="panel-card">
<?php if (!$trash['files'] && !$trash['folders']): ?>
    <div class="empty-state"><i class="fa-regular fa-trash-can"></i><h3>Your trash is empty</h3><p>Deleted files will appear here.</p></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table-vault">
            <thead><tr><th>Name</th><th>Type</th><th>Original location</th><th>Size</th><th>Deleted</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($trash['folders'] as $folder): ?>
                <tr>
                    <td><i class="fa-solid fa-folder me-2"></i><?= e($folder['name']) ?></td>
                    <td>Folder</td>
                    <td>—</td>
                    <td>—</td>
                    <td><?= e($folder['deleted_at'] ?? '') ?></td>
                    <td class="text-end"><?php $itemType = 'folder'; $item = $folder; $showTrashActions = true; require __DIR__ . '/includes/partials/file_actions.php'; unset($showTrashActions); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($trash['files'] as $file): ?>
                <tr>
                    <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></td>
                    <td>File</td>
                    <td><?= e($file['folder_name'] ?? 'My Storage') ?></td>
                    <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                    <td><?= e($file['deleted_at'] ?? '') ?></td>
                    <td class="text-end"><?php $itemType = 'file'; $item = $file; $showTrashActions = true; require __DIR__ . '/includes/partials/file_actions.php'; unset($showTrashActions); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
