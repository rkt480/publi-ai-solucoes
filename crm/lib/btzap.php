<?php

declare(strict_types=1);

require_once __DIR__ . '/storage.php';

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

function btzap_render_custom_message(string $message, array $lead): string
{
    $replacements = [
        '{{name}}' => (string) ($lead['name'] ?? ''),
        '{{company}}' => (string) ($lead['company'] ?? ''),
        '{{segment}}' => (string) ($lead['segment'] ?? ''),
    ];

    return strtr($message, $replacements);
}

function btzap_send_text(string $number, string $text): array
{
    $config = btzap_config();
    $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://server.btzap.com.br'), '/');
    $token = trim((string) ($config['token'] ?? ''));
    $payload = [
        'number' => $number,
        'text' => $text,
        'delay' => (int) ($config['delay'] ?? 0),
    ];

    $ch = curl_init($baseUrl . '/send/text');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'token: ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
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

function btzap_send_lead_notification(array $lead): array
{
    if (!btzap_is_configured()) {
        crm_update_whatsapp_status((string) $lead['id'], 'nao_configurado', 'BTZap ainda não configurado.');
        return ['ok' => false, 'error' => 'BTZap ainda não configurado.'];
    }

    $number = btzap_normalize_phone((string) ($lead['whatsapp'] ?? ''));

    if ($number === '') {
        crm_update_whatsapp_status((string) $lead['id'], 'falhou', 'WhatsApp inválido.');
        return ['ok' => false, 'error' => 'WhatsApp inválido.'];
    }

    $result = btzap_send_text($number, btzap_render_message($lead));

    if (($result['ok'] ?? false) === true) {
        crm_update_whatsapp_status((string) $lead['id'], 'enviado');
        return $result;
    }

    crm_update_whatsapp_status((string) $lead['id'], 'falhou', (string) ($result['error'] ?? 'Falha ao enviar.'));
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
