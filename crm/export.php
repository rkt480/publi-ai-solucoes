<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

$leads = crm_read_leads();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads-publi-ai.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Nome', 'WhatsApp', 'Empresa', 'Segmento', 'Anuncia', 'Status', 'Mensagem', 'Observações', 'Recebido em']);

foreach ($leads as $lead) {
    fputcsv($output, [
        $lead['name'] ?? '',
        $lead['whatsapp'] ?? '',
        $lead['company'] ?? '',
        $lead['segment'] ?? '',
        $lead['advertises'] ?? '',
        $lead['status'] ?? '',
        $lead['message'] ?? '',
        $lead['notes'] ?? '',
        $lead['created_at'] ?? '',
    ]);
}

fclose($output);
