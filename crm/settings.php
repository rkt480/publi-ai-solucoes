<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/settings.php';

crm_require_login();

$saved = ($_GET['saved'] ?? '') === '1';
$error = '';
$settings = crm_read_settings();
$whatsappNumber = (string) ($settings['whatsapp_number'] ?? crm_whatsapp_number());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_require_valid_csrf();

    $number = crm_normalize_whatsapp_number((string) ($_POST['whatsapp_number'] ?? ''));

    if ($number === '') {
        $error = 'Informe o número do WhatsApp de atendimento.';
    } else {
        $settings['whatsapp_number'] = $number;
        crm_write_settings($settings);
        header('Location: settings.php?saved=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Configurações | CRM</title>
    <link rel="stylesheet" href="./assets/crm.css?v=20260701-1900" />
  </head>
  <body>
    <div class="app-shell">
      <aside class="sidebar" aria-label="Navegação do CRM">
        <a class="brand" href="index.php" aria-label="Início">
          <span class="brand-mark">P</span>
          <span>Publi CRM</span>
        </a>
        <a class="sidebar-exit" href="logout.php" title="Sair">Sair</a>
      </aside>

      <div class="workspace">
        <header class="topbar">
          <nav class="topbar-nav" aria-label="Áreas do CRM">
            <a href="index.php">Leads</a>
            <a href="followups.php">Follow-up</a>
            <a class="active" href="settings.php">Configurações</a>
          </nav>
        </header>

        <header class="app-header">
          <div>
            <p class="eyebrow">Atendimento</p>
            <h1>Configurações do WhatsApp</h1>
          </div>
        </header>

        <main class="dashboard automation-layout">
          <section class="automation-card">
            <h2>Número de atendimento</h2>

            <?php if ($saved): ?>
              <div class="alert success">Configuração salva.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form class="flow-form" method="post">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(crm_csrf_token()) ?>" />
              <label>
                WhatsApp conectado/atendimento
                <input type="tel" name="whatsapp_number" value="<?= htmlspecialchars($whatsappNumber) ?>" placeholder="Ex: 5511999999999" required />
              </label>
              <button type="submit">Salvar número</button>
            </form>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
