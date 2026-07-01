<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/storage.php';
require_once dirname(__DIR__) . '/lib/btzap.php';
require_once dirname(__DIR__) . '/lib/meta-capi.php';
require_once dirname(__DIR__) . '/lib/security.php';

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

if (trim((string) ($payload['website'] ?? '')) !== '') {
    http_response_code(201);
    echo json_encode(['ok' => true]);
    exit;
}

if (crm_throttle_is_limited('lead-submit', 'public-form', 6, 600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Muitas tentativas. Tente novamente em alguns minutos.']);
    exit;
}

$required = ['name', 'whatsapp', 'company', 'segment', 'advertises', 'message'];

foreach ($required as $field) {
    if (trim((string) ($payload[$field] ?? '')) === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => "Campo obrigatório: {$field}."]);
        exit;
    }
}

crm_throttle_record('lead-submit', 'public-form', 600);

try {
    $lead = crm_create_lead($payload);
} catch (Throwable $error) {
    error_log('Erro ao salvar lead no CRM: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o lead no CRM.']);
    exit;
}

$whatsappResult = ['ok' => false, 'error' => 'Notificação não executada.'];
$metaResult = ['ok' => false, 'error' => 'Meta CAPI não executada.'];

try {
    $metaResult = meta_capi_send_lead_created($lead, $payload);

    if (($metaResult['ok'] ?? false) !== true && ($metaResult['skipped'] ?? false) !== true) {
        error_log('Erro Meta CAPI Lead ' . (string) $lead['id'] . ': ' . (string) ($metaResult['error'] ?? 'Erro desconhecido.'));
    }
} catch (Throwable $error) {
    error_log('Erro Meta CAPI Lead ' . (string) $lead['id'] . ': ' . $error->getMessage());
}

try {
    $whatsappResult = btzap_send_lead_notification($lead);
} catch (Throwable $error) {
    crm_update_whatsapp_status((string) $lead['id'], 'falhou', 'Erro ao enviar WhatsApp: ' . $error->getMessage());
    error_log('Erro BTZap no lead ' . (string) $lead['id'] . ': ' . $error->getMessage());
}

// Integração e-mail: disparar notificação aqui ou chamar uma automação externa.
// Integração CRM externo: sincronizar este lead com HubSpot, Kommo, Pipedrive, Notion etc.

http_response_code(201);
echo json_encode([
    'ok' => true,
    'lead_id' => $lead['id'],
    'whatsapp' => $whatsappResult,
    'meta' => $metaResult,
]);
