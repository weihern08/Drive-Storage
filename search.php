<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$q = vaultora_get_string('q');
$results = $q !== '' ? vaultora_search_user_items((int) $vaultUser['id'], $q) : ['files' => [], 'folders' => []];
$pageTitle = 'Search';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Search</h1>
        <p>Find files and folders in your vault.</p>
    </div>
</div>

<div class="panel-card">
    <form method="get" class="row g-2 align-items-end mb-4">
        <div class="col-md-9">
            <label class="form-label" for="q">Query</label>
            <input class="form-control form-control-lg" id="q" name="q" value="<?= e($q) ?>" placeholder="Search by name…">
        </div>
        <div class="col-md-3">
            <button class="btn btn-vault w-100 btn-lg" type="submit">Search</button>
        </div>
    </form>

    <?php if ($q === ''): ?>
        <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h3>Start searching</h3><p>Enter a file or folder name above.</p></div>
    <?php elseif (!$results['files'] && !$results['folders']): ?>
        <div class="empty-state"><i class="fa-regular fa-circle-xmark"></i><h3>No matches</h3><p>Nothing matched “<?= e($q) ?>” in your vault.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-vault">
                <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Size</th><th>Modified</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($results['folders'] as $folder): ?>
                    <tr>
                        <td><a href="<?= e(vaultora_url('files.php?folder=' . (int) $folder['id'])) ?>"><i class="fa-solid fa-folder me-2"></i><?= e($folder['name']) ?></a></td>
                        <td>Folder</td>
                        <td>Vault</td>
                        <td>—</td>
                        <td><?= e($folder['updated_at'] ?? '') ?></td>
                        <td class="text-end"><?php $itemType = 'folder'; $item = $folder; require __DIR__ . '/includes/partials/file_actions.php'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($results['files'] as $file): ?>
                    <tr>
                        <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></td>
                        <td>File</td>
                        <td><?= e($file['folder_name'] ?? 'My Storage') ?></td>
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

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
