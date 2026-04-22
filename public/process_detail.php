<?php

use App\Repositories\AuditLogRepository;
use App\Repositories\ProcessRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$process = (new ProcessRepository())->find($id, $user);
if (!$process) {
    flash('Processo nao encontrado.', 'danger');
    redirect('processes.php');
}

$logs = (new AuditLogRepository())->search(['process_id' => $id]);
$pageTitle = 'Detalhes do processo';
$activeNav = 'processes';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="detail-hero">
        <div>
            <p class="section-kicker">Processo</p>
            <h1><?= e($process['process_number']) ?></h1>
            <p><?= e($process['general_description']) ?></p>
            <div class="d-flex gap-2 flex-wrap">
                <?= status_badge($process['status']) ?>
                <?= deadline_badge($process) ?>
                <?= sync_badge($process['sync_status'] ?? null) ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-light" href="<?= url('process_form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Editar</a>
            <a class="btn btn-primary" href="<?= url('sync_action.php?id=' . $id) ?>"><i class="bi bi-arrow-repeat"></i> Reenviar para planilha</a>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-8">
            <div class="app-card">
                <div class="card-head"><h2>Informacoes principais</h2></div>
                <dl class="detail-grid">
                    <dt>Responsavel pela resposta</dt><dd><?= e($process['response_owner'] ?: '-') ?></dd>
                    <dt>Orgao solicitante</dt><dd><?= e($process['requesting_agency'] ?: '-') ?></dd>
                    <dt>Responsavel pela revisao</dt><dd><?= e($process['review_owner'] ?: '-') ?></dd>
                    <dt>Bloco interno</dt><dd><?= e($process['internal_block'] ?: '-') ?></dd>
                    <dt>Prazo externo/MDS</dt><dd><?= e(format_date($process['external_deadline_mds'])) ?></dd>
                    <dt>Data envio GAB</dt><dd><?= e(format_date($process['gab_sent_date'])) ?></dd>
                </dl>
                <hr>
                <h3 class="h6">Descricao detalhada</h3>
                <p class="text-secondary"><?= nl2br(e($process['detailed_description'] ?: '-')) ?></p>
                <h3 class="h6">Comentarios/anotacoes</h3>
                <p class="text-secondary mb-0"><?= nl2br(e($process['notes'] ?: '-')) ?></p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card">
                <div class="card-head"><h2>Fluxo</h2></div>
                <ul class="status-timeline">
                    <li><span>Resposta</span><?= workflow_badge($process['response_status']) ?></li>
                    <li><span>Revisao Andrea</span><?= workflow_badge($process['andrea_review_status']) ?></li>
                    <li><span>Assinado</span><?= workflow_badge($process['signed_status']) ?></li>
                    <li><span>Enviado Gab</span><?= workflow_badge($process['sent_gab_status']) ?></li>
                </ul>
            </div>
            <div class="app-card mt-4">
                <div class="card-head"><h2>Sincronizacao</h2></div>
                <p class="mb-1"><?= sync_badge($process['sync_status'] ?? null) ?></p>
                <small class="text-secondary d-block">Ultima sync: <?= e($process['last_synced_at'] ?: '-') ?></small>
                <?php if (!empty($process['last_error'])): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= e($process['last_error']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="app-card mt-4">
        <div class="card-head"><h2>Historico recente</h2><a href="<?= url('audit.php?process_id=' . $id) ?>">Ver completo</a></div>
        <div class="table-responsive">
            <table class="table modern-table">
                <thead><tr><th>Quando</th><th>Campo</th><th>Antes</th><th>Depois</th><th>Origem</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($logs, 0, 10) as $log): ?>
                        <tr>
                            <td><?= e($log['created_at']) ?></td>
                            <td><?= e($log['field_name']) ?></td>
                            <td><?= e($log['old_value'] ?: '-') ?></td>
                            <td><?= e($log['new_value'] ?: '-') ?></td>
                            <td><?= e($log['origin']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

