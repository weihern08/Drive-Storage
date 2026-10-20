<?php
/**
 * Vaultora — CSRF protection
 */

declare(strict_types=1);

function vaultora_csrf_token(): string
{
    if (empty($_SESSION[VAULTORA_CSRF_KEY]) || !is_string($_SESSION[VAULTORA_CSRF_KEY])) {
        $_SESSION[VAULTORA_CSRF_KEY] = vaultora_random_token(32);
    }
    return $_SESSION[VAULTORA_CSRF_KEY];
}

function vaultora_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(vaultora_csrf_token()) . '">';
}

function vaultora_json_body(): array
{
    if (isset($GLOBALS['_vaultora_json_body']) && is_array($GLOBALS['_vaultora_json_body'])) {
        return $GLOBALS['_vaultora_json_body'];
    }
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    $GLOBALS['_vaultora_json_body'] = is_array($json) ? $json : [];
    return $GLOBALS['_vaultora_json_body'];
}

function vaultora_csrf_verify(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (($token === null || $token === '') && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = vaultora_json_body();
            if (isset($json['_csrf'])) {
                $token = $json['_csrf'];
            }
        }
    }
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION[VAULTORA_CSRF_KEY] ?? '';
    return is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function vaultora_require_csrf(): void
{
    if (!vaultora_csrf_verify()) {
        if (vaultora_wants_json()) {
            vaultora_json(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 419);
        }
        vaultora_flash('danger', 'Invalid security token. Please try again.');
        $ref = $_SERVER['HTTP_REFERER'] ?? vaultora_url('dashboard.php');
        header('Location: ' . $ref);
        exit;
    }
}

function vaultora_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json')
        || strcasecmp($requested, 'XMLHttpRequest') === 0
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}
