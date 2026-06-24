<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/storage.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

crm_require_login();

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

$id = trim((string) ($payload['id'] ?? ''));
$status = trim((string) ($payload['status'] ?? ''));
$allowedStatuses = ['novo', 'contatado', 'followup', 'proposta', 'fechado', 'perdido'];

if ($id === '' || !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
    exit;
}

if (!crm_update_lead($id, ['status' => $status])) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Lead não encontrado.']);
    exit;
}

echo json_encode(['ok' => true]);
