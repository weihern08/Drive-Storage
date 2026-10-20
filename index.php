<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (vaultora_current_user()) {
    vaultora_redirect('dashboard.php');
}
vaultora_redirect('login.php');
