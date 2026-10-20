<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (vaultora_current_user()) {
    if (vaultora_login_portal() === 'admin') {
        vaultora_redirect('admin/index.php');
    }
    vaultora_redirect('dashboard.php');
}

$error = null;
$isAdminAttempt = false;
if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();
    $result = vaultora_login(vaultora_post_string('email'), (string) ($_POST['password'] ?? ''), 'user');
    if ($result['success']) {
        vaultora_redirect('dashboard.php');
    }
    $error = $result['message'];
    $isAdminAttempt = !empty($result['admin_login']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User sign in · <?= e(vaultora_app_name()) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(vaultora_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-card">
        <div class="brand-mark mb-4">
            <span class="brand-glyph"><i class="fa-solid fa-cube"></i></span>
            <div>
                <div class="brand-name"><?= e(vaultora_app_name()) ?></div>
                <div class="brand-tag">User vault login</div>
            </div>
        </div>
        <h1 class="auth-title">User sign in</h1>
        <p class="auth-lead">Please enter your <strong>gmail</strong> and <strong>password</strong>.</p>

        <div class="demo-creds mb-3">
            <div class="demo-creds-title">Demo accounts</div>
            <div class="demo-creds-row">
                <strong>User</strong>
                <span>gmail: <code>user@gmail.com</code></span>
                <span>password: <code>User123!</code></span>
            </div>
            <div class="demo-creds-row">
                <strong>Admin</strong>
                <span>gmail: <code>admin@gmail.com</code></span>
                <span>password: <code>Admin123!</code></span>
                <a href="<?= e(vaultora_url('admin/login.php')) ?>">Admin login →</a>
            </div>
        </div>

        <?php foreach (vaultora_get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
                <?php if ($isAdminAttempt): ?>
                    <div class="mt-2"><a class="alert-link" href="<?= e(vaultora_url('admin/login.php')) ?>">Go to Admin login</a></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3" autocomplete="on">
            <?= vaultora_csrf_field() ?>
            <div>
                <label class="form-label fw-semibold" for="email">gmail</label>
                <input class="form-control form-control-lg" type="email" id="email" name="email"
                       value="user@gmail.com" placeholder="gmail" required autofocus autocomplete="username">
            </div>
            <div>
                <label class="form-label fw-semibold" for="password">password</label>
                <input class="form-control form-control-lg" type="password" id="password" name="password"
                       value="User123!" placeholder="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-vault btn-lg w-100" type="submit">Sign in</button>
        </form>
        <p class="auth-footer mb-0">
            Administrator?
            <a href="<?= e(vaultora_url('admin/login.php')) ?>">Sign in to Admin console</a>
        </p>
        <p class="auth-footer">Accounts are created by administrators. Public registration is disabled.</p>
    </div>
</div>
</body>
</html>
