<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_audit_access();
$moduleReady = audits_schema_ready();
if (!$moduleReady) {
    flash('O módulo de auditorias precisa das migrations 004_add_audits_module.sql e 005_expand_audits_for_timeline.sql.', 'danger');
    redirect('audits.php');
}

$repo = new AuditRepository();
$audit = $repo->find((int) ($_GET['id'] ?? 0));
if (!$audit) {
    flash('Auditoria não encontrada.', 'danger');
    redirect('audits.php');
}

$items = $repo->items((int) $audit['id']);
$counts = ['DETERMINACAO' => 0, 'RECOMENDACAO' => 0, 'CIENCIA' => 0];
foreach ($items as $item) {
    $kind = strtoupper(trim((string) $item['item_kind']));
    $kind = str_replace(
        ['DETERMINAÇÃO', 'RECOMENDAÇÃO', 'CIÊNCIA', 'DETERMINAÃ‡ÃƒO', 'RECOMENDAÃ‡ÃƒO', 'CIÃŠNCIA'],
        ['DETERMINACAO', 'RECOMENDACAO', 'CIENCIA', 'DETERMINACAO', 'RECOMENDACAO', 'CIENCIA'],
        $kind
    );
    if (isset($counts[$kind])) {
        $counts[$kind]++;
    }
}

$nextDeadline = $audit['deadline_label'] ?: ($audit['deadline_date'] ? format_date($audit['deadline_date']) : '-');
$summary = $audit['control_summary'] ?: ($audit['related_processes'] ?: '-');

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
                <span class="badge <?= !empty($audit['deadline_is_current']) ? 'text-bg-warning' : 'text-bg-light' ?>"><?= e($nextDeadline) ?></span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="<?= url('audit_form.php?id=' . (int) $audit['id']) ?>"><i class="bi bi-pencil"></i> Editar</a>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card"><span>Determinações</span><strong><?= $counts['DETERMINACAO'] ?></strong><i class="bi bi-list-check"></i></article>
        <article class="metric-card"><span>Recomendações</span><strong><?= $counts['RECOMENDACAO'] ?></strong><i class="bi bi-journal-check"></i></article>
        <article class="metric-card"><span>Ciências</span><strong><?= $counts['CIENCIA'] ?></strong><i class="bi bi-info-circle"></i></article>
        <article class="metric-card"><span>Total de itens</span><strong><?= count($items) ?></strong><i class="bi bi-diagram-3"></i></article>
    </section>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Dados principais</h2></div>
                <dl class="detail-grid">
                    <dt>ID</dt><dd><?= (int) $audit['id'] ?></dd>
                    <dt>NUP</dt><dd><?= e($audit['audit_nup']) ?></dd>
                    <dt>Ano</dt><dd><?= e((string) $audit['audit_year']) ?></dd>
                    <dt>Status da auditoria</dt><dd><?= e($audit['process_status'] ?: '-') ?></dd>
                    <dt>Órgão de controle</dt><dd><?= e($audit['requesting_body']) ?></dd>
                    <dt>Tipo</dt><dd><?= e($audit['audit_type']) ?></dd>
                    <dt>Classificação</dt><dd><?= e($audit['classification'] ?: '-') ?></dd>
                    <dt>Fase</dt><dd><?= e($audit['audit_phase'] ?: '-') ?></dd>
                    <dt>Responsável atual</dt><dd><?= e($audit['current_owner'] ?: '-') ?></dd>
                    <dt>Data de início</dt><dd><?= e(format_date($audit['start_date'])) ?></dd>
                    <dt>Última resposta</dt><dd><?= e(format_date($audit['last_date_response'])) ?></dd>
                    <dt>Próximo prazo</dt><dd><?= e($nextDeadline) ?><?= !empty($audit['flag_estimated']) ? ' (estimado)' : '' ?></dd>
                    <dt>Em diligência</dt><dd><?= !empty($audit['has_diligence']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Status da etapa 3</dt><dd><?= e($audit['stage3_status'] ?: '-') ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Escopo e ponto de controle</h2></div>
                <h3 class="h6">Objetivo</h3>
                <p class="text-secondary"><?= nl2br(e($audit['objective'] ?: '-')) ?></p>
                <h3 class="h6">Tema</h3>
                <p class="text-secondary"><?= nl2br(e($audit['theme'] ?: '-')) ?></p>
                <h3 class="h6">Resumo do ponto de controle</h3>
                <p class="text-secondary"><?= nl2br(e($summary)) ?></p>
                <h3 class="h6">Observações</h3>
                <p class="text-secondary mb-0"><?= nl2br(e($audit['notes'] ?: '-')) ?></p>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Etapa 2</h2></div>
                <dl class="detail-grid">
                    <dt>Início</dt><dd><?= e(format_date($audit['stage2_start_date'])) ?></dd>
                    <dt>Em diligência</dt><dd><?= !empty($audit['flag_stage2_diligence']) ? 'Sim' : 'Não' ?></dd>
                    <dt>Última resposta</dt><dd><?= e(format_date($audit['stage2_date_last_response_diligence'])) ?></dd>
                    <dt>Documento preliminar</dt><dd><?= e($audit['stage2_preliminary_document'] ?: '-') ?></dd>
                    <dt>Prazo em dias</dt><dd><?= e((string) ($audit['stage2_deadline_days'] ?? '-')) ?></dd>
                    <dt>Prazo final</dt><dd><?= e(format_date($audit['stage2_final_deadline'])) ?></dd>
                    <dt>Status</dt><dd><?= e($audit['stage2_status'] ?: '-') ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Monitoramentos</h2></div>
                <ul class="status-timeline">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <li>
                            <div>
                                <strong><?= $i ?>&ordm; monitoramento</strong>
                                <small class="d-block text-secondary"><?= e(format_date($audit["monitoring{$i}_start_date"] ?? null)) ?> até <?= e(format_date($audit["monitoring{$i}_final_deadline"] ?? null)) ?></small>
                            </div>
                            <span class="audit-value"><?= e($audit["monitoring{$i}_status"] ?: '-') ?></span>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="app-card mt-4 p-0 overflow-hidden">
        <div class="card-head p-4 pb-0"><h2>Itens vinculados</h2></div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Tipo</th><th>Descrição</th><th>Status órgão</th><th>Status DGBA</th><th>Status geral</th><th>Ponto de controle</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="badge text-bg-light"><?= e(audit_item_kind_label($item['item_kind'])) ?></span></td>
                            <td class="audit-item-text"><?= e($item['item_description']) ?></td>
                            <td><?= e($item['control_body_status'] ?: '-') ?></td>
                            <td><?= e($item['dgba_status'] ?: '-') ?></td>
                            <td><?= e($item['status_geral'] ?: '-') ?></td>
                            <td class="audit-item-text"><?= e($item['item_control_point'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?>
                        <tr><td colspan="6"><div class="empty-state">Esta auditoria não possui itens detalhados.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
