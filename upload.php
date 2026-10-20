<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
vaultora_require_login();
vaultora_flash('info', 'Use the Upload button to add files to your vault.');
vaultora_redirect('files.php');
