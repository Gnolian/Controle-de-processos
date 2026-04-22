<?php

use App\Integrations\GraphAuthClient;
use App\Repositories\IntegrationSettingsRepository;
use App\Repositories\SyncRepository;

require __DIR__ . '/../app/bootstrap.php';

require_role(['admin']);

try {
    $state = (string) ($_GET['state'] ?? '');
    if (!$state || !hash_equals((string) ($_SESSION['graph_oauth_state'] ?? ''), $state)) {
        throw new RuntimeException('Estado OAuth invalido. Tente autorizar novamente.');
    }

    $code = (string) ($_GET['code'] ?? '');
    if (!$code) {
        throw new RuntimeException('Codigo de autorizacao nao recebido.');
    }

    $repo = new IntegrationSettingsRepository();
    $tokens = (new GraphAuthClient())->exchangeCode($repo->all(), $code);
    $repo->save([
        'refresh_token' => $tokens['refresh_token'] ?? '',
        'sync_enabled' => '1',
    ]);
    (new SyncRepository())->log(null, 'info', 'Conta Microsoft autorizada com sucesso.');
    flash('Conta Microsoft autorizada. A sincronizacao foi ativada.');
} catch (Throwable $exception) {
    flash($exception->getMessage(), 'danger');
}

redirect('settings.php');

