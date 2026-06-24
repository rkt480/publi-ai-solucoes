<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

if (crm_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (crm_attempt_login($user, $password)) {
        header('Location: index.php');
        exit;
    }

    $error = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login | CRM Publi AI Soluções</title>
    <link rel="stylesheet" href="./assets/crm.css" />
  </head>
  <body class="auth-page">
    <main class="auth-card">
      <p class="eyebrow">Painel privado</p>
      <h1>CRM de leads</h1>
      <p>Acesse os contatos recebidos pela landing page.</p>

      <?php if ($error !== ''): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <label>
          Usuário
          <input type="text" name="user" autocomplete="username" required />
        </label>
        <label>
          Senha
          <input type="password" name="password" autocomplete="current-password" required />
        </label>
        <button type="submit">Entrar no painel</button>
      </form>
    </main>
  </body>
</html>
