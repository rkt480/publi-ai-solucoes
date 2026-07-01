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
$googleTagManagerId = crm_google_tag_manager_id();
$whatsappConfigured = $whatsappNumber !== '';
$metaConfigured = $metaPixelId !== '' && $metaAccessToken !== '';
$gtmConfigured = $googleTagManagerId !== '';

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

    if (($_POST['settings_section'] ?? '') === 'gtm') {
        $settings['google_tag_manager_id'] = crm_normalize_gtm_id((string) ($_POST['google_tag_manager_id'] ?? ''));
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
    <link rel="stylesheet" href="./assets/crm.css?v=20260701-2052" />
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

        <main class="dashboard automation-layout integrations-layout">
          <section class="automation-card integration-card">
            <header class="integration-card-header">
              <span class="integration-icon whatsapp-icon" aria-hidden="true">
                <svg class="brand-logo" viewBox="0 0 48 48" focusable="false">
                  <circle cx="24" cy="24" r="21" fill="#25D366" />
                  <path fill="#fff" d="M34.6 29.9c-.4 1.2-2.2 2.2-3.2 2.3-.9.1-2.1.2-6.1-1.5-5.1-2.1-8.4-7.4-8.6-7.7-.2-.3-2.1-2.8-2.1-5.3s1.3-3.7 1.8-4.2c.5-.5 1.1-.6 1.5-.6h1.1c.3 0 .8-.1 1.2.9.4 1 .1.2 1.4 3.4.1.3.2.7 0 1.1-.2.4-.3.6-.6.9-.3.3-.5.6-.8.9-.2.3-.5.6-.2 1.1.3.5 1.2 2 2.6 3.2 1.8 1.6 3.3 2.1 3.8 2.4.5.3.8.2 1.1-.1.3-.4 1.2-1.4 1.6-1.9.3-.5.7-.4 1.1-.2.5.2 2.8 1.3 3.3 1.6.5.3.8.4.9.6.1.3.1 1.7-.3 2.9Z" />
                  <path fill="#fff" d="M24 7.7A16.2 16.2 0 0 0 9.9 31.9L8 39l7.3-1.9A16.2 16.2 0 1 0 24 7.7Zm0 29.4c-2.7 0-5.2-.8-7.4-2.2l-.5-.3-4.3 1.1 1.2-4.2-.3-.5A13.2 13.2 0 1 1 24 37.1Z" />
                </svg>
              </span>
              <div>
                <p class="integration-kicker">Atendimento</p>
                <h2>WhatsApp</h2>
              </div>
              <span class="integration-status <?= $whatsappConfigured ? 'is-active' : '' ?>">
                <?= $whatsappConfigured ? 'Configurado' : 'Inativo' ?>
              </span>
            </header>
            <p class="integration-description">Número que recebe a notificação interna quando um novo lead entra.</p>

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
                Número conectado
                <input type="tel" name="whatsapp_number" value="<?= htmlspecialchars($whatsappNumber) ?>" placeholder="Ex: 5511999999999" />
              </label>
              <button class="integration-save" type="submit">
                <span aria-hidden="true">✓</span>
                Salvar configurações
              </button>
            </form>
          </section>

          <section class="automation-card integration-card">
            <header class="integration-card-header">
              <span class="integration-icon meta-icon" aria-hidden="true">
                <svg class="brand-logo" viewBox="0 0 48 48" focusable="false">
                  <path fill="#0866FF" d="M9.2 29.6c1.1-4.4 4-13.1 9.3-13.1 3 0 5 2.1 6.8 4.7 1.8-2.6 3.8-4.7 6.8-4.7 5.3 0 8.2 8.7 9.3 13.1 1.2 4.8-.5 8-4.3 8-3.5 0-6.4-4.1-9.1-8.4l-2.7-4.3-2.7 4.3c-2.7 4.3-5.6 8.4-9.1 8.4-3.8 0-5.5-3.2-4.3-8Zm5.2 2.7c1.7 0 3.9-3.2 5.9-6.3l2-3.1c-1.3-1.9-2.4-2.8-3.8-2.8-2.2 0-4.2 4.6-5.1 8.4-.6 2.4-.2 3.8 1 3.8Zm21.2 0c1.2 0 1.6-1.4 1-3.8-.9-3.8-2.9-8.4-5.1-8.4-1.4 0-2.5.9-3.8 2.8l2 3.1c2 3.1 4.2 6.3 5.9 6.3Z" />
                </svg>
              </span>
              <div>
                <p class="integration-kicker">API de conversões</p>
                <h2>Meta Ads</h2>
              </div>
              <span class="integration-status <?= $metaConfigured ? 'is-active' : '' ?>">
                <?= $metaConfigured ? 'Configurado' : 'Inativo' ?>
              </span>
            </header>
            <p class="integration-description">Envia Lead, PropostaEnviada e Purchase pelo servidor para o mesmo Pixel.</p>

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
              <button class="integration-save" type="submit">
                <span aria-hidden="true">✓</span>
                Salvar configurações
              </button>
            </form>
          </section>

          <section class="automation-card integration-card">
            <header class="integration-card-header">
              <span class="integration-icon gtm-icon" aria-hidden="true">
                <svg class="brand-logo" viewBox="0 0 48 48" focusable="false">
                  <path fill="#4285F4" d="M8.7 20.5 23.9 5.3c1.2-1.2 3.2-1.2 4.4 0l14.4 14.4c1.2 1.2 1.2 3.2 0 4.4L27.5 39.3c-1.2 1.2-3.2 1.2-4.4 0L8.7 24.9c-1.2-1.2-1.2-3.2 0-4.4Z" />
                  <path fill="#8AB4F8" d="M18.3 20.5 28.3 10.5 42.7 24.9 32.7 34.9 18.3 20.5Z" />
                  <path fill="#3367D6" d="M8.7 20.5 18.3 10.9 32.7 25.3 23.1 34.9 8.7 20.5Z" />
                  <path fill="#fff" d="M21.7 21.7 25.9 17.5 30.3 21.9 26.1 26.1 21.7 21.7Z" opacity=".94" />
                </svg>
              </span>
              <div>
                <p class="integration-kicker">Tags do site</p>
                <h2>Google Tag Manager</h2>
              </div>
              <span class="integration-status <?= $gtmConfigured ? 'is-active' : '' ?>">
                <?= $gtmConfigured ? 'Configurado' : 'Inativo' ?>
              </span>
            </header>
            <p class="integration-description">Carrega o container na landing page para PageView, tags e eventos de navegação.</p>

            <form class="flow-form" method="post">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(crm_csrf_token()) ?>" />
              <input type="hidden" name="settings_section" value="gtm" />
              <label>
                Container ID
                <input type="text" name="google_tag_manager_id" value="<?= htmlspecialchars($googleTagManagerId) ?>" placeholder="Ex: GTM-ABC1234" autocomplete="off" />
              </label>
              <button class="integration-save" type="submit">
                <span aria-hidden="true">✓</span>
                Salvar configurações
              </button>
            </form>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
