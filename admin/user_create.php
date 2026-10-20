<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $quota = vaultora_parse_bytes(vaultora_post_string('quota', '5 GB'));
    if ($quota <= 0) {
        $quota = vaultora_default_quota_bytes();
    }
    $result = vaultora_admin_create_user(
        vaultora_post_string('display_name'),
        vaultora_post_string('email'),
        (string) ($_POST['password'] ?? ''),
        vaultora_post_string('role', 'user'),
        $quota,
        vaultora_post_string('status', 'active')
    );
    vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    if ($result['success']) {
        vaultora_redirect('admin/users.php');
    }
}

$pageTitle = 'Create user';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Create user</h1>
        <p>Only administrators can provision accounts.</p>
    </div>
</div>

<div class="panel-card" style="max-width:720px">
    <form method="post" class="vstack gap-3">
        <?= vaultora_csrf_field() ?>
        <div>
            <label class="form-label" for="display_name">Full name</label>
            <input class="form-control" id="display_name" name="display_name" required>
        </div>
        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required>
        </div>
        <div>
            <label class="form-label" for="password">Temporary password</label>
            <input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="quota">Storage quota</label>
                <input class="form-control" id="quota" name="quota" value="5 GB" placeholder="e.g. 10 GB">
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-vault" type="submit">Create account</button>
            <a class="btn btn-outline-secondary" href="<?= e(vaultora_url('admin/users.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
