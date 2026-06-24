<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $steps = [];
    $postedSteps = is_array($_POST['steps'] ?? null) ? $_POST['steps'] : [];

    foreach ($postedSteps as $step) {
        $value = max(0, (int) ($step['delay_value'] ?? 0));
        $unit = (string) ($step['delay_unit'] ?? 'minutes');
        $multiplier = match ($unit) {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        };

        $steps[] = [
            'delay_minutes' => $value * $multiplier,
            'message' => $step['message'] ?? '',
        ];
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($id > 0) {
        crm_update_followup_flow($id, $name, $description, $steps);
    } else {
        crm_create_followup_flow($name, $description, $steps);
    }
}

header('Location: followups.php');
exit;
