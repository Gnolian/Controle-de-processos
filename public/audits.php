<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_audit_access();
$moduleReady = audits_schema_ready();
$repo = new AuditRepository();

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'requesting_body' => trim((string) ($_GET['requesting_body'] ?? '')),
    'audit_type' => trim((string) ($_GET['audit_type'] ?? '')),
    'audit_phase' => trim((string) ($_GET['audit_phase'] ?? '')),
    'process_status' => trim((string) ($_GET['process_status'] ?? '')),
    'diligence' => trim((string) ($_GET['diligence'] ?? '')),
    'item_kind' => trim((string) ($_GET['item_kind'] ?? '')),
];

$selectedItemStatus = trim((string) ($_GET['item_status_group'] ?? ''));

$audits = [];
$metrics = [
    'total' => 0,
    'in_diligence' => 0,
    'first_monitoring' => 0,
    'second_monitoring' => 0,
    'third_monitoring' => 0,
    'fourth_monitoring' => 0,
];
$byBody = [];
$diligencePhase = [];
$byType = [];
$itemTotals = [];
$itemImplementation = [];
$itemCards = [];
$timelineEntries = [];

if ($moduleReady) {
    $audits = $repo->list($filters);
    $metrics = $repo->dashboardMetrics();
    $byBody = array_map(
        fn (array $row) => $row + ['url' => url('audits.php?requesting_body=' . urlencode($row['label']))],
        $repo->countsByBody()
    );
    $diligencePhase = array_map(function (array $row): array {
        $query = $row['label'] === 'Em diligencia'
            ? 'audit_phase=' . urlencode($row['label'])
            : 'audit_phase=' . urlencode($row['label']);
        return $row + ['url' => url('audits.php?' . $query)];
    }, $repo->diligencePhaseOverview());
    $byType = array_map(
        fn (array $row) => $row + ['url' => url('audits.php?audit_type=' . urlencode($row['label']))],
        $repo->countsByType()
    );
    $itemTotals = $repo->itemTotalsPerAudit();
    $itemImplementation = array_map(
        fn (array $row) => $row + ['url' => url('audits.php?item_status_group=' . urlencode($row['label']))],
        $repo->itemImplementationSummary()
    );
    $itemCards = $repo->itemsByStatusGroup($selectedItemStatus !== '' ? $selectedItemStatus : null);
    $timelineEntries = $repo->timelineEntries(2026);
}

$timelineColumns = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$timelineByMonth = array_fill(1, 12, []);
foreach ($timelineEntries as $entry) {
    $index = max(1, min(12, (int) $entry['month_index']));
    $timelineByMonth[$index][] = $entry;
}

