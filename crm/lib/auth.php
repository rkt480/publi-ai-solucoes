<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

function crm_config(): array
{
    return require dirname(__DIR__) . '/config.php';
}

function crm_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function crm_login_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $crmPosition = strpos($scriptName, '/crm/');

    if ($crmPosition === false) {
        return 'login.php';
    }

    return substr($scriptName, 0, $crmPosition + 5) . 'login.php';
}

function crm_request_expects_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return str_contains($accept, 'application/json')
        || str_contains($scriptName, '/api/');
}

function crm_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

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
    crm_send_security_headers();

    if (!crm_is_logged_in()) {
        if (crm_request_expects_json()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Login necessário.']);
            exit;
        }

        header('Location: ' . crm_login_path());
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

function crm_csrf_token(): string
{
    crm_start_session();

    if (empty($_SESSION['crm_csrf_token']) || !is_string($_SESSION['crm_csrf_token'])) {
        $_SESSION['crm_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['crm_csrf_token'];
}

function crm_request_csrf_token(): string
{
    return (string) (
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['_csrf_token']
        ?? ''
    );
}

function crm_verify_csrf_token(string $token): bool
{
    crm_start_session();

    return is_string($_SESSION['crm_csrf_token'] ?? null)
        && hash_equals((string) $_SESSION['crm_csrf_token'], $token);
}

function crm_require_valid_csrf(): void
{
    if (crm_verify_csrf_token(crm_request_csrf_token())) {
        return;
    }

    http_response_code(419);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sessão expirada. Atualize a página e tente novamente.';
    exit;
}

function crm_login_identity(string $user): string
{
    return strtolower(trim($user));
}

function crm_login_is_limited(string $user): bool
{
    return crm_throttle_is_limited('login', crm_login_identity($user), 8, 900);
}

function crm_record_login_failure(string $user): void
{
    crm_throttle_record('login', crm_login_identity($user), 900);
}

function crm_clear_login_failures(string $user): void
{
    crm_throttle_clear('login', crm_login_identity($user), 900);
}

function crm_logout(): void
{
    crm_start_session();
    $_SESSION = [];
    session_destroy();
}
