<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();
$id = vaultora_get_int('id');
$user = vaultora_admin_get_user($id);
if (!$user) {
    vaultora_flash('danger', 'User not found.');
    vaultora_redirect('admin/users.php');
}

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $action = vaultora_post_string('action', 'update');
    if ($action === 'update') {
        $quota = vaultora_parse_bytes(vaultora_post_string('quota'));
        $result = vaultora_admin_update_user(
            $id,
            (int) $vaultUser['id'],
            vaultora_post_string('display_name'),
            vaultora_post_string('email'),
            vaultora_post_string('role'),
            vaultora_post_string('status'),
            $quota > 0 ? $quota : (int) $user['storage_quota_bytes']
        );
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    if ($action === 'reset') {
        $result = vaultora_admin_reset_password($id, (int) $vaultUser['id'], (string) ($_POST['password'] ?? ''));
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    if ($action === 'delete') {
        $result = vaultora_admin_delete_user($id, (int) $vaultUser['id']);
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
        if ($result['success']) {
            vaultora_redirect('admin/users.php');
        }
    }
    vaultora_redirect('admin/user_edit.php?id=' . $id);
}

$user = vaultora_admin_get_user($id);
$pageTitle = 'Edit user';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Edit user</h1>
        <p><?= e($user['email']) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel-card">
            <form method="post" class="vstack gap-3">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <div>
                    <label class="form-label" for="display_name">Full name</label>
                    <input class="form-control" id="display_name" name="display_name" value="<?= e($user['display_name']) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="quota">Storage quota</label>
                        <input class="form-control" id="quota" name="quota" value="<?= e(vaultora_format_bytes((int) $user['storage_quota_bytes'], 0)) ?>">
                    </div>
                </div>
                <p class="small text-secondary mb-0">Used: <?= e(vaultora_format_bytes((int) $user['storage_used_bytes'])) ?></p>
                <button class="btn btn-vault align-self-start" type="submit">Save changes</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel-card">
            <h2>Reset password</h2>
            <form method="post" class="vstack gap-3">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <input class="form-control" type="password" name="password" minlength="8" required placeholder="New password" autocomplete="new-password">
                <button class="btn btn-outline-vault" type="submit">Reset password</button>
            </form>
        </div>
        <div class="panel-card">
            <h2>Danger zone</h2>
            <form method="post" onsubmit="return confirm('Delete this user and all of their files?');">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-outline-danger" type="submit">Delete user</button>
            </form>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
