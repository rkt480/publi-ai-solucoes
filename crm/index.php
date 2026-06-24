<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

$config = crm_config();
$leads = crm_read_leads();
$followupFlows = crm_read_followup_flows(true);
$statusLabels = [
    'novo' => 'Novo',
    'contatado' => 'Contatado',
    'followup' => 'Follow-up',
    'proposta' => 'Proposta enviada',
    'fechado' => 'Fechado',
    'perdido' => 'Perdido',
];
$leadsByStatus = array_fill_keys(array_keys($statusLabels), []);

foreach ($leads as $lead) {
    $status = (string) ($lead['status'] ?? 'novo');

    if (!isset($leadsByStatus[$status])) {
        $status = 'novo';
    }

    $leadsByStatus[$status][] = $lead;
}

function lead_origin_summary(array $lead): string
{
    $source = trim((string) ($lead['utm_source'] ?? ''));
    $medium = trim((string) ($lead['utm_medium'] ?? ''));
    $campaign = trim((string) ($lead['utm_campaign'] ?? ''));

    if ($source !== '' || $medium !== '' || $campaign !== '') {
        $parts = array_filter([$source, $medium, $campaign], fn(string $part): bool => $part !== '');
        return implode(' / ', $parts);
    }

    $referrer = trim((string) ($lead['referrer'] ?? ''));

    if ($referrer !== '') {
        $host = parse_url($referrer, PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'Site externo';
    }

    return 'Direto ou sem UTM';
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CRM de Leads | <?= htmlspecialchars((string) $config['company_name']) ?></title>
    <link rel="stylesheet" href="./assets/crm.css?v=20260621-5" />
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
            <a class="active" href="index.php">Leads</a>
            <a href="followups.php">Follow-up</a>
          </nav>
        </header>

        <header class="app-header">
          <div>
            <p class="eyebrow">CRM privado</p>
            <h1>Leads da landing page</h1>
          </div>
          <nav>
            <a href="followups.php">Criar fluxo</a>
            <a href="export.php">Exportar CSV</a>
          </nav>
        </header>

    <main class="dashboard">
      <section class="metrics">
        <article>
          <span>Total</span>
          <strong><?= count($leads) ?></strong>
        </article>
        <article>
          <span>Novos</span>
          <strong><?= count(array_filter($leads, fn(array $lead): bool => ($lead['status'] ?? '') === 'novo')) ?></strong>
        </article>
        <article>
          <span>Em contato</span>
          <strong><?= count(array_filter($leads, fn(array $lead): bool => in_array(($lead['status'] ?? ''), ['contatado', 'proposta'], true))) ?></strong>
        </article>
        <article>
          <span>Fechados</span>
          <strong><?= count(array_filter($leads, fn(array $lead): bool => ($lead['status'] ?? '') === 'fechado')) ?></strong>
        </article>
      </section>

      <?php if (count($leads) === 0): ?>
        <section class="empty-state">
          <h2>Nenhum lead recebido ainda</h2>
          <p>Quando alguém preencher o formulário da landing page, o cadastro aparecerá aqui.</p>
        </section>
      <?php else: ?>
        <section class="kanban-board" aria-label="Funil comercial em Kanban">
          <?php foreach ($statusLabels as $status => $label): ?>
            <section class="kanban-column" data-status="<?= htmlspecialchars($status) ?>">
              <header class="kanban-column-header">
                <div>
                  <span class="status status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($label) ?></span>
                  <strong><?= count($leadsByStatus[$status]) ?></strong>
                </div>
              </header>

              <div class="kanban-dropzone" data-status="<?= htmlspecialchars($status) ?>">
                <?php foreach ($leadsByStatus[$status] as $lead): ?>
                  <article
                    class="lead-card kanban-card"
                    draggable="true"
                    data-lead-id="<?= htmlspecialchars((string) ($lead['id'] ?? '')) ?>"
                  >
                    <div class="lead-main">
                      <div>
                        <h2><?= htmlspecialchars((string) ($lead['name'] ?? 'Sem nome')) ?></h2>
                        <p><?= htmlspecialchars((string) ($lead['company'] ?? '')) ?> · <?= htmlspecialchars((string) ($lead['segment'] ?? '')) ?></p>
                      </div>
                    </div>

                    <p class="message source-preview">Fonte: <?= htmlspecialchars(lead_origin_summary($lead)) ?></p>

                    <div class="lead-actions">
                      <button class="details-toggle" type="button" data-toggle-details>Detalhes</button>
                      <form method="post" action="delete.php" onsubmit="return confirm('Excluir este lead?');">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($lead['id'] ?? '')) ?>" />
                        <button type="submit" class="danger">Excluir</button>
                      </form>
                    </div>

                    <div
                      class="lead-details-panel"
                      hidden
                      role="dialog"
                      aria-modal="true"
                      aria-label="Detalhes do lead"
                      data-modal-lead-id="<?= htmlspecialchars((string) ($lead['id'] ?? '')) ?>"
                    >
                      <div class="lead-modal-card">
                        <header class="lead-modal-header">
                          <div>
                            <p class="eyebrow">Detalhes do lead</p>
                            <h2><?= htmlspecialchars((string) ($lead['name'] ?? 'Sem nome')) ?> | <?= htmlspecialchars((string) ($lead['company'] ?? 'Empresa não informada')) ?></h2>
                            <span><?= htmlspecialchars((string) ($lead['segment'] ?? 'Segmento não informado')) ?> · <?= htmlspecialchars(lead_origin_summary($lead)) ?></span>
                          </div>
                          <button class="modal-close details-toggle" type="button" data-toggle-details>×</button>
                        </header>

                        <div class="lead-modal-body">
                          <aside class="lead-modal-tabs" aria-label="Seções do lead">
                            <button class="active" type="button" data-lead-tab="dados">Dados do lead</button>
                            <button type="button" data-lead-tab="origem">Origem e UTM</button>
                            <button type="button" data-lead-tab="observacoes">Observações</button>
                            <button type="button" data-lead-tab="followup">Follow-up</button>
                          </aside>

                          <section class="lead-modal-content">
                            <div class="lead-tab-panel active" data-lead-panel="dados">
                              <h3>Contato</h3>
                              <dl class="lead-details">
                        <div>
                          <dt>WhatsApp</dt>
                          <dd><?= htmlspecialchars((string) ($lead['whatsapp'] ?? '')) ?></dd>
                        </div>
                        <div>
                          <dt>Anuncia?</dt>
                          <dd><?= htmlspecialchars((string) ($lead['advertises'] ?? '')) ?></dd>
                        </div>
                        <div>
                          <dt>Recebido em</dt>
                          <dd><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($lead['created_at'] ?? 'now')))) ?></dd>
                        </div>
                        <div>
                          <dt>Status WhatsApp</dt>
                          <dd>
                            <?= htmlspecialchars((string) ($lead['whatsapp_status'] ?? 'pendente')) ?>
                            <?php if (!empty($lead['whatsapp_sent_at'])): ?>
                              em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $lead['whatsapp_sent_at']))) ?>
                            <?php endif; ?>
                          </dd>
                        </div>
                              </dl>
                            </div>

                            <div class="lead-tab-panel" data-lead-panel="origem" hidden>
                              <h3>Origem e UTM</h3>
                              <dl class="lead-details">
                        <div>
                          <dt>Origem do lead</dt>
                          <dd><?= htmlspecialchars(lead_origin_summary($lead)) ?></dd>
                        </div>
                        <div>
                          <dt>UTM source</dt>
                          <dd><?= htmlspecialchars(trim((string) ($lead['utm_source'] ?? '')) !== '' ? (string) $lead['utm_source'] : 'Sem UTM') ?></dd>
                        </div>
                        <div>
                          <dt>UTM medium</dt>
                          <dd><?= htmlspecialchars(trim((string) ($lead['utm_medium'] ?? '')) !== '' ? (string) $lead['utm_medium'] : 'Sem UTM') ?></dd>
                        </div>
                        <div>
                          <dt>UTM campaign</dt>
                          <dd><?= htmlspecialchars(trim((string) ($lead['utm_campaign'] ?? '')) !== '' ? (string) $lead['utm_campaign'] : 'Sem UTM') ?></dd>
                        </div>
                        <div>
                          <dt>UTM content / term</dt>
                          <dd>
                            <?= htmlspecialchars((string) ($lead['utm_content'] ?? '')) ?>
                            <?= trim((string) ($lead['utm_term'] ?? '')) !== '' ? ' / ' . htmlspecialchars((string) $lead['utm_term']) : '' ?>
                            <?= trim((string) ($lead['utm_content'] ?? '')) === '' && trim((string) ($lead['utm_term'] ?? '')) === '' ? 'Sem UTM' : '' ?>
                          </dd>
                        </div>
                        <div class="field-wide">
                          <dt>Página/referrer</dt>
                          <dd>
                            <?= htmlspecialchars(trim((string) ($lead['landing_path'] ?? '')) !== '' ? (string) $lead['landing_path'] : (string) ($lead['page'] ?? '')) ?>
                            <?= trim((string) ($lead['referrer'] ?? '')) !== '' ? ' | Ref: ' . htmlspecialchars((string) $lead['referrer']) : '' ?>
                          </dd>
                        </div>
                      </dl>
                            </div>

                      <?php if (!empty($lead['whatsapp_error'])): ?>
                        <p class="message error-message"><?= htmlspecialchars((string) $lead['whatsapp_error']) ?></p>
                      <?php endif; ?>

                            <div class="lead-tab-panel" data-lead-panel="observacoes" hidden>
                              <h3>Observações comerciais</h3>
                      <form class="update-form" method="post" action="update.php">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($lead['id'] ?? '')) ?>" />
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>" />
                        <label>
                          Observações comerciais
                          <textarea name="notes" rows="3" placeholder="Ex: pediu orçamento, retornar amanhã, perfil bom..."><?= htmlspecialchars((string) ($lead['notes'] ?? '')) ?></textarea>
                        </label>
                        <button type="submit">Salvar observação</button>
                      </form>
                            </div>

                            <div class="lead-tab-panel" data-lead-panel="followup" hidden>
                              <h3>Fluxo de follow-up</h3>
                      <form class="update-form" method="post" action="assign-followup.php">
                        <input type="hidden" name="lead_id" value="<?= htmlspecialchars((string) ($lead['id'] ?? '')) ?>" />
                        <label>
                          Fluxo de follow-up
                          <select name="flow_id" required>
                            <option value="">Selecione um fluxo</option>
                            <?php foreach ($followupFlows as $flow): ?>
                              <option value="<?= (int) $flow['id'] ?>" <?= ((int) ($lead['followup_flow_id'] ?? 0) === (int) $flow['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $flow['name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <button type="submit">Aplicar follow-up</button>
                      </form>
                            </div>
                          </section>
                        </div>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
      </div>
    </div>
    <script src="./assets/crm.js?v=20260624-1"></script>
  </body>
</html>
