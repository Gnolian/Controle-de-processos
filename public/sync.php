<?php

use App\Repositories\IntegrationSettingsRepository;
use App\Repositories\SyncRepository;
use App\Services\SyncService;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_role(['admin', 'coordenador']);
$syncRepo = new SyncRepository();
$settingsRepo = new IntegrationSettingsRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'retry_failed') {
            $count = (new SyncService())->syncFailed($user);
            flash("Reenvio solicitado para {$count} processo(s).");
        }
        if ($action === 'test_connection') {
            $result = (new SyncService())->testConnection();
            flash('Conexao validada. Colunas: ' . count($result['columns']) . '. Linhas lidas: ' . $result['rows'] . '.');
        }
        if ($action === 'import_sheet') {
            $count = (new SyncService())->importFromExcel($user);
            flash("Leitura da planilha concluida: {$count} linha(s) processada(s).");
        }
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
    redirect('sync.php');
}

$logs = $syncRepo->latest();
$failed = $syncRepo->failedProcesses();
$configured = $settingsRepo->configured();
$pageTitle = 'Sincronizacao';
$activeNav = 'sync';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="hero-panel sync">
        <div>
            <p class="section-kicker">Excel Online</p>
            <h1>Status da sincronizacao</h1>
            <p class="text-secondary mb-0">Acompanhe envios para a planilha, falhas e tentativas de reprocessamento.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_connection"><button class="btn btn-light" type="submit"><i class="bi bi-plug"></i> Testar conexao</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="import_sheet"><button class="btn btn-light" type="submit"><i class="bi bi-cloud-download"></i> Ler planilha agora</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retry_failed"><button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Reenviar falhas</button></form>
        </div>
    </section>

    <?php if (!$configured): ?>
        <div class="alert alert-warning shadow-sm">A integracao ainda nao esta totalmente configurada. Acesse <a href="<?= url('settings.php') ?>">Integracao</a> para informar credenciais e autorizar a conta Microsoft.</div>
    <?php endif; ?>

    <section class="metric-grid">
        <article class="metric-card danger"><span>Falhas pendentes</span><strong><?= count($failed) ?></strong><i class="bi bi-exclamation-octagon"></i></article>
        <article class="metric-card"><span>Logs recentes</span><strong><?= count($logs) ?></strong><i class="bi bi-list-check"></i></article>
    </section>

    <section class="app-card mt-4">
        <div class="card-head"><h2>Processos com falha</h2></div>
        <div class="table-responsive">
            <table class="table modern-table">
                <thead><tr><th>Processo</th><th>Erro</th><th class="text-end">Acao</th></tr></thead>
                <tbody>
                    <?php foreach ($failed as $process): ?>
                        <tr>
                            <td><?= e($process['process_number']) ?></td>
                            <td><?= e($process['last_error']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('sync_action.php?id=' . (int) $process['id']) ?>">Reenviar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$failed): ?><tr><td colspan="3"><div class="empty-state">Nenhuma falha pendente.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="app-card mt-4 p-0 overflow-hidden">
        <div class="card-head p-4 pb-0"><h2>Logs de sincronizacao</h2></div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Data</th><th>Processo</th><th>Status</th><th>Mensagem</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['created_at']) ?></td>
                            <td><?= e($log['process_number'] ?: '-') ?></td>
                            <td><?= sync_badge($log['status']) ?></td>
                            <td><?= e($log['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
