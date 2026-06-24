<?php

declare(strict_types=1);

function crm_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require dirname(__DIR__) . '/config.php';
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, (string) $db['user'], (string) $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    crm_ensure_lead_tracking_columns($pdo);

    return $pdo;
}

function crm_ensure_lead_tracking_columns(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $columns = [
        'utm_source' => 'VARCHAR(120) NULL AFTER page',
        'utm_medium' => 'VARCHAR(120) NULL AFTER utm_source',
        'utm_campaign' => 'VARCHAR(180) NULL AFTER utm_medium',
        'utm_content' => 'VARCHAR(180) NULL AFTER utm_campaign',
        'utm_term' => 'VARCHAR(180) NULL AFTER utm_content',
        'referrer' => 'TEXT NULL AFTER utm_term',
        'landing_path' => 'TEXT NULL AFTER referrer',
    ];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = "leads"
          AND COLUMN_NAME = :column_name'
    );

    foreach ($columns as $column => $definition) {
        $stmt->execute(['column_name' => $column]);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(sprintf('ALTER TABLE leads ADD COLUMN %s %s', $column, $definition));
        }
    }

    $checked = true;
}

function crm_read_leads(): array
{
    $stmt = crm_db()->query('SELECT * FROM leads ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function crm_create_lead(array $payload): array
{
    $lead = [
        'id' => bin2hex(random_bytes(8)),
        'name' => trim((string) ($payload['name'] ?? '')),
        'whatsapp' => trim((string) ($payload['whatsapp'] ?? '')),
        'company' => trim((string) ($payload['company'] ?? '')),
        'segment' => trim((string) ($payload['segment'] ?? '')),
        'advertises' => trim((string) ($payload['advertises'] ?? '')),
        'message' => trim((string) ($payload['message'] ?? '')),
        'page' => trim((string) ($payload['page'] ?? '')),
        'utm_source' => trim((string) ($payload['utm_source'] ?? '')),
        'utm_medium' => trim((string) ($payload['utm_medium'] ?? '')),
        'utm_campaign' => trim((string) ($payload['utm_campaign'] ?? '')),
        'utm_content' => trim((string) ($payload['utm_content'] ?? '')),
        'utm_term' => trim((string) ($payload['utm_term'] ?? '')),
        'referrer' => trim((string) ($payload['referrer'] ?? '')),
        'landing_path' => trim((string) ($payload['landing_path'] ?? '')),
        'status' => 'novo',
        'notes' => '',
        'whatsapp_status' => 'pendente',
        'whatsapp_sent_at' => null,
        'whatsapp_error' => null,
        'followup_flow_id' => null,
        'followup_started_at' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $stmt = crm_db()->prepare(
        'INSERT INTO leads
        (id, name, whatsapp, company, segment, advertises, message, page, utm_source, utm_medium, utm_campaign, utm_content, utm_term, referrer, landing_path, status, notes, whatsapp_status, whatsapp_sent_at, whatsapp_error, followup_flow_id, followup_started_at, created_at, updated_at)
        VALUES
        (:id, :name, :whatsapp, :company, :segment, :advertises, :message, :page, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :referrer, :landing_path, :status, :notes, :whatsapp_status, :whatsapp_sent_at, :whatsapp_error, :followup_flow_id, :followup_started_at, :created_at, :updated_at)'
    );
    $stmt->execute($lead);

    return $lead;
}

function crm_update_lead(string $id, array $updates): bool
{
    $allowedStatuses = ['novo', 'contatado', 'followup', 'proposta', 'fechado', 'perdido'];
    $status = trim((string) ($updates['status'] ?? 'novo'));

    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'novo';
    }

    $notes = trim((string) ($updates['notes'] ?? ''));

    if (!array_key_exists('notes', $updates)) {
        $stmt = crm_db()->prepare(
            'UPDATE leads SET status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() > 0;
    }

    $stmt = crm_db()->prepare(
        'UPDATE leads SET status = :status, notes = :notes, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'status' => $status,
        'notes' => $notes,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return $stmt->rowCount() > 0;
}

function crm_read_followup_flows(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM followup_flows';

    if ($onlyActive) {
        $sql .= ' WHERE active = 1';
    }

    $sql .= ' ORDER BY created_at DESC';

    return crm_db()->query($sql)->fetchAll();
}

function crm_find_followup_flow(int $id): ?array
{
    $stmt = crm_db()->prepare('SELECT * FROM followup_flows WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $flow = $stmt->fetch();

    return is_array($flow) ? $flow : null;
}

function crm_read_followup_steps(int $flowId): array
{
    $stmt = crm_db()->prepare('SELECT * FROM followup_steps WHERE flow_id = :flow_id ORDER BY step_order ASC');
    $stmt->execute(['flow_id' => $flowId]);

    return $stmt->fetchAll();
}

function crm_create_followup_flow(string $name, string $description, array $steps): int
{
    $db = crm_db();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'INSERT INTO followup_flows (name, description, active, created_at, updated_at)
            VALUES (:name, :description, 1, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $flowId = (int) $db->lastInsertId();
        crm_replace_followup_steps($flowId, $steps);
        $db->commit();

        return $flowId;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function crm_update_followup_flow(int $id, string $name, string $description, array $steps): bool
{
    $db = crm_db();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'UPDATE followup_flows
            SET name = :name, description = :description, updated_at = :updated_at
            WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        crm_replace_followup_steps($id, $steps);
        crm_reschedule_followup_flow($id);
        $db->commit();

        return true;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function crm_replace_followup_steps(int $flowId, array $steps): void
{
    $db = crm_db();
    $delete = $db->prepare('DELETE FROM followup_steps WHERE flow_id = :flow_id');
    $delete->execute(['flow_id' => $flowId]);

    $insert = $db->prepare(
        'INSERT INTO followup_steps (flow_id, step_order, delay_minutes, message, created_at)
        VALUES (:flow_id, :step_order, :delay_minutes, :message, :created_at)'
    );

    $order = 1;

    foreach ($steps as $step) {
        $message = trim((string) ($step['message'] ?? ''));

        if ($message === '') {
            continue;
        }

        $insert->execute([
            'flow_id' => $flowId,
            'step_order' => $order,
            'delay_minutes' => max(0, (int) ($step['delay_minutes'] ?? 0)),
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $order++;
    }
}

function crm_reschedule_followup_flow(int $flowId): void
{
    $db = crm_db();
    $leads = $db->prepare(
        'SELECT id FROM leads WHERE status = "followup" AND followup_flow_id = :flow_id'
    );
    $leads->execute(['flow_id' => $flowId]);
    $activeLeads = $leads->fetchAll();

    if (count($activeLeads) === 0) {
        return;
    }

    $steps = crm_read_followup_steps($flowId);
    $insert = $db->prepare(
        'INSERT INTO followup_queue (lead_id, flow_id, step_id, step_order, scheduled_at, status, created_at)
        VALUES (:lead_id, :flow_id, :step_id, :step_order, :scheduled_at, "pendente", :created_at)'
    );
    $alreadyHandled = $db->prepare(
        'SELECT COUNT(*) FROM followup_queue
        WHERE lead_id = :lead_id
          AND flow_id = :flow_id
          AND step_order = :step_order
          AND status IN ("pendente", "enviado")'
    );
    $alreadySent = $db->prepare(
        'SELECT COUNT(*) FROM followup_step_history
        WHERE lead_id = :lead_id
          AND flow_id = :flow_id
          AND step_order = :step_order
          AND status = "enviado"'
    );

    foreach ($activeLeads as $lead) {
        foreach ($steps as $step) {
            $alreadySent->execute([
                'lead_id' => $lead['id'],
                'flow_id' => $flowId,
                'step_order' => $step['step_order'],
            ]);

            if ((int) $alreadySent->fetchColumn() > 0) {
                continue;
            }

            $alreadyHandled->execute([
                'lead_id' => $lead['id'],
                'flow_id' => $flowId,
                'step_order' => $step['step_order'],
            ]);

            if ((int) $alreadyHandled->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([
                'lead_id' => $lead['id'],
                'flow_id' => $flowId,
                'step_id' => $step['id'],
                'step_order' => $step['step_order'],
                'scheduled_at' => date('Y-m-d H:i:s', time() + ((int) $step['delay_minutes'] * 60)),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

function crm_delete_followup_flow(int $id): bool
{
    $stmt = crm_db()->prepare('DELETE FROM followup_flows WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}

function crm_assign_followup_flow(string $leadId, int $flowId): bool
{
    $flow = crm_find_followup_flow($flowId);

    if ($flow === null) {
        return false;
    }

    $db = crm_db();
    $db->beginTransaction();

    try {
        $update = $db->prepare(
            'UPDATE leads
            SET status = "followup",
                followup_flow_id = :flow_id,
                followup_started_at = :started_at,
                updated_at = :updated_at
            WHERE id = :id'
        );
        $update->execute([
            'id' => $leadId,
            'flow_id' => $flowId,
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $clear = $db->prepare('DELETE FROM followup_queue WHERE lead_id = :lead_id AND status = "pendente"');
        $clear->execute(['lead_id' => $leadId]);

        $steps = crm_read_followup_steps($flowId);
        $insert = $db->prepare(
            'INSERT INTO followup_queue (lead_id, flow_id, step_id, step_order, scheduled_at, status, created_at)
            VALUES (:lead_id, :flow_id, :step_id, :step_order, :scheduled_at, "pendente", :created_at)'
        );

        foreach ($steps as $step) {
            $scheduledAt = date('Y-m-d H:i:s', time() + ((int) $step['delay_minutes'] * 60));
            $insert->execute([
                'lead_id' => $leadId,
                'flow_id' => $flowId,
                'step_id' => $step['id'],
                'step_order' => $step['step_order'],
                'scheduled_at' => $scheduledAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->commit();
        return $update->rowCount() > 0;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function crm_read_due_followups(int $limit = 20): array
{
    $stmt = crm_db()->prepare(
        'SELECT q.*, s.message, l.name, l.whatsapp, l.company, l.segment
        FROM followup_queue q
        JOIN followup_steps s ON s.id = q.step_id
        JOIN leads l ON l.id = q.lead_id
        WHERE q.status = "pendente"
          AND q.scheduled_at <= :now
          AND NOT EXISTS (
            SELECT 1
            FROM followup_queue q2
            WHERE q2.lead_id = q.lead_id
              AND q2.flow_id = q.flow_id
              AND q2.status = "pendente"
              AND q2.scheduled_at <= :now
              AND q2.step_order < q.step_order
          )
          AND NOT EXISTS (
            SELECT 1
            FROM followup_step_history h
            WHERE h.lead_id = q.lead_id
              AND h.flow_id = q.flow_id
              AND h.status = "enviado"
              AND h.sent_at >= :recent_sent_at
          )
        ORDER BY q.scheduled_at ASC
        LIMIT ' . max(1, $limit)
    );
    $now = time();
    $stmt->execute([
        'now' => date('Y-m-d H:i:s', $now),
        'recent_sent_at' => date('Y-m-d H:i:s', $now - 55),
    ]);

    return $stmt->fetchAll();
}

function crm_update_followup_queue_item(int $id, string $status, ?string $error = null): bool
{
    $itemStmt = crm_db()->prepare('SELECT * FROM followup_queue WHERE id = :id LIMIT 1');
    $itemStmt->execute(['id' => $id]);
    $item = $itemStmt->fetch();

    $stmt = crm_db()->prepare(
        'UPDATE followup_queue
        SET status = :status,
            sent_at = :sent_at,
            error = :error
        WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'status' => $status,
        'sent_at' => $status === 'enviado' ? date('Y-m-d H:i:s') : null,
        'error' => $error,
    ]);

    if (is_array($item) && in_array($status, ['enviado', 'falhou'], true)) {
        crm_record_followup_step_history($item, $status, $error);
    }

    return $stmt->rowCount() > 0;
}

function crm_record_followup_step_history(array $queueItem, string $status, ?string $error = null): void
{
    $stmt = crm_db()->prepare(
        'INSERT INTO followup_step_history
        (lead_id, flow_id, step_order, status, sent_at, error, created_at, updated_at)
        VALUES
        (:lead_id, :flow_id, :step_order, :status, :sent_at, :error, :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            sent_at = VALUES(sent_at),
            error = VALUES(error),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'lead_id' => $queueItem['lead_id'],
        'flow_id' => $queueItem['flow_id'],
        'step_order' => $queueItem['step_order'],
        'status' => $status,
        'sent_at' => $status === 'enviado' ? date('Y-m-d H:i:s') : null,
        'error' => $error,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

function crm_delete_lead(string $id): bool
{
    $stmt = crm_db()->prepare('DELETE FROM leads WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}

function crm_find_lead(string $id): ?array
{
    $stmt = crm_db()->prepare('SELECT * FROM leads WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $lead = $stmt->fetch();

    return is_array($lead) ? $lead : null;
}

function crm_update_whatsapp_status(string $id, string $status, ?string $error = null): bool
{
    $stmt = crm_db()->prepare(
        'UPDATE leads
        SET whatsapp_status = :status,
            whatsapp_sent_at = :sent_at,
            whatsapp_error = :error,
            updated_at = :updated_at
        WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'status' => $status,
        'sent_at' => $status === 'enviado' ? date('Y-m-d H:i:s') : null,
        'error' => $error,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return $stmt->rowCount() > 0;
}
