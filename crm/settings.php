<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/settings.php';

crm_require_login();

$saved = ($_GET['saved'] ?? '') === '1';
$error = '';
$settings = crm_read_settings();
$whatsappNumber = (string) ($settings['whatsapp_number'] ?? '');
$metaSettings = crm_meta_capi_settings();
$metaPixelId = $metaSettings['pixel_id'];
$metaAccessToken = $metaSettings['access_token'];
$metaTestEventCode = $metaSettings['test_event_code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_require_valid_csrf();

    if (($_POST['settings_section'] ?? '') === 'whatsapp') {
        $settings['whatsapp_number'] = crm_normalize_whatsapp_number((string) ($_POST['whatsapp_number'] ?? ''));
    }

    if (($_POST['settings_section'] ?? '') === 'meta') {
        $settings['meta_pixel_id'] = preg_replace('/\D+/', '', (string) ($_POST['meta_pixel_id'] ?? '')) ?? '';
        $settings['meta_access_token'] = trim((string) ($_POST['meta_access_token'] ?? ''));
        $settings['meta_test_event_code'] = trim((string) ($_POST['meta_test_event_code'] ?? ''));
    }

    crm_write_settings($settings);
    header('Location: settings.php?saved=1');
    exit;
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
            <p class="eyebrow">Integrações</p>
            <h1>Configurações do CRM</h1>
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
              <input type="hidden" name="settings_section" value="whatsapp" />
              <label>
                WhatsApp conectado/atendimento
                <input type="tel" name="whatsapp_number" value="<?= htmlspecialchars($whatsappNumber) ?>" placeholder="Ex: 5511999999999" />
              </label>
              <button type="submit">Salvar configurações</button>
            </form>
          </section>

          <section class="automation-card">
            <h2>Meta Ads</h2>

            <form class="flow-form" method="post">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(crm_csrf_token()) ?>" />
              <input type="hidden" name="settings_section" value="meta" />
              <label>
                Pixel ID
                <input type="text" name="meta_pixel_id" value="<?= htmlspecialchars($metaPixelId) ?>" placeholder="Ex: 123456789012345" inputmode="numeric" />
              </label>
              <label>
                Access Token
                <input type="password" name="meta_access_token" value="<?= htmlspecialchars($metaAccessToken) ?>" placeholder="Cole o token da API de Conversões" autocomplete="off" />
              </label>
              <label>
                Código de teste
                <input type="text" name="meta_test_event_code" value="<?= htmlspecialchars($metaTestEventCode) ?>" placeholder="Ex: TEST12345" autocomplete="off" />
              </label>
              <button type="submit">Salvar configurações</button>
            </form>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