$pageTitle = 'Auditorias';
$activeNav = 'audits';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <?php if (!$moduleReady): ?>
        <div class="alert alert-warning shadow-sm">
            O modulo de auditorias ainda nao foi instalado neste banco. No phpMyAdmin, importe primeiro:
            <strong>database/migrations/004_add_audits_module.sql</strong> e depois
            <strong>database/migrations/005_expand_audits_for_timeline.sql</strong>.
        </div>
    <?php endif; ?>

    <section class="hero-panel">
        <div>
            <p class="section-kicker">CGU e TCU</p>
            <h1>Painel de auditorias</h1>
            <p class="text-secondary mb-0">Visao executiva, cadastro manual, acompanhamento de itens e linha do tempo de prazos de 2026.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_form.php') : '#' ?>"><i class="bi bi-plus-lg"></i> Nova auditoria</a>
            <a class="btn btn-outline-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_import.php') : '#' ?>"><i class="bi bi-cloud-upload"></i> Importar base tratada</a>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-clockwise"></i> Limpar filtros</a>
        </div>
    </section>

    <section class="metric-grid xl">
        <article class="metric-card">
            <span>Numero de auditorias</span>
            <strong><?= (int) $metrics['total'] ?></strong>
            <i class="bi bi-shield-check"></i>
        </article>
        <article class="metric-card warning">
            <span>Em diligencia</span>
            <strong><?= (int) $metrics['in_diligence'] ?></strong>
            <i class="bi bi-exclamation-circle"></i>
        </article>
        <article class="metric-card">
            <span>1º monitoramento</span>
            <strong><?= (int) $metrics['first_monitoring'] ?></strong>
            <i class="bi bi-1-circle"></i>
        </article>
        <article class="metric-card">
            <span>2º monitoramento</span>
            <strong><?= (int) $metrics['second_monitoring'] ?></strong>
            <i class="bi bi-2-circle"></i>
        </article>
        <article class="metric-card">
            <span>3º monitoramento</span>
            <strong><?= (int) $metrics['third_monitoring'] ?></strong>
            <i class="bi bi-3-circle"></i>
        </article>
        <article class="metric-card">
            <span>4º monitoramento</span>
            <strong><?= (int) $metrics['fourth_monitoring'] ?></strong>
            <i class="bi bi-4-circle"></i>
        </article>
    </section>

    <form class="filter-card" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Buscar auditoria</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Codigo, NUP, tema ou objetivo" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Orgao</label>
                <input class="form-control" name="requesting_body" value="<?= e($filters['requesting_body']) ?>" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Tipo</label>
                <input class="form-control" name="audit_type" value="<?= e($filters['audit_type']) ?>" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Fase</label>
                <input class="form-control" name="audit_phase" value="<?= e($filters['audit_phase']) ?>" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Diligencia</label>
                <select class="form-select" name="diligence" <?= !$moduleReady ? 'disabled' : '' ?>>
                    <option value="">Todas</option>
                    <option value="1" <?= selected($filters['diligence'], '1') ?>>Em diligencia</option>
                    <option value="0" <?= selected($filters['diligence'], '0') ?>>Sem diligencia</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Tipo de item</label>
                <select class="form-select" name="item_kind" <?= !$moduleReady ? 'disabled' : '' ?>>
                    <option value="">Todos</option>
                    <option value="DETERMINACAO" <?= selected($filters['item_kind'], 'DETERMINACAO') ?>>Determinacao</option>
                    <option value="RECOMENDACAO" <?= selected($filters['item_kind'], 'RECOMENDACAO') ?>>Recomendacao</option>
                    <option value="CIENCIA" <?= selected($filters['item_kind'], 'CIENCIA') ?>>Ciencia</option>
                </select>
            </div>
            <div class="col-lg-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit" <?= !$moduleReady ? 'disabled' : '' ?>><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="app-card mb-4">
        <div class="card-head">
            <h2>Linha do tempo 2026</h2>
            <span class="text-secondary">Passe o mouse para ver o ID e clique para abrir o resumo da auditoria.</span>
        </div>
        <div class="timeline-board">
            <?php foreach ($timelineColumns as $offset => $label): $index = $offset + 1; ?>
                <div class="timeline-month <?= (int) date('n') === $index ? 'timeline-month-current' : '' ?>">
                    <div class="timeline-month-head"><?= e($label) ?></div>
                    <div class="timeline-month-body">
                        <?php foreach ($timelineByMonth[$index] as $entry): ?>
                            <button
                                type="button"
                                class="timeline-chip <?= $entry['deadline_is_current'] ? 'timeline-chip-current' : '' ?>"
                                title="<?= e($entry['audit_code']) ?>"
                                data-timeline-entry='<?= e(json_encode($entry, JSON_UNESCAPED_UNICODE)) ?>'
                            >
                                <span><?= e($entry['audit_code']) ?></span>
                                <small><?= e($entry['deadline_is_current'] ? 'Hoje' : $entry['deadline_label']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <?php if (!$timelineByMonth[$index]): ?>
                            <div class="timeline-empty">-</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Auditorias por orgao solicitante</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($byBody, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Diligencias ou fase atual</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($diligencePhase, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-4">
            <div class="app-card h-100">
                <div class="card-head"><h2>Por tipo de auditoria</h2></div>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($byType, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card h-100">
                <div class="card-head"><h2>Itens por situacao</h2></div>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($itemImplementation, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="app-card h-100">
                <div class="card-head"><h2>Determinacoes, recomendacoes e ciencia por auditoria</h2></div>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead><tr><th>Auditoria</th><th>Orgao</th><th>Determ.</th><th>Recom.</th><th>Ciencia</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($itemTotals as $row): ?>
                                <tr class="clickable-row" data-href="<?= url('audit_detail.php?id=' . (int) $row['id']) ?>">
                                    <td><strong><?= e($row['audit_code']) ?></strong><small><?= e($row['audit_nup']) ?></small></td>
                                    <td><?= e($row['requesting_body']) ?></td>
                                    <td><?= (int) $row['determinacoes'] ?></td>
                                    <td><?= (int) $row['recomendacoes'] ?></td>
                                    <td><?= (int) $row['ciencias'] ?></td>
                                    <td><strong><?= (int) $row['total'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$itemTotals): ?>
                                <tr><td colspan="6"><div class="empty-state">Nenhum item consolidado encontrado.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="app-card h-100">
                <div class="card-head">
                    <h2>Pontos de controle por situacao</h2>
                    <span class="text-secondary"><?= $selectedItemStatus !== '' ? e($selectedItemStatus) : 'Todos os status' ?></span>
                </div>
                <div class="audit-point-cards">
                    <?php foreach ($itemCards as $item): ?>
                        <article class="audit-point-card">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <strong><?= e($item['audit_code']) ?></strong>
                                    <small><?= e(audit_item_kind_label($item['item_kind'])) ?></small>
                                </div>
                                <span class="badge text-bg-light"><?= e($item['status_group']) ?></span>
                            </div>
                            <p class="mb-2 text-secondary small"><?= e($item['audit_nup']) ?></p>
                            <p class="mb-2"><?= e($item['item_control_point'] ?: '-') ?></p>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('audit_detail.php?id=' . (int) $item['audit_id']) ?>">Abrir auditoria</a>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$itemCards): ?>
                        <div class="empty-state">Nenhum item encontrado para a situacao selecionada.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="app-card mt-4 p-0 overflow-hidden">
        <div class="card-head p-4 pb-0">
            <h2>Auditorias</h2>
            <span class="text-secondary"><?= count($audits) ?> resultado(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Codigo</th><th>Orgao</th><th>Tipo</th><th>Fase</th><th>Prazo</th><th>Itens</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($audits as $audit): ?>
                        <tr class="clickable-row" data-href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>">
                            <td><strong><?= e($audit['audit_code']) ?></strong><small><?= e($audit['audit_nup']) ?></small></td>
                            <td><?= e($audit['requesting_body']) ?></td>
                            <td><?= e($audit['audit_type']) ?></td>
                            <td><?= e($audit['audit_phase'] ?: '-') ?></td>
                            <td>
                                <span class="badge rounded-pill <?= !empty($audit['deadline_is_current']) ? 'text-bg-warning' : 'text-bg-light' ?>">
                                    <?= e($audit['deadline_label'] ?: ($audit['deadline_date'] ? format_date($audit['deadline_date']) : 'Sem prazo')) ?>
                                </span>
                            </td>
                            <td><?= (int) $audit['total_items'] ?></td>
                            <td class="text-end d-flex gap-2 justify-content-end">
                                <a class="btn btn-sm btn-light" href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-light" href="<?= url('audit_form.php?id=' . (int) $audit['id']) ?>"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$audits): ?>
                        <tr><td colspan="7"><div class="empty-state">Nenhuma auditoria encontrada.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="timelineAuditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="section-kicker mb-1">Linha do tempo 2026</p>
                    <h2 class="modal-title fs-4 mb-0" data-timeline-title>Auditoria</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="detail-grid timeline-detail-grid">
                    <dt>ID da auditoria</dt><dd data-timeline-id>-</dd>
                    <dt>NUP</dt><dd data-timeline-nup>-</dd>
                    <dt>Orgao de controle</dt><dd data-timeline-body>-</dd>
                    <dt>Tema</dt><dd data-timeline-theme>-</dd>
                    <dt>Fase da auditoria</dt><dd data-timeline-phase>-</dd>
                    <dt>Responsavel atual</dt><dd data-timeline-owner>-</dd>
                    <dt>Proximo prazo</dt><dd data-timeline-deadline>-</dd>
                    <dt>Ponto de controle</dt><dd data-timeline-summary>-</dd>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-primary" data-timeline-link>Abrir auditoria</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
