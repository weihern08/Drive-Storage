<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];
$storage = vaultora_storage_stats($vaultUser);
$recent = vaultora_recent_files($userId, 8);
$starredFiles = vaultora_list_files($userId, null, true, 6, 0);
$starredFolders = vaultora_list_folders($userId, null, true);
$fileCount = vaultora_count_files($userId);
$folderCount = vaultora_count_folders($userId);

$pageTitle = 'Dashboard';
$folderIdForModals = null;
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Hello, <?= e(explode(' ', $vaultUser['display_name'])[0]) ?></h1>
        <p>Your personal vault overview and quick actions.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><i class="fa-solid fa-file"></i><div class="stat-label">Total files</div><div class="stat-value"><?= e((string) $fileCount) ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-folder"></i><div class="stat-label">Folders</div><div class="stat-value"><?= e((string) $folderCount) ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-hard-drive"></i><div class="stat-label">Storage used</div><div class="stat-value"><?= e($storage['used_label']) ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-gauge"></i><div class="stat-label">Remaining</div><div class="stat-value"><?= e($storage['remaining_label']) ?></div></div>
</div>

<div class="panel-card mb-4">
    <h2>Storage</h2>
    <div class="d-flex justify-content-between small mb-2">
        <span><?= e($storage['used_label']) ?> / <?= e($storage['quota_label']) ?> used</span>
        <span><?= e((string) $storage['percent']) ?>%</span>
    </div>
    <div class="progress storage-progress" style="height:0.85rem">
        <div class="progress-bar" style="width:<?= e((string) $storage['percent']) ?>%"></div>
    </div>
    <?php if ($storage['percent'] >= 90): ?>
        <div class="alert alert-warning mt-3 mb-0">Your storage is almost full.</div>
    <?php endif; ?>
</div>

<div class="panel-card">
    <h2>Quick actions</h2>
    <div class="quick-actions">
        <button type="button" class="quick-action" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fa-solid fa-cloud-arrow-up"></i><span>Upload file</span></button>
        <button type="button" class="quick-action" data-bs-toggle="modal" data-bs-target="#folderModal"><i class="fa-solid fa-folder-plus"></i><span>New folder</span></button>
        <a class="quick-action" href="<?= e(vaultora_url('recent.php')) ?>"><i class="fa-solid fa-clock"></i><span>View recent</span></a>
        <a class="quick-action" href="<?= e(vaultora_url('starred.php')) ?>"><i class="fa-solid fa-star"></i><span>View starred</span></a>
        <a class="quick-action" href="<?= e(vaultora_url('trash.php')) ?>"><i class="fa-solid fa-trash"></i><span>View trash</span></a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Recent files</h2>
                <a href="<?= e(vaultora_url('recent.php')) ?>">See all</a>
            </div>
            <?php if (!$recent): ?>
                <div class="empty-state"><i class="fa-regular fa-folder-open"></i><h3>No files yet</h3><p>Upload your first file to get started.</p>
                    <button class="btn btn-vault" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload file</button></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-vault">
                        <thead><tr><th>Name</th><th>Size</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $file): ?>
                            <tr>
                                <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2 text-secondary"></i><?= e($file['original_name']) ?></td>
                                <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                                <td><?= e($file['updated_at'] ?? '') ?></td>
                                <td class="text-end"><?php $itemType = 'file'; $item = $file; require __DIR__ . '/includes/partials/file_actions.php'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Starred</h2>
                <a href="<?= e(vaultora_url('starred.php')) ?>">See all</a>
            </div>
            <?php if (!$starredFiles && !$starredFolders): ?>
                <div class="empty-state py-4"><i class="fa-regular fa-star"></i><h3>No starred items</h3><p>Star important files for quick access.</p></div>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($starredFolders as $folder): ?>
                        <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <a href="<?= e(vaultora_url('files.php?folder=' . (int) $folder['id'])) ?>"><i class="fa-solid fa-folder me-2" style="color:<?= e($folder['color']) ?>"></i><?= e($folder['name']) ?></a>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($starredFiles as $file): ?>
                        <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></span>
                            <a class="small" href="<?= e(vaultora_url('download.php?id=' . (int) $file['id'])) ?>">Download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
