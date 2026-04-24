<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_audit_access();
$moduleReady = audits_schema_ready();
$repo = new AuditRepository();

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'audit_year' => trim((string) ($_GET['audit_year'] ?? '')),
    'process_status' => trim((string) ($_GET['process_status'] ?? '')),
    'requesting_body' => trim((string) ($_GET['requesting_body'] ?? '')),
    'theme' => trim((string) ($_GET['theme'] ?? '')),
    'classification' => trim((string) ($_GET['classification'] ?? '')),
    'audit_phase' => trim((string) ($_GET['audit_phase'] ?? '')),
    'current_owner' => trim((string) ($_GET['current_owner'] ?? '')),
];

$selectedItemStatus = trim((string) ($_GET['item_status_group'] ?? ''));
$selectedItemKind = trim((string) ($_GET['item_kind'] ?? ''));
$resultAnchor = 'audit-results';
$rdcAnchor = 'rdc-status-section';

$audits = [];
$metrics = [
    'total' => 0,
    'diligence_report' => 0,
    'monitoring_pending' => 0,
    'first_monitoring' => 0,
    'second_monitoring' => 0,
    'third_monitoring' => 0,
    'fourth_monitoring' => 0,
    'other_phases' => 0,
];
$filterOptions = [
    'audit_year' => [],
    'process_status' => [],
    'requesting_body' => [],
    'theme' => [],
    'classification' => [],
    'audit_phase' => [],
    'current_owner' => [],
];
$byBody = [];
$diligencePhase = [];
$byType = [];
$itemTotals = [];
$itemImplementation = [];
$itemCards = [];
$timelineEntries = [];

$buildUrl = function (array $overrides = [], string $anchor = 'audit-results') use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides), static fn ($value) => $value !== '');
    $query = http_build_query($params);
    $base = url('audits.php');
    return $base . ($query !== '' ? '?' . $query : '') . '#' . $anchor;
};

if ($moduleReady) {
    $audits = $repo->list($filters + ['item_kind' => $selectedItemKind]);
    $metrics = $repo->dashboardMetrics($filters);

    foreach (array_keys($filterOptions) as $field) {
        $filterOptions[$field] = $repo->distinctValues($field);
    }

    $byBody = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['requesting_body' => $row['label']], $resultAnchor)],
        $repo->countsByBody($filters)
    );
    $diligencePhase = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['audit_phase' => $row['label']], $resultAnchor)],
        $repo->diligencePhaseOverview($filters)
    );
    $byType = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['audit_type' => $row['label']], $resultAnchor)],
        $repo->countsByType($filters)
    );
    $itemTotals = $repo->itemTotalsPerAudit($filters);
    $itemImplementation = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['item_status_group' => $row['label']], $rdcAnchor)],
        $repo->itemImplementationSummary($filters)
    );
    $itemCards = $repo->itemsByStatusGroup($selectedItemStatus !== '' ? $selectedItemStatus : null, 18, $filters);
    $timelineEntries = $repo->timelineEntries(2026, $filters);
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
            <p class="text-secondary mb-0">Visao executiva, cadastro manual, acompanhamento de RDC e linha do tempo de prazos de 2026.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_form.php') : '#' ?>"><i class="bi bi-plus-lg"></i> Nova auditoria</a>
            <a class="btn btn-outline-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_import.php') : '#' ?>"><i class="bi bi-cloud-upload"></i> Importar base tratada</a>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-clockwise"></i> Limpar filtros</a>
        </div>
    </section>

    <form class="filter-card" id="audit-filters" method="get" action="<?= url('audits.php#' . $resultAnchor) ?>">
        <div class="card-head">
            <h2>Filtro de Auditorias</h2>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Buscar auditoria</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Codigo, NUP, tema ou objetivo" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <?php foreach ([
                'audit_year' => 'Ano',
                'process_status' => 'Status do processo',
                'requesting_body' => 'Orgao',
                'theme' => 'Tema',
                'classification' => 'Classificacao',
                'audit_phase' => 'Fase da Auditoria',
                'current_owner' => 'Responsavel Atual',
            ] as $field => $label): ?>
                <div class="col-lg-4 col-xl-3">
                    <label class="form-label"><?= e($label) ?></label>
                    <select class="form-select" name="<?= e($field) ?>" <?= !$moduleReady ? 'disabled' : '' ?>>
                        <option value="">Todos</option>
                        <?php foreach ($filterOptions[$field] as $option): ?>
                            <option value="<?= e((string) $option['value']) ?>" <?= selected($filters[$field], (string) $option['value']) ?>><?= e((string) $option['value']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="col-lg-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit" <?= !$moduleReady ? 'disabled' : '' ?>><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="metric-grid xl">
        <article class="metric-card">
            <span>Numero de auditorias</span>
            <strong><?= (int) $metrics['total'] ?></strong>
            <i class="bi bi-shield-check"></i>
        </article>
        <article class="metric-card warning">
            <span>Em diligencia/Relatorio</span>
            <strong><?= (int) $metrics['diligence_report'] ?></strong>
            <i class="bi bi-exclamation-circle"></i>
        </article>
        <article class="metric-card muted">
            <span>Monitoramento a iniciar</span>
            <strong><?= (int) $metrics['monitoring_pending'] ?></strong>
            <i class="bi bi-hourglass-split"></i>
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
        <article class="metric-card success">
            <span>Demais fases</span>
            <strong><?= (int) $metrics['other_phases'] ?></strong>
            <i class="bi bi-grid"></i>
        </article>
    </section>

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
                                class="timeline-chip <?= $entry['deadline_is_current'] ? 'timeline-chip-current' : '' ?> <?= !empty($entry['is_dgba']) ? 'timeline-chip-dgba' : '' ?>"
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
                <div class="card-head"><h2>Diligencia ou fase atual</h2></div>
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
            <div class="app-card h-100" id="<?= e($rdcAnchor) ?>">
                <div class="card-head"><h2>Situacao dos RDC</h2></div>
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
                    <h2>Pontos de controle dos RDC</h2>
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

    <section class="app-card mt-4 p-0 overflow-hidden" id="<?= e($resultAnchor) ?>">
        <div class="card-head p-4 pb-0">
            <h2>Auditorias</h2>
            <span class="text-secondary"><?= count($audits) ?> resultado(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Codigo</th><th>Orgao</th><th>Tema</th><th>Fase</th><th>Prazo</th><th>Itens</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($audits as $audit): ?>
                        <tr class="clickable-row" data-href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>">
                            <td><strong><?= e($audit['audit_code']) ?></strong><small><?= e($audit['audit_nup']) ?></small></td>
                            <td><?= e($audit['requesting_body']) ?></td>
                            <td class="text-truncate-cell"><?= e($audit['theme'] ?: '-') ?></td>
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
