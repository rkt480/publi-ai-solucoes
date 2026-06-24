<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

return [
    'admin_user' => 'admin',
    'admin_password_hash' => '$2y$10$8zVbe2q3Pir2zfkXaVZ2sOgVcUBTdkilnKgRAOc2tr8CSI8Z8bc7G',
    'company_name' => 'Publi AI Soluções',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'u432013964_publi_ai_crm',
        'user' => 'u432013964_publi_ai',
        'password' => '5H^oDr*Exj',
        'charset' => 'utf8mb4',
    ],
    'btzap' => [
        'enabled' => true,
        'base_url' => 'https://server.btzap.com.br',
        'token' => 'b0d5b009-b2e3-4b60-8c73-7424d752e3cb',
        'delay' => 1200,
        'lead_message' => "Olá, {{name}}! Recebemos seu cadastro para demonstração da estrutura de landing page com CRM.\n\nEm breve vamos entrar em contato para entender melhor sua empresa e mostrar como funciona o fluxo de captação, CRM e notificações.\n\nPubli AI Soluções",
    ],
];
