<?php

use App\Repositories\AuditLogRepository;
use App\Repositories\ProcessRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_process_access();
$id = (int) ($_GET['id'] ?? 0);
$process = (new ProcessRepository())->find($id, $user);
if (!$process) {
    flash('Processo não encontrado.', 'danger');
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
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-light" href="<?= url('process_form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Editar</a>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-8">
            <div class="app-card">
                <div class="card-head"><h2>Informações principais</h2></div>
                <dl class="detail-grid">
                    <dt>Responsável pela resposta</dt><dd><?= e($process['response_owner'] ?: '-') ?></dd>
                    <dt>Órgão solicitante</dt><dd><?= e($process['requesting_agency'] ?: '-') ?></dd>
                    <dt>Responsável pela revisão</dt><dd><?= e($process['review_owner'] ?: '-') ?></dd>
                    <dt>Bloco interno</dt><dd><?= e($process['internal_block'] ?: '-') ?></dd>
                    <dt>Tipo de prazo</dt><dd><?= ($process['deadline_type'] ?? 'data') === 'tempo_habil' ? 'Tempo Hábil' : 'Data definida' ?></dd>
                    <dt>Prazo externo/MDS</dt><dd><?= e(format_date($process['external_deadline_mds'])) ?></dd>
                    <dt>Data envio GAB</dt><dd><?= e(format_date($process['gab_sent_date'])) ?></dd>
                </dl>
                <hr>
                <h3 class="h6">Descrição detalhada</h3>
                <p class="text-secondary"><?= nl2br(e($process['detailed_description'] ?: '-')) ?></p>
                <h3 class="h6">Comentários/anotações</h3>
                <p class="text-secondary mb-0"><?= nl2br(e($process['notes'] ?: '-')) ?></p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card">
                <div class="card-head"><h2>Fluxo</h2></div>
                <ul class="status-timeline">
                    <li><span>Resposta</span><?= workflow_badge($process['response_status']) ?></li>
                    <li><span>Revisão Andrea</span><?= workflow_badge($process['andrea_review_status']) ?></li>
                    <li><span>Assinado</span><?= workflow_badge($process['signed_status']) ?></li>
                    <li><span>Enviado Gab</span><?= workflow_badge($process['sent_gab_status']) ?></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="app-card mt-4">
        <div class="card-head"><h2>Histórico recente</h2><a href="<?= url('audit.php?process_id=' . $id) ?>">Ver completo</a></div>
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
