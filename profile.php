<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];
$storage = vaultora_storage_stats($vaultUser);

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $action = vaultora_post_string('action');
    if ($action === 'profile') {
        $result = vaultora_update_profile($userId, vaultora_post_string('display_name'), vaultora_post_string('email'));
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    if ($action === 'password') {
        $result = vaultora_change_password(
            $userId,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        vaultora_flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    vaultora_redirect('profile.php');
}

$vaultUser = vaultora_current_user();
$pageTitle = 'Profile';
require __DIR__ . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Profile</h1>
        <p>Manage your account details and password.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="text-center mb-3">
                <div class="user-avatar mx-auto" style="width:72px;height:72px;font-size:1.6rem"><?= e(mb_strtoupper(mb_substr($vaultUser['display_name'], 0, 1))) ?></div>
                <h2 class="h4 mt-3 mb-1"><?= e($vaultUser['display_name']) ?></h2>
                <div class="text-secondary"><?= e($vaultUser['email']) ?></div>
                <span class="badge text-bg-secondary mt-2"><?= e(ucfirst($vaultUser['role'])) ?></span>
            </div>
            <div class="small text-secondary mb-1">Storage</div>
            <div class="progress storage-progress mb-1"><div class="progress-bar" style="width:<?= e((string) $storage['percent']) ?>%"></div></div>
            <div class="small"><?= e($storage['used_label']) ?> / <?= e($storage['quota_label']) ?></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel-card">
            <h2>Account details</h2>
            <form method="post" class="vstack gap-3">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div>
                    <label class="form-label" for="display_name">Display name</label>
                    <input class="form-control" id="display_name" name="display_name" value="<?= e($vaultUser['display_name']) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($vaultUser['email']) ?>" required>
                </div>
                <button class="btn btn-vault align-self-start" type="submit">Save profile</button>
            </form>
        </div>
        <div class="panel-card">
            <h2>Change password</h2>
            <form method="post" class="vstack gap-3">
                <?= vaultora_csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div>
                    <label class="form-label" for="current_password">Current password</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" required>
                </div>
                <div>
                    <label class="form-label" for="new_password">New password</label>
                    <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" required>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm new password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                <button class="btn btn-vault align-self-start" type="submit">Update password</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
