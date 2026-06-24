<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/storage.php';
require_once dirname(__DIR__) . '/lib/btzap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

$required = ['name', 'whatsapp', 'company', 'advertises'];

foreach ($required as $field) {
    if (trim((string) ($payload[$field] ?? '')) === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => "Campo obrigatório: {$field}."]);
        exit;
    }
}

try {
    $lead = crm_create_lead($payload);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o lead no CRM.']);
    exit;
}

btzap_send_lead_notification($lead);

// Integração e-mail: disparar notificação aqui ou chamar uma automação externa.
// Integração CRM externo: sincronizar este lead com HubSpot, Kommo, Pipedrive, Notion etc.

http_response_code(201);
echo json_encode(['ok' => true, 'lead_id' => $lead['id']]);
