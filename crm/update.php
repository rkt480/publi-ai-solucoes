<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/meta-capi.php';

crm_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_require_valid_csrf();

    $id = (string) ($_POST['id'] ?? '');
    $status = (string) ($_POST['status'] ?? 'novo');
    $leadBeforeUpdate = crm_find_lead($id);

    if (crm_update_lead($id, [
        'status' => $status,
        'notes' => $_POST['notes'] ?? '',
    ]) && is_array($leadBeforeUpdate) && (string) ($leadBeforeUpdate['status'] ?? '') !== $status) {
        try {
            $leadBeforeUpdate['status'] = $status;
            $metaResult = meta_capi_send_status_event($leadBeforeUpdate, $status);

            if (($metaResult['ok'] ?? false) !== true && ($metaResult['skipped'] ?? false) !== true) {
                error_log('Erro Meta CAPI status ' . $status . ' lead ' . $id . ': ' . (string) ($metaResult['error'] ?? 'Erro desconhecido.'));
            }
        } catch (Throwable $error) {
            error_log('Erro Meta CAPI status ' . $status . ' lead ' . $id . ': ' . $error->getMessage());
        }
    }
}

header('Location: index.php');
exit;
