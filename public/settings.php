<?php

use App\Integrations\GraphAuthClient;
use App\Repositories\IntegrationSettingsRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_role(['admin']);
$repo = new IntegrationSettingsRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $repo->save([
            'sync_enabled' => isset($_POST['sync_enabled']) ? '1' : '0',
            'tenant_id' => trim((string) ($_POST['tenant_id'] ?? '')),
            'client_id' => trim((string) ($_POST['client_id'] ?? '')),
            'client_secret' => trim((string) ($_POST['client_secret'] ?? '')),
            'redirect_uri' => trim((string) ($_POST['redirect_uri'] ?? '')),
            'drive_id' => trim((string) ($_POST['drive_id'] ?? '')),
            'item_id' => trim((string) ($_POST['item_id'] ?? '')),
            'table_name' => trim((string) ($_POST['table_name'] ?? '')),
            'worksheet_name' => trim((string) ($_POST['worksheet_name'] ?? '')),
        ]);
        flash('Configuracoes salvas.');
        redirect('settings.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$settings = $repo->all();
$state = bin2hex(random_bytes(16));
$_SESSION['graph_oauth_state'] = $state;
$authUrl = (new GraphAuthClient())->authorizationUrl($settings, $state);
$pageTitle = 'Integracao SharePoint';
$activeNav = 'settings';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Microsoft Graph</p>
            <h1>Configuracoes da integracao</h1>
        </div>
        <a class="btn btn-outline-primary" href="<?= e($authUrl) ?>"><i class="bi bi-microsoft"></i> Autorizar conta Microsoft</a>
    </section>

    <div class="alert alert-info shadow-sm">
        Use um aplicativo registrado no Microsoft Entra ID com permissao delegada <strong>Files.ReadWrite</strong> e <strong>offline_access</strong>. Depois de salvar as credenciais, clique em autorizar para gravar o refresh token.
    </div>

    <form method="post" class="app-card form-card">
        <?= csrf_field() ?>
        <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="sync_enabled" name="sync_enabled" <?= checked(($settings['sync_enabled'] ?? '0') === '1') ?>>
            <label class="form-check-label" for="sync_enabled">Sincronizacao automatica ativa</label>
        </div>

        <div class="form-section">
            <h2>Credenciais Microsoft</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Tenant ID<input class="form-control" name="tenant_id" value="<?= e($settings['tenant_id'] ?? '') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Client ID<input class="form-control" name="client_id" value="<?= e($settings['client_id'] ?? '') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Client Secret<input class="form-control" type="password" name="client_secret" value="<?= e($settings['client_secret'] ?? '') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Redirect URI<input class="form-control" name="redirect_uri" value="<?= e($settings['redirect_uri'] ?? '') ?>"></label></div>
            </div>
        </div>

        <div class="form-section">
            <h2>Planilha Excel Online</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Drive ID<input class="form-control" name="drive_id" value="<?= e($settings['drive_id'] ?? '') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Item ID / Workbook ID<input class="form-control" name="item_id" value="<?= e($settings['item_id'] ?? '') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Nome da tabela<input class="form-control" name="table_name" value="<?= e($settings['table_name'] ?? 'Tabela1') ?>"></label></div>
                <div class="col-md-6"><label class="form-label">Aba da planilha (opcional)<input class="form-control" name="worksheet_name" value="<?= e($settings['worksheet_name'] ?? '') ?>"></label></div>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar configuracoes</button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

