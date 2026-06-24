<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_assign_followup_flow((string) ($_POST['lead_id'] ?? ''), (int) ($_POST['flow_id'] ?? 0));
}

header('Location: index.php');
exit;
