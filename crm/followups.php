<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';

crm_require_login();

$flows = crm_read_followup_flows();

function short_text(string $text, int $limit = 120): string
{
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

function human_delay(int $minutes): string
{
    if ($minutes >= 1440 && $minutes % 1440 === 0) {
        $days = (int) ($minutes / 1440);
        return $days . ' ' . ($days === 1 ? 'dia' : 'dias');
    }

    if ($minutes >= 60 && $minutes % 60 === 0) {
        $hours = (int) ($minutes / 60);
        return $hours . ' ' . ($hours === 1 ? 'hora' : 'horas');
    }

    return $minutes . ' ' . ($minutes === 1 ? 'minuto' : 'minutos');
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fluxos de Follow-up | CRM</title>
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
            <a href="index.php">Leads</a>
            <a class="active" href="followups.php">Follow-up</a>
          </nav>
        </header>

        <header class="app-header">
          <div>
            <p class="eyebrow">Automação</p>
            <h1>Fluxos de follow-up</h1>
          </div>
          <nav>
            <a href="index.php">Voltar ao CRM</a>
          </nav>
        </header>

    <main class="dashboard automation-layout">
      <section class="automation-card">
        <h2>Criar novo fluxo</h2>
        <p>Monte a sequência como você falaria no comercial. Use <code>{{name}}</code>, <code>{{company}}</code> e <code>{{segment}}</code>.</p>
        <form class="flow-form" method="post" action="save-followup.php" id="flowForm">
          <input type="hidden" name="id" id="flowId" value="" />
          <input type="hidden" name="steps_json" id="stepsJson" value="" />
          <label>
            Nome do fluxo
            <input type="text" name="name" id="flowName" placeholder="Ex: Recuperação lead frio" required />
          </label>
          <label>
            Descrição
            <textarea name="description" id="flowDescription" rows="2" placeholder="Quando usar esse fluxo?"></textarea>
          </label>

          <div class="flow-steps" id="flowSteps">
            <article class="flow-step" data-step>
              <div class="flow-step-header">
                <strong>Mensagem 1</strong>
                <button class="secondary-action remove-step" type="button" data-remove-step>Remover</button>
              </div>
              <div class="delay-row">
                <label>
                  Enviar após
                  <input type="number" name="steps[0][delay_value]" min="0" value="0" />
                </label>
                <label>
                  Unidade
                  <select name="steps[0][delay_unit]">
                    <option value="minutes">minutos</option>
                    <option value="hours">horas</option>
                    <option value="days">dias</option>
                  </select>
                </label>
              </div>
              <label>
                Mensagem
                <textarea name="steps[0][message]" rows="4" placeholder="Ex: Oi {{name}}, vi que você pediu uma demonstração. Posso te mostrar como funciona?"></textarea>
              </label>
            </article>
          </div>

          <button class="secondary-action" type="button" id="addFlowStep">Adicionar mensagem</button>
          <div class="form-actions">
            <button type="submit" id="saveFlowButton">Salvar fluxo</button>
            <button class="secondary-action" type="button" id="cancelEditFlow" hidden>Cancelar edição</button>
          </div>
        </form>
      </section>

      <section class="automation-card">
        <h2>Fluxos cadastrados</h2>
        <?php if (count($flows) === 0): ?>
          <p>Nenhum fluxo cadastrado ainda.</p>
        <?php else: ?>
          <div class="flow-list">
            <?php foreach ($flows as $flow): ?>
              <?php $steps = crm_read_followup_steps((int) $flow['id']); ?>
              <article
                class="flow-item"
                data-flow='<?= htmlspecialchars(json_encode([
                  'id' => (int) $flow['id'],
                  'name' => (string) $flow['name'],
                  'description' => (string) ($flow['description'] ?? ''),
                  'steps' => array_map(fn(array $step): array => [
                    'delay_minutes' => (int) $step['delay_minutes'],
                    'message' => (string) $step['message'],
                  ], $steps),
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
              >
                <div>
                  <h3><?= htmlspecialchars((string) $flow['name']) ?></h3>
                  <p><?= htmlspecialchars((string) ($flow['description'] ?? '')) ?></p>
                </div>
                <p class="flow-messages-label">Mensagens</p>
                <ol>
                  <?php foreach ($steps as $step): ?>
                    <li>
                      <strong><?= human_delay((int) $step['delay_minutes']) ?></strong>
                      <?= htmlspecialchars(short_text((string) $step['message'])) ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
                <div class="flow-item-actions">
                  <button class="secondary-action" type="button" data-edit-flow>Editar</button>
                  <form method="post" action="delete-followup.php" onsubmit="return confirm('Excluir este fluxo?');">
                    <input type="hidden" name="id" value="<?= (int) $flow['id'] ?>" />
                    <button class="danger" type="submit">Excluir fluxo</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
    <template id="flowStepTemplate">
      <article class="flow-step" data-step>
        <div class="flow-step-header">
          <strong>Mensagem</strong>
          <button class="secondary-action remove-step" type="button" data-remove-step>Remover</button>
        </div>
        <div class="delay-row">
          <label>
            Enviar após
            <input type="number" data-name="delay_value" min="0" value="1" />
          </label>
          <label>
            Unidade
            <select data-name="delay_unit">
              <option value="minutes">minutos</option>
              <option value="hours">horas</option>
              <option value="days" selected>dias</option>
            </select>
          </label>
        </div>
        <label>
          Mensagem
          <textarea data-name="message" rows="4" placeholder="Digite a mensagem do follow-up"></textarea>
        </label>
      </article>
    </template>
      </div>
    </div>
    <script src="./assets/followups.js?v=20260624-2"></script>
  </body>
</html>
