<?php
/**
 * Vaultora — Application bootstrap
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/folders.php';
require_once __DIR__ . '/files.php';
require_once __DIR__ . '/tags.php';
require_once __DIR__ . '/shares.php';
require_once __DIR__ . '/versions.php';
require_once __DIR__ . '/users.php';

vaultora_boot_session();

if (!vaultora_is_installed() && !vaultora_is_install_request()) {
    header('Location: ' . vaultora_url('install/'));
    exit;
}

if (vaultora_is_installed() && !vaultora_is_install_request()) {
    vaultora_ensure_storage_dirs();
}
