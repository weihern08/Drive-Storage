<?php
/**
 * Vaultora — System settings
 */

declare(strict_types=1);

function vaultora_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = vaultora_db()->prepare('SELECT setting_value FROM vault_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string) $row['setting_value'] : $default;
    } catch (Throwable) {
        $cache[$key] = $default;
    }

    return $cache[$key];
}

function vaultora_set_setting(string $key, string $value): void
{
    $stmt = vaultora_db()->prepare(
        'INSERT INTO vault_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $stmt->execute([$key, $value]);
}

function vaultora_app_name(): string
{
    return vaultora_setting('app_name', VAULTORA_DEFAULT_APP_NAME) ?: VAULTORA_DEFAULT_APP_NAME;
}

function vaultora_max_upload_bytes(): int
{
    return max(1, (int) (vaultora_setting('max_upload_bytes', (string) VAULTORA_DEFAULT_MAX_UPLOAD) ?? VAULTORA_DEFAULT_MAX_UPLOAD));
}

function vaultora_default_quota_bytes(): int
{
    return max(1, (int) (vaultora_setting('default_quota_bytes', (string) VAULTORA_DEFAULT_QUOTA) ?? VAULTORA_DEFAULT_QUOTA));
}

function vaultora_trash_retention_days(): int
{
    return max(1, (int) (vaultora_setting('trash_retention_days', (string) VAULTORA_DEFAULT_TRASH_DAYS) ?? VAULTORA_DEFAULT_TRASH_DAYS));
}

function vaultora_allowed_extensions(): array
{
    $raw = vaultora_setting('allowed_extensions', implode(',', VAULTORA_DEFAULT_ALLOWED_EXTENSIONS));
    $parts = array_filter(array_map(static function ($e) {
        return strtolower(trim($e));
    }, explode(',', (string) $raw)));
    return array_values(array_unique($parts));
}

function vaultora_extension_allowed(string $ext): bool
{
    $ext = strtolower($ext);
    if ($ext === '' || vaultora_is_blocked_extension($ext)) {
        return false;
    }
    return in_array($ext, vaultora_allowed_extensions(), true);
}

function vaultora_all_settings(): array
{
    $stmt = vaultora_db()->query('SELECT setting_key, setting_value FROM vault_settings');
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}
