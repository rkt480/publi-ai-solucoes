<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/btzap.php';

crm_require_login();

$items = crm_read_due_followups(20);
$sent = 0;
$failed = 0;

foreach ($items as $item) {
    $result = btzap_send_followup($item);

    if (($result['ok'] ?? false) === true) {
        crm_update_followup_queue_item((int) $item['id'], 'enviado');
        $sent++;
        continue;
    }

    crm_update_followup_queue_item((int) $item['id'], 'falhou', (string) ($result['error'] ?? 'Falha ao enviar.'));
    $failed++;
}

$result = [
    'processed' => count($items),
    'sent' => $sent,
    'failed' => $failed,
];

if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Follow-ups processados: {$result['processed']}\n";
echo "Enviados: {$result['sent']}\n";
echo "Falharam: {$result['failed']}\n";
