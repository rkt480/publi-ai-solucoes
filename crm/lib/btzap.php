<?php

declare(strict_types=1);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/settings.php';

function btzap_config(): array
{
    $config = require dirname(__DIR__) . '/config.php';
    return $config['btzap'] ?? [];
}

function btzap_is_configured(): bool
{
    $config = btzap_config();
    $token = trim((string) ($config['token'] ?? ''));

    return ($config['enabled'] ?? false) === true
        && $token !== ''
        && $token !== 'COLE_SEU_TOKEN_AQUI';
}

function btzap_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '55')) {
        return $digits;
    }

    return '55' . $digits;
}

function btzap_render_message(array $lead): string
{
    $config = btzap_config();
    $message = (string) ($config['lead_message'] ?? '');

    $replacements = [
        '{{name}}' => (string) ($lead['name'] ?? ''),
        '{{company}}' => (string) ($lead['company'] ?? ''),
        '{{segment}}' => (string) ($lead['segment'] ?? ''),
    ];

    return strtr($message, $replacements);
}

function btzap_render_internal_notification(array $lead): string
{
    $config = btzap_config();
    $message = (string) ($config['internal_notification_message'] ?? '');

    if ($message === '') {
        $message = "Novo lead recebido:\n\nNome: {{name}}\nWhatsApp: {{whatsapp}}\nEmpresa: {{company}}\nSite/Landing: {{segment}}\nControle dos leads: {{advertises}}\nNecessidade: {{message}}";
    }

    $replacements = [
        '{{name}}' => (string) ($lead['name'] ?? ''),
        '{{whatsapp}}' => (string) ($lead['whatsapp'] ?? ''),
        '{{company}}' => (string) ($lead['company'] ?? ''),
        '{{segment}}' => (string) ($lead['segment'] ?? ''),
        '{{advertises}}' => (string) ($lead['advertises'] ?? ''),
        '{{message}}' => (string) ($lead['message'] ?? ''),
    ];

    return strtr($message, $replacements);
}

function btzap_render_custom_message(string $message, array $lead): string
{
    $replacements = [
        '{{name}}' => (string) ($lead['name'] ?? ''),
        '{{company}}' => (string) ($lead['company'] ?? ''),
        '{{segment}}' => (string) ($lead['segment'] ?? ''),
    ];

    return strtr($message, $replacements);
}

function btzap_request(string $endpoint, array $payload, int $timeout = 20): array
{
    $config = btzap_config();
    $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://server.btzap.com.br'), '/');
    $token = trim((string) ($config['token'] ?? ''));

    $ch = curl_init($baseUrl . $endpoint);

    if ($ch === false) {
        return ['ok' => false, 'error' => 'Não foi possível iniciar o cURL.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'token: ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        return ['ok' => false, 'error' => $curlError ?: 'Erro desconhecido no cURL.'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => 'BTZap HTTP ' . $httpCode . ': ' . $body];
    }

    return ['ok' => true, 'response' => json_decode((string) $body, true)];
}

function btzap_send_presence(string $number): array
{
    $config = btzap_config();

    if (($config['typing_presence'] ?? true) !== true) {
        return ['ok' => true, 'skipped' => true];
    }

    return btzap_request('/message/presence', [
        'number' => $number,
        'presence' => 'composing',
        'delay' => max(0, (int) ($config['typing_delay'] ?? 30000)),
    ], 20);
}

function btzap_send_text(string $number, string $text): array
{
    $config = btzap_config();
    $presence = btzap_send_presence($number);

    if (($presence['ok'] ?? false) !== true) {
        error_log('BTZap presença digitando falhou para ' . $number . ': ' . (string) ($presence['error'] ?? 'Erro desconhecido.'));
    }

    $result = btzap_request('/send/text', [
        'number' => $number,
        'text' => $text,
        'delay' => (int) ($config['delay'] ?? 0),
    ], 20);

    if (($result['ok'] ?? false) === true) {
        $result['presence'] = $presence;
    }

    return $result;
}

function btzap_send_lead_notification(array $lead): array
{
    if (!btzap_is_configured()) {
        crm_update_whatsapp_status((string) $lead['id'], 'nao_configurado', 'BTZap ainda não configurado.');
        return ['ok' => false, 'error' => 'BTZap ainda não configurado.'];
    }

    $number = crm_whatsapp_number();

    if ($number === '') {
        crm_update_whatsapp_status((string) $lead['id'], 'notifica_sem_numero', 'Número interno do WhatsApp não configurado.');
        return ['ok' => false, 'error' => 'Número interno do WhatsApp não configurado.'];
    }

    $result = btzap_send_text($number, btzap_render_internal_notification($lead));

    if (($result['ok'] ?? false) === true) {
        crm_update_whatsapp_status((string) $lead['id'], 'notifica_enviada');
        return $result;
    }

    crm_update_whatsapp_status((string) $lead['id'], 'notifica_falhou', (string) ($result['error'] ?? 'Falha ao enviar.'));
    return $result;
}

function btzap_send_followup(array $queueItem): array
{
    if (!btzap_is_configured()) {
        return ['ok' => false, 'error' => 'BTZap ainda não configurado.'];
    }

    $number = btzap_normalize_phone((string) ($queueItem['whatsapp'] ?? ''));

    if ($number === '') {
        return ['ok' => false, 'error' => 'WhatsApp inválido.'];
    }

    return btzap_send_text($number, btzap_render_custom_message((string) $queueItem['message'], $queueItem));
}
