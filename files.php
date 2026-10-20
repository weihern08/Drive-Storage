<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];
$folderId = vaultora_get_int('folder') ?: null;
$view = vaultora_get_string('view', $_COOKIE['vaultora_view'] ?? 'grid');
if (!in_array($view, ['grid', 'list'], true)) {
    $view = 'grid';
}
setcookie('vaultora_view', $view, ['expires' => time() + 86400 * 365, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax']);

if ($folderId) {
    $currentFolder = vaultora_get_folder($folderId, $userId);
    if (!$currentFolder) {
        vaultora_flash('danger', 'Folder not found.');
        vaultora_redirect('files.php');
    }
}

$crumbs = vaultora_folder_breadcrumbs($userId, $folderId);
$folders = vaultora_list_folders($userId, $folderId);
$files = vaultora_list_files($userId, $folderId, false, 200, 0);
$folderIdForModals = $folderId;
$pageTitle = 'My files';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>My files</h1>
        <p>Browse, organize, and manage your vault.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-vault" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
        <button class="btn btn-outline-vault" data-bs-toggle="modal" data-bs-target="#folderModal"><i class="fa-solid fa-folder-plus"></i> New folder</button>
    </div>
</div>

<nav class="breadcrumb-vault" aria-label="Breadcrumb">
    <?php foreach ($crumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
        <?php if ($crumb['id'] === null): ?>
            <a href="<?= e(vaultora_url('files.php')) ?>"><?= e($crumb['name']) ?></a>
        <?php elseif ($i === count($crumbs) - 1): ?>
            <span aria-current="page"><?= e($crumb['name']) ?></span>
        <?php else: ?>
            <a href="<?= e(vaultora_url('files.php?folder=' . (int) $crumb['id'])) ?>"><?= e($crumb['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<div class="view-toolbar">
    <div class="text-secondary small"><?= count($folders) ?> folders · <?= count($files) ?> files</div>
    <div class="btn-group">
        <a class="btn btn-sm <?= $view === 'grid' ? 'btn-vault' : 'btn-outline-vault' ?>" href="<?= e(vaultora_url('files.php?' . http_build_query(array_filter(['folder' => $folderId, 'view' => 'grid'])))) ?>">Grid</a>
        <a class="btn btn-sm <?= $view === 'list' ? 'btn-vault' : 'btn-outline-vault' ?>" href="<?= e(vaultora_url('files.php?' . http_build_query(array_filter(['folder' => $folderId, 'view' => 'list'])))) ?>">List</a>
    </div>
</div>

<div class="panel-card">
<?php if (!$folders && !$files): ?>
    <div class="empty-state">
        <i class="fa-regular fa-folder-open"></i>
        <h3>This folder is empty</h3>
        <p>Upload files or create a nested folder to organize your work.</p>
        <button class="btn btn-vault" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload file</button>
    </div>
<?php elseif ($view === 'grid'): ?>
    <div class="file-grid">
        <?php foreach ($folders as $folder): ?>
            <div class="file-card folder">
                <button class="star-btn <?= !empty($folder['is_starred']) ? 'active' : '' ?>" type="button" data-action="star" data-type="folder" data-id="<?= (int) $folder['id'] ?>" data-starred="<?= !empty($folder['is_starred']) ? '1' : '0' ?>" aria-label="Star folder"><i class="fa-solid fa-star"></i></button>
                <a href="<?= e(vaultora_url('files.php?folder=' . (int) $folder['id'])) ?>">
                    <div class="icon-wrap" style="color:<?= e($folder['color']) ?>"><i class="fa-solid fa-folder"></i></div>
                    <div class="name"><?= e($folder['name']) ?></div>
                    <div class="meta">Folder · <?= e(date('M j, Y', strtotime($folder['updated_at'] ?? $folder['created_at']))) ?></div>
                </a>
                <div class="mt-2"><?php $itemType = 'folder'; $item = $folder; require __DIR__ . '/includes/partials/file_actions.php'; ?></div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($files as $file): ?>
            <div class="file-card">
                <button class="star-btn <?= !empty($file['is_starred']) ? 'active' : '' ?>" type="button" data-action="star" data-type="file" data-id="<?= (int) $file['id'] ?>" data-starred="<?= !empty($file['is_starred']) ? '1' : '0' ?>" aria-label="Star file"><i class="fa-solid fa-star"></i></button>
                <div class="icon-wrap"><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?>"></i></div>
                <div class="name"><?= e($file['original_name']) ?></div>
                <div class="meta"><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?> · <?= e(date('M j, Y', strtotime($file['updated_at'] ?? $file['created_at']))) ?></div>
                <div class="mt-2"><?php $itemType = 'file'; $item = $file; require __DIR__ . '/includes/partials/file_actions.php'; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table-vault">
            <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Modified</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($folders as $folder): ?>
                <tr>
                    <td><a href="<?= e(vaultora_url('files.php?folder=' . (int) $folder['id'])) ?>"><i class="fa-solid fa-folder me-2" style="color:<?= e($folder['color']) ?>"></i><?= e($folder['name']) ?></a></td>
                    <td>Folder</td>
                    <td>—</td>
                    <td><?= e($folder['updated_at'] ?? '') ?></td>
                    <td><?= !empty($folder['is_starred']) ? 'Starred' : 'Active' ?></td>
                    <td class="text-end"><?php $itemType = 'folder'; $item = $folder; require __DIR__ . '/includes/partials/file_actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></td>
                    <td><?= e(strtoupper((string) $file['extension']) ?: 'FILE') ?></td>
                    <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                    <td><?= e($file['updated_at'] ?? '') ?></td>
                    <td><?= !empty($file['is_starred']) ? 'Starred' : 'Active' ?></td>
                    <td class="text-end"><?php $itemType = 'file'; $item = $file; require __DIR__ . '/includes/partials/file_actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
