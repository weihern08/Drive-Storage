<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$files = vaultora_recent_files((int) $vaultUser['id'], 40);
$pageTitle = 'Recent';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Recent</h1>
        <p>Recently uploaded, modified, or accessed files.</p>
    </div>
</div>

<div class="panel-card">
<?php if (!$files): ?>
    <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><h3>No recent activity</h3><p>Files you open or upload will appear here.</p></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table-vault">
            <thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><i class="fa-solid <?= e(vaultora_file_icon($file['extension'])) ?> me-2"></i><?= e($file['original_name']) ?></td>
                    <td><?= e(strtoupper((string) $file['extension']) ?: 'FILE') ?></td>
                    <td><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></td>
                    <td><?= e($file['last_accessed_at'] ?: $file['updated_at']) ?></td>
                    <td class="text-end"><?php $itemType = 'file'; $item = $file; require __DIR__ . '/includes/partials/file_actions.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
