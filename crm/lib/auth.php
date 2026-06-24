<?php

declare(strict_types=1);

function crm_config(): array
{
    return require dirname(__DIR__) . '/config.php';
}

function crm_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function crm_is_logged_in(): bool
{
    crm_start_session();
    return ($_SESSION['crm_logged_in'] ?? false) === true;
}

function crm_require_login(): void
{
    if (!crm_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function crm_attempt_login(string $user, string $password): bool
{
    crm_start_session();
    $config = crm_config();

    $validUser = hash_equals((string) $config['admin_user'], $user);
    $validPassword = password_verify($password, (string) $config['admin_password_hash']);

    if (!$validUser || !$validPassword) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['crm_logged_in'] = true;
    $_SESSION['crm_user'] = $user;

    return true;
}

function crm_logout(): void
{
    crm_start_session();
    $_SESSION = [];
    session_destroy();
}
