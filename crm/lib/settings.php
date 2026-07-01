<?php

declare(strict_types=1);

function crm_settings_file(): string
{
    return dirname(__DIR__) . '/data/settings.json';
}

function crm_read_settings(): array
{
    $file = crm_settings_file();

    if (!is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    $settings = json_decode($contents !== false ? $contents : '{}', true);

    return is_array($settings) ? $settings : [];
}

function crm_write_settings(array $settings): void
{
    $dir = dirname(crm_settings_file());

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    file_put_contents(crm_settings_file(), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function crm_normalize_whatsapp_number(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';

    if ($digits === '') {
        return '';
    }

    return str_starts_with($digits, '55') ? $digits : '55' . $digits;
}

function crm_whatsapp_number(): string
{
    $settings = crm_read_settings();
    return crm_normalize_whatsapp_number((string) ($settings['whatsapp_number'] ?? ''));
}
