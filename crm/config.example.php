<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

return [
    'admin_user' => 'admin',
    'admin_password_hash' => 'cole-aqui-o-hash-gerado-com-password_hash',
    'company_name' => 'Publi AI Soluções',
    'auto_migrate' => false,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'publi_ai_crm',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'btzap' => [
        'enabled' => false,
        'base_url' => 'https://server.btzap.com.br',
        'token' => 'COLE_SEU_TOKEN_AQUI',
        'typing_presence' => true,
        'typing_delay' => 30000,
        'delay' => 1200,
        'internal_notification_number' => '55DDDNUMERO',
        'public_whatsapp_number' => '55DDDNUMERO',
        'internal_notification_message' => "Novo lead recebido:\n\nNome: {{name}}\nWhatsApp: {{whatsapp}}\nEmpresa: {{company}}\nSite/Landing: {{segment}}\nControle dos leads: {{advertises}}\nNecessidade: {{message}}",
        'lead_message' => "Olá, {{name}}! Recebemos seu cadastro para demonstração da estrutura de landing page com CRM.\n\nEm breve vamos entrar em contato.",
    ],
];
