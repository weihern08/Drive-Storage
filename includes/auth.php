<?php
/**
 * Vaultora — Authentication & authorization
 */

declare(strict_types=1);

function vaultora_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(VAULTORA_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function vaultora_current_user(): ?array
{
    if (empty($_SESSION['vaultora_user_id'])) {
        return null;
    }

    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }

    try {
        $stmt = vaultora_db()->prepare(
            'SELECT id, email, display_name, role, status, storage_quota_bytes, storage_used_bytes, created_at, last_login_at
             FROM vault_users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['vaultora_user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user || $user['status'] !== 'active') {
            vaultora_logout(false);
            $user = null;
        }
    } catch (Throwable) {
        $user = null;
    }

    $cached = true;
    return $user;
}

/**
 * @param string $portal 'user' for personal vault login, 'admin' for admin console login
 */
function vaultora_login(string $email, string $password, string $portal = 'user'): array
{
    $portal = $portal === 'admin' ? 'admin' : 'user';
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Gmail and password are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid Gmail / email address.'];
    }

    $stmt = vaultora_db()->prepare(
        'SELECT id, email, password_hash, display_name, role, status FROM vault_users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        vaultora_log_activity(null, 'login_failed', 'user', null, 'Failed ' . $portal . ' login for ' . $email);
        return ['success' => false, 'message' => 'Invalid Gmail or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'This account is inactive. Contact an administrator.'];
    }

    if ($portal === 'admin' && ($user['role'] ?? '') !== 'admin') {
        vaultora_log_activity((int) $user['id'], 'login_failed', 'user', (int) $user['id'], 'Non-admin tried admin login');
        return ['success' => false, 'message' => 'Administrator access required. Use the user login page instead.'];
    }

    if ($portal === 'user' && ($user['role'] ?? '') === 'admin') {
        return [
            'success' => false,
            'message' => 'Administrators must sign in from the Admin login page.',
            'admin_login' => true,
        ];
    }

    session_regenerate_id(true);
    $_SESSION['vaultora_user_id'] = (int) $user['id'];
    $_SESSION['vaultora_role'] = $user['role'];
    $_SESSION['vaultora_portal'] = $portal;

    $upd = vaultora_db()->prepare('UPDATE vault_users SET last_login_at = NOW() WHERE id = ?');
    $upd->execute([(int) $user['id']]);

    vaultora_log_activity(
        (int) $user['id'],
        $portal === 'admin' ? 'admin_login' : 'login',
        'user',
        (int) $user['id'],
        $portal === 'admin' ? 'Administrator signed in' : 'User signed in'
    );

    return ['success' => true, 'user' => $user, 'portal' => $portal];
}

function vaultora_login_portal(): string
{
    $portal = $_SESSION['vaultora_portal'] ?? 'user';
    return $portal === 'admin' ? 'admin' : 'user';
}

function vaultora_is_admin_session(?array $user = null): bool
{
    return vaultora_is_admin($user) && vaultora_login_portal() === 'admin';
}

function vaultora_logout(bool $log = true): void
{
    $uid = isset($_SESSION['vaultora_user_id']) ? (int) $_SESSION['vaultora_user_id'] : null;
    if ($log && $uid) {
        try {
            vaultora_log_activity($uid, 'logout', 'user', $uid, 'User signed out');
        } catch (Throwable) {
            // ignore logging failures during logout
        }
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    vaultora_boot_session();
}

function vaultora_require_login(): array
{
    $user = vaultora_current_user();
    if (!$user) {
        if (vaultora_wants_json()) {
            vaultora_json(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        vaultora_flash('warning', 'Please sign in to continue.');
        vaultora_redirect('login.php');
    }

    // Admin console session cannot access the personal vault area
    if (vaultora_login_portal() === 'admin') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $inAdmin = str_contains($script, '/admin/');
        if (!$inAdmin) {
            if (vaultora_wants_json()) {
                vaultora_json(['success' => false, 'message' => 'Please sign in with a user account for the vault.'], 403);
            }
            vaultora_flash('warning', 'You are signed in as an administrator. Use Admin login for the console, or sign out and use the user login for the vault.');
            vaultora_redirect('admin/index.php');
        }
    }

    return $user;
}

function vaultora_require_admin(): array
{
    $user = vaultora_current_user();
    if (!$user) {
        if (vaultora_wants_json()) {
            vaultora_json(['success' => false, 'message' => 'Administrator authentication required.'], 401);
        }
        vaultora_flash('warning', 'Please sign in with an administrator account.');
        vaultora_redirect('admin/login.php');
    }

    if (($user['role'] ?? '') !== 'admin' || vaultora_login_portal() !== 'admin') {
        if (vaultora_wants_json()) {
            vaultora_json(['success' => false, 'message' => 'Administrator access required.'], 403);
        }
        vaultora_logout(false);
        vaultora_flash('danger', 'Please sign in through the Admin login page.');
        vaultora_redirect('admin/login.php');
    }

    return $user;
}

function vaultora_is_admin(?array $user = null): bool
{
    $user = $user ?? vaultora_current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function vaultora_change_password(int $userId, string $current, string $new, string $confirm): array
{
    if ($new === '' || strlen($new) < 8) {
        return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
    }
    if ($new !== $confirm) {
        return ['success' => false, 'message' => 'Password confirmation does not match.'];
    }

    $stmt = vaultora_db()->prepare('SELECT password_hash FROM vault_users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = vaultora_db()->prepare('UPDATE vault_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute([$hash, $userId]);
    vaultora_log_activity($userId, 'change_password', 'user', $userId, 'Password changed');

    return ['success' => true, 'message' => 'Password updated successfully.'];
}

function vaultora_update_profile(int $userId, string $displayName, string $email): array
{
    $displayName = trim($displayName);
    $email = strtolower(trim($email));
    if ($displayName === '' || mb_strlen($displayName) > 120) {
        return ['success' => false, 'message' => 'Please enter a valid display name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $check = vaultora_db()->prepare('SELECT id FROM vault_users WHERE email = ? AND id <> ? LIMIT 1');
    $check->execute([$email, $userId]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'That email is already in use.'];
    }

    $stmt = vaultora_db()->prepare(
        'UPDATE vault_users SET display_name = ?, email = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$displayName, $email, $userId]);

    return ['success' => true, 'message' => 'Profile updated successfully.'];
}
