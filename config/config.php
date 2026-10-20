<?php
/**
 * Vaultora — Application configuration
 */

declare(strict_types=1);

if (!defined('VAULTORA_ROOT')) {
    define('VAULTORA_ROOT', dirname(__DIR__));
}

define('VAULTORA_APP_KEY', 'vaultora');
define('VAULTORA_VERSION', '1.0.0');

define('VAULTORA_STORAGE_PATH', VAULTORA_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'vault');
define('VAULTORA_VERSIONS_PATH', VAULTORA_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'versions');
define('VAULTORA_TEMP_PATH', VAULTORA_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'temp');
define('VAULTORA_LOCK_FILE', VAULTORA_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'installed.lock');

define('VAULTORA_SESSION_NAME', 'VAULTORASESSID');
define('VAULTORA_CSRF_KEY', '_vaultora_csrf');

/** Default settings used when DB settings are unavailable */
define('VAULTORA_DEFAULT_APP_NAME', 'Vaultora');
define('VAULTORA_DEFAULT_MAX_UPLOAD', 50 * 1024 * 1024); // 50 MB
define('VAULTORA_DEFAULT_QUOTA', 5 * 1024 * 1024 * 1024); // 5 GB
define('VAULTORA_DEFAULT_TRASH_DAYS', 30);

define('VAULTORA_BLOCKED_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'cgi', 'pl', 'py', 'vbs', 'msi', 'dll',
    'htaccess', 'htpasswd', 'jsp', 'asp', 'aspx', 'shtml',
]);

define('VAULTORA_DEFAULT_ALLOWED_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    'mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov', 'avi',
    'zip', 'rar', '7z', 'tar', 'gz',
    'json', 'xml', 'md', 'html', 'css', 'js',
]);
