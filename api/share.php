<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$input = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    ? (vaultora_json_body() ?: $_POST)
    : $_POST;

$action = (string) ($input['action'] ?? 'create');
$userId = (int) $user['id'];

if ($action === 'disable') {
    vaultora_json(vaultora_disable_share((int) ($input['share_id'] ?? 0), $userId));
}

$type = (string) ($input['type'] ?? '');
$id = (int) ($input['id'] ?? 0);
$password = isset($input['password']) ? (string) $input['password'] : null;

$expiry = (string) ($input['expiry'] ?? '');
$expiresAt = null;
if ($expiry === 'custom') {
    $expiresAt = (string) ($input['expires_at'] ?? '');
} elseif ($expiry !== '' && ctype_digit($expiry)) {
    $expiresAt = date('Y-m-d H:i:s', time() + ((int) $expiry * 86400));
}

vaultora_json(vaultora_create_share($userId, $type, $id, $password, $expiresAt));
