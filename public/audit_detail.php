<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_audit_access();
$repo = new AuditRepository();
$audit = $repo->find((int) ($_GET['id'] ?? 0));
if (!$audit) {
    flash('Auditoria nao encontrada.', 'danger');
    redirect('audits.php');
}
$items = $repo->items((int) $audit['id']);
$counts = ['DETERMINAÇÃO' => 0, 'RECOMENDAÇÃO' => 0, 'CIÊNCIA' => 0];
foreach ($items as $item) {
    if (isset($counts[$item['item_kind']])) {
        $counts[$item['item_kind']]++;
    }
}

$pageTitle = 'Detalhe da auditoria';
$activeNav = 'audits';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <section class="detail-hero">
        <div>
            <p class="section-kicker">Identificação da auditoria</p>
            <h1><?= e($audit['audit_code']) ?></h1>
            <p><?= e($audit['theme'] ?: $audit['audit_type']) ?></p>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge text-bg-light"><?= e($audit['requesting_body']) ?></span>
                <span class="badge text-bg-light"><?= e($audit['audit_phase'] ?: 'Sem fase') ?></span>
                <?= $audit['has_diligence'] ? '<span class="badge text-bg-warning">Em diligência</span>' : '<span class="badge text-bg-secondary">Sem diligência</span>' ?>
            </div>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="metric-grid">
        <article class="metric-card"><span>Determinações</span><strong><?= $counts['DETERMINAÇÃO'] ?></strong><i class="bi bi-list-check"></i></article>
        <article class="metric-card"><span>Recomendações</span><strong><?= $counts['RECOMENDAÇÃO'] ?></strong><i class="bi bi-journal-check"></i></article>
        <article class="metric-card"><span>Ciências</span><strong><?= $counts['CIÊNCIA'] ?></strong><i class="bi bi-info-circle"></i></article>
        <article class="metric-card"><span>Total de itens</span><strong><?= count($items) ?></strong><i class="bi bi-diagram-3"></i></article>
    </section>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Dados principais</h2></div>
                <dl class="detail-grid">
                    <dt>NUP</dt><dd><?= e($audit['audit_nup']) ?></dd>
                    <dt>Ano</dt><dd><?= e((string) $audit['audit_year']) ?></dd>
                    <dt>Status do processo</dt><dd><?= e($audit['process_status']) ?></dd>
                    <dt>Órgão</dt><dd><?= e($audit['requesting_body']) ?></dd>
                    <dt>Tipo</dt><dd><?= e($audit['audit_type']) ?></dd>
                    <dt>Classificação</dt><dd><?= e($audit['classification']) ?></dd>
                    <dt>Fase</dt><dd><?= e($audit['audit_phase']) ?></dd>
                    <dt>Responsável atual</dt><dd><?= e($audit['current_owner']) ?></dd>
                    <dt>Data de início</dt><dd><?= e(format_date($audit['start_date'])) ?></dd>
                    <dt>Acórdão/Relatório</dt><dd><?= e($audit['accord_report'] ?: '-') ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Escopo e observações</h2></div>
                <h3 class="h6">Objetivo</h3>
                <p class="text-secondary"><?= nl2br(e($audit['objective'] ?: '-')) ?></p>
                <h3 class="h6">Tema</h3>
                <p class="text-secondary"><?= nl2br(e($audit['theme'] ?: '-')) ?></p>
                <h3 class="h6">Observações</h3>
                <p class="text-secondary mb-0"><?= nl2br(e($audit['notes'] ?: '-')) ?></p>
            </div>
        </div>
    </section>

    <section class="app-card mt-4 p-0 overflow-hidden">
        <div class="card-head p-4 pb-0"><h2>Itens vinculados</h2></div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Tipo</th><th>Descrição</th><th>Status órgão</th><th>Status DGBA</th><th>Etapa 03</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="badge text-bg-light"><?= e($item['item_kind']) ?></span></td>
                            <td class="audit-item-text"><?= e($item['item_description']) ?></td>
                            <td><?= e($item['control_body_status'] ?: '-') ?></td>
                            <td><?= e($item['dgba_status'] ?: '-') ?></td>
                            <td><?= e($item['stage3_status'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?>
                        <tr><td colspan="5"><div class="empty-state">Essa auditoria nao possui itens detalhados importados.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

