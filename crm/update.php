<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_update_lead((string) ($_POST['id'] ?? ''), [
        'status' => $_POST['status'] ?? 'novo',
        'notes' => $_POST['notes'] ?? '',
    ]);
}

header('Location: index.php');
exit;
