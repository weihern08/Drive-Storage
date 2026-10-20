<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];
$files = vaultora_list_files($userId, null, true, 200, 0);
$folders = vaultora_list_folders($userId, null, true);
$pageTitle = 'Starred';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Starred</h1>
        <p>Important files and folders you marked for quick access.</p>
    </div>
</div>

<div class="panel-card">
<?php if (!$files && !$folders): ?>
    <div class="empty-state"><i class="fa-regular fa-star"></i><h3>No starred items</h3><p>Star important files for quick access.</p></div>
<?php else: ?>
    <div class="file-grid">
        <?php foreach ($folders as $folder): ?>
            <div class="file-card folder">
                <div class="icon-wrap" style="color:<?= e($folder['color']) ?>"><i class="fa-solid fa-folder"></i></div>
                <div class="name"><?= e($folder['name']) ?></div>
                <div class="meta">Folder</div>
                <div class="mt-2"><?php $itemType = 'folder'; $item = $folder; require __DIR__ . '/includes/partials/file_actions.php'; ?></div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($files as $file): ?>
            <div class="file-card">
                <div class="icon-wrap"><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?>"></i></div>
                <div class="name"><?= e($file['original_name']) ?></div>
                <div class="meta"><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></div>
                <div class="mt-2"><?php $itemType = 'file'; $item = $file; require __DIR__ . '/includes/partials/file_actions.php'; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
