<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$portal = vaultora_current_user() ? vaultora_login_portal() : 'user';
vaultora_logout(true);
vaultora_redirect($portal === 'admin' ? 'admin/login.php' : 'login.php');
