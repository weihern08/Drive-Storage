<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $action = vaultora_post_string('action');
    if ($action === 'create') {
        $result = vaultora_create_tag($userId, vaultora_post_string('name'), vaultora_post_string('color', '#0d9488'));
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    if ($action === 'delete') {
        $result = vaultora_delete_tag(vaultora_post_int('tag_id'), $userId);
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    vaultora_redirect('tags.php');
}

$tagId = vaultora_get_int('tag');
$tags = vaultora_list_tags($userId);
$files = $tagId ? vaultora_files_by_tag($tagId, $userId) : [];
$pageTitle = 'Tags';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Tags</h1>
        <p>Organize files with lightweight colored labels.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <h2>Your tags</h2>
            <form method="post" class="vstack gap-2 mb-3">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input class="form-control" name="name" placeholder="Tag name" required maxlength="60">
                <input class="form-control form-control-color" type="color" name="color" value="#0d9488">
                <button class="btn btn-vault" type="submit">Create tag</button>
            </form>
            <?php if (!$tags): ?>
                <div class="empty-state py-3"><h3>No tags yet</h3><p>Create tags like Important, Work, or Assignment.</p></div>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($tags as $tag): ?>
                        <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <a href="<?= e(vaultora_url('tags.php?tag=' . (int) $tag['id'])) ?>">
                                <span class="tag-chip" style="background:<?= e($tag['color']) ?>22;color:<?= e($tag['color']) ?>"><?= e($tag['name']) ?></span>
                                <span class="small text-secondary ms-1"><?= (int) $tag['file_count'] ?></span>
                            </a>
                            <form method="post" onsubmit="return confirm('Delete this tag?');">
                                <?= vaultora_csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="tag_id" value="<?= (int) $tag['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel-card">
            <h2><?= $tagId ? 'Tagged files' : 'Select a tag' ?></h2>
            <?php if (!$tagId): ?>
                <div class="empty-state"><i class="fa-solid fa-tags"></i><h3>Choose a tag</h3><p>Select a tag on the left to filter files.</p></div>
            <?php elseif (!$files): ?>
                <div class="empty-state"><h3>No files with this tag</h3><p>Attach this tag from a file’s details panel.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-vault">
                        <thead><tr><th>Name</th><th>Size</th><th>Modified</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td><?= e($file['original_name']) ?></td>
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
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
