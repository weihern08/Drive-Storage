<?php
/**
 * Vaultora — Shared helpers
 */

declare(strict_types=1);

function vaultora_is_installed(): bool
{
    return is_file(VAULTORA_LOCK_FILE);
}

function vaultora_is_install_request(): bool
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($script, '/install/');
}

function vaultora_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Normalize when running from /admin or /api or /install
    foreach (['/admin', '/api', '/install'] as $segment) {
        if (str_ends_with($script, $segment)) {
            $script = substr($script, 0, -strlen($segment));
            break;
        }
    }
    $script = rtrim($script, '/');
    $base = $script === '' || $script === '.' ? '' : $script;
    return $base;
}

function vaultora_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = vaultora_base_path();
    return ($base === '' ? '' : $base) . '/' . $path;
}

function vaultora_absolute_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . vaultora_url($path);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vaultora_redirect(string $path): never
{
    header('Location: ' . vaultora_url($path));
    exit;
}

function vaultora_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function vaultora_flash(string $type, string $message): void
{
    $_SESSION['_vaultora_flash'][] = ['type' => $type, 'message' => $message];
}

function vaultora_get_flashes(): array
{
    $flashes = $_SESSION['_vaultora_flash'] ?? [];
    unset($_SESSION['_vaultora_flash']);
    return $flashes;
}

function vaultora_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function vaultora_format_bytes(int|float $bytes, int $precision = 2): string
{
    $bytes = (float) max(0, $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $power = min($power, count($units) - 1);
    return round($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
}

function vaultora_parse_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || !preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB)?$/i', $value, $m)) {
        return 0;
    }
    $num = (float) $m[1];
    $unit = strtoupper($m[2] ?? 'B');
    $map = ['B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4];
    return (int) round($num * (1024 ** ($map[$unit] ?? 0)));
}

function vaultora_safe_filename(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\p{L}\p{N}\.\-_ \(\)\[\]]+/u', '_', $name) ?? 'file';
    $name = trim($name, '._ ');
    return $name !== '' ? mb_substr($name, 0, 180) : 'file';
}

function vaultora_extension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function vaultora_is_blocked_extension(string $ext): bool
{
    return in_array(strtolower($ext), VAULTORA_BLOCKED_EXTENSIONS, true);
}

function vaultora_random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function vaultora_stored_filename(string $original): string
{
    $ext = vaultora_extension($original);
    $safeExt = $ext !== '' && !vaultora_is_blocked_extension($ext) ? '.' . $ext : '';
    return bin2hex(random_bytes(16)) . $safeExt;
}

function vaultora_validate_name(string $name, int $max = 180): ?string
{
    $name = trim($name);
    if ($name === '') {
        return 'Name cannot be empty.';
    }
    if (mb_strlen($name) > $max) {
        return 'Name is too long.';
    }
    if (preg_match('/[\\\\\/\:\*\?\"\<\>\|]/', $name)) {
        return 'Name contains invalid characters.';
    }
    return null;
}

function vaultora_paginate(int $total, int $page, int $perPage = 20): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function vaultora_file_icon(string $ext): string
{
    $ext = strtolower($ext);
    $map = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'csv' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
        'gif' => 'fa-file-image', 'webp' => 'fa-file-image', 'svg' => 'fa-file-image', 'bmp' => 'fa-file-image',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'ogg' => 'fa-file-audio',
        'mp4' => 'fa-file-video', 'webm' => 'fa-file-video', 'mov' => 'fa-file-video', 'avi' => 'fa-file-video',
        'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', '7z' => 'fa-file-zipper', 'tar' => 'fa-file-zipper', 'gz' => 'fa-file-zipper',
        'txt' => 'fa-file-lines', 'md' => 'fa-file-lines', 'json' => 'fa-file-code', 'xml' => 'fa-file-code',
        'html' => 'fa-file-code', 'css' => 'fa-file-code', 'js' => 'fa-file-code',
    ];
    return $map[$ext] ?? 'fa-file';
}

function vaultora_is_previewable(string $ext, string $mime = ''): bool
{
    $ext = strtolower($ext);
    $previewExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'md', 'csv', 'mp3', 'wav', 'ogg', 'mp4', 'webm'];
    if (in_array($ext, $previewExt, true)) {
        return true;
    }
    return str_starts_with($mime, 'image/') || str_starts_with($mime, 'text/') || $mime === 'application/pdf';
}

function vaultora_request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function vaultora_post_string(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function vaultora_post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function vaultora_get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function vaultora_get_string(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}
