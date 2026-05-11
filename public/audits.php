<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_audit_access();
$moduleReady = audits_schema_ready();
$repo = new AuditRepository();

$getMulti = static function (string $key): array {
    $raw = $_GET[$key] ?? [];
    $values = is_array($raw) ? $raw : [$raw];
    return array_values(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        $values
    ), static fn (string $value): bool => $value !== ''));
};

$formatFilterOptionLabel = static function (string $name, string $value) use (&$formatPhaseLegend): string {
    if ($name === 'audit_phase') {
        return $formatPhaseLegend($value);
    }

    if ($name === 'item_kind') {
        return match ($value) {
            'DETERMINACAO' => 'Determinações',
            'RECOMENDACAO' => 'Recomendações',
            'CIENCIA' => 'Ciência',
            default => $value,
        };
    }

    if ($name === 'complexity') {
        return match ((string) $value) {
            '1' => '1 - Baixa',
            '2' => '2 - Média',
            '3' => '3 - Alta',
            default => $value,
        };
    }

    return $value;
};

$renderFilterBox = static function (string $name, string $label, array $options, array $selectedValues) use ($formatFilterOptionLabel): void {
    $labelsByValue = [];
    foreach ($options as $option) {
        $value = (string) ($option['value'] ?? '');
        $labelsByValue[$value] = (string) ($option['label'] ?? $formatFilterOptionLabel($name, $value));
    }

    $selectedCount = count($selectedValues);
    $summary = $selectedCount === 0
        ? 'Todos'
        : ($selectedCount === 1
            ? ($labelsByValue[$selectedValues[0]] ?? $formatFilterOptionLabel($name, $selectedValues[0]))
            : $selectedCount . ' selecionados');
    ?>
    <div class="audit-filter-field">
        <label class="form-label"><?= e($label) ?></label>
        <details class="filter-select">
            <summary>
                <span><?= e($summary) ?></span>
                <i class="bi bi-chevron-down"></i>
            </summary>
            <div class="filter-select-menu">
                <?php foreach ($options as $option): ?>
                    <?php $value = (string) ($option['value'] ?? ''); ?>
                    <label class="filter-select-option">
                        <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($value) ?>" <?= in_array($value, $selectedValues, true) ? 'checked' : '' ?>>
                        <span><?= e((string) ($option['label'] ?? $formatFilterOptionLabel($name, $value))) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
    <?php
};

$formatPhaseLegend = static function (string $value): string {
    $normalized = mb_strtoupper(trim($value), 'UTF-8');

    return match ($normalized) {
        'INICIAL/DILIGÊNCIA' => 'Em Diligência',
        'MONITORAMENTO À INICIAR' => 'Monitoramento a Iniciar',
        '1º MONITORAMENTO' => '1º Monitoramento',
        '2º MONITORAMENTO' => '2º Monitoramento',
        '3º MONITORAMENTO' => '3º Monitoramento',
        '4º MONITORAMENTO' => '4º Monitoramento',
        'ELABORAÇÃO DE RELATÓRIO FINAL' => 'Relatório Final',
        default => $value,
    };
};

$sortFilterOptions = static function (string $name, array $options) use ($formatPhaseLegend): array {
    $phaseOrder = [
        'Em Diligência' => 10,
        'Monitoramento a Iniciar' => 20,
        '1º Monitoramento' => 40,
        '2º Monitoramento' => 50,
        '3º Monitoramento' => 60,
        '4º Monitoramento' => 70,
        'Relatório Final' => 80,
    ];

    usort($options, static function (array $left, array $right) use ($name, $phaseOrder, $formatPhaseLegend): int {
        $leftValue = trim((string) ($left['value'] ?? ''));
        $rightValue = trim((string) ($right['value'] ?? ''));

        if ($name === 'audit_year') {
            return (int) $rightValue <=> (int) $leftValue;
        }

        if ($name === 'complexity') {
            return (int) $leftValue <=> (int) $rightValue;
        }

        if ($name === 'audit_phase') {
            $leftLabel = $formatPhaseLegend($leftValue);
            $rightLabel = $formatPhaseLegend($rightValue);
            $leftRank = $phaseOrder[$leftLabel] ?? 999;
            $rightRank = $phaseOrder[$rightLabel] ?? 999;
            return $leftRank <=> $rightRank ?: strcasecmp($leftLabel, $rightLabel);
        }

        return strcasecmp($leftValue, $rightValue);
    });

    return $options;
};

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'audit_year' => $getMulti('audit_year'),
    'process_status' => $getMulti('process_status'),
    'requesting_body' => $getMulti('requesting_body'),
    'audit_type' => $getMulti('audit_type'),
    'theme' => $getMulti('theme'),
    'classification' => $getMulti('classification'),
    'complexity' => $getMulti('complexity'),
    'audit_phase' => $getMulti('audit_phase'),
    'current_owner' => $getMulti('current_owner'),
    'item_kind' => $getMulti('item_kind'),
    'item_status_group' => $getMulti('item_status_group'),
];

$timelineYear = max(2026, (int) ($_GET['timeline_year'] ?? 2026));
$resultAnchor = 'audit-results';
$rdcAnchor = 'rdc-status-section';
$timelineAnchor = 'audit-timeline';

$audits = [];
$metrics = [
    'total' => 0,
    'rdc_total' => 0,
    'diligence_report' => 0,
    'monitoring_pending' => 0,
    'first_monitoring' => 0,
    'second_monitoring' => 0,
    'third_monitoring' => 0,
    'fourth_monitoring' => 0,
];
$filterOptions = [
    'audit_year' => [],
    'process_status' => [],
    'requesting_body' => [],
    'theme' => [],
    'classification' => [],
    'complexity' => [],
    'audit_phase' => [],
    'current_owner' => [],
    'item_status_group' => [],
];
$byBody = [];
$diligencePhase = [];
$byType = [];
$itemTotals = [];
$itemImplementation = [];
$itemCards = [];
$timelineEntries = [];
$itemKindOptionRows = [
    ['value' => 'RECOMENDACAO', 'label' => 'Recomendações'],
    ['value' => 'DETERMINACAO', 'label' => 'Determinações'],
    ['value' => 'CIENCIA', 'label' => 'Ciência'],
];

$buildUrl = function (array $overrides = [], string $anchor = 'audit-results') use ($filters, $timelineYear): string {
    $params = $filters;
    $params['timeline_year'] = $timelineYear;
    $params = array_merge($params, $overrides);
    $params = array_filter($params, static fn ($value) => $value !== '' && $value !== []);

    $query = http_build_query($params);
    $base = url('audits.php');
    return $base . ($query !== '' ? '?' . $query : '') . '#' . $anchor;
};

if ($moduleReady) {
    $audits = $repo->list($filters);
    $metrics = $repo->dashboardMetrics($filters);

    foreach (array_keys($filterOptions) as $field) {
        $options = $field === 'item_status_group'
            ? $repo->itemStatusOptions()
            : $repo->distinctValues($field);
        $filterOptions[$field] = $sortFilterOptions($field, $options);
    }

    $byBody = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['requesting_body' => [$row['label']]], $resultAnchor)],
        $repo->countsByBody($filters)
    );
    $diligencePhase = array_map(
        fn (array $row) => [
            'label' => $formatPhaseLegend((string) ($row['label'] ?? '')),
            'total' => $row['total'] ?? 0,
            'url' => $buildUrl(['audit_phase' => [(string) ($row['label'] ?? '')]], $resultAnchor),
        ],
        $repo->diligencePhaseOverview($filters)
    );
    $byType = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['audit_type' => [$row['label']]], $resultAnchor)],
        $repo->countsByType($filters)
    );
    $itemTotals = $repo->itemTotalsPerAudit($filters);
    $itemImplementation = array_map(
        fn (array $row) => $row + ['url' => $buildUrl(['item_status_group' => [$row['label']]], $rdcAnchor)],
        $repo->itemImplementationSummary($filters)
    );
    $itemCards = $repo->itemsByStatusGroup(null, 18, $filters);
    $timelineEntries = $repo->timelineEntries($timelineYear, $filters);
}

$totalDeterminacoes = 0;
$totalRecomendacoes = 0;
$totalCiencias = 0;
foreach ($itemTotals as $row) {
    $totalDeterminacoes += (int) ($row['determinacoes'] ?? 0);
    $totalRecomendacoes += (int) ($row['recomendacoes'] ?? 0);
    $totalCiencias += (int) ($row['ciencias'] ?? 0);
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
            O módulo de auditorias ainda não foi instalado neste banco. No phpMyAdmin, importe primeiro
            <strong>database/migrations/004_add_audits_module.sql</strong> e depois
            <strong>database/migrations/005_expand_audits_for_timeline.sql</strong>.
        </div>
    <?php endif; ?>

    <section class="hero-panel">
        <div>
            <p class="section-kicker">CGU e TCU</p>
            <h1>Painel de Auditorias</h1>
            <p class="text-secondary mb-0">Visão executiva, cadastro manual, acompanhamento de RDC e linha do tempo anual de prazos.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (can_edit_audits($user)): ?>
                <a class="btn btn-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_form.php') : '#' ?>"><i class="bi bi-plus-lg"></i> Nova auditoria</a>
                <a class="btn btn-outline-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_import.php') : '#' ?>"><i class="bi bi-cloud-upload"></i> Importar base tratada</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-clockwise"></i> Limpar filtros</a>
        </div>
    </section>

    <form class="filter-card" id="audit-filters" method="get" action="<?= url('audits.php') ?>">
        <input type="hidden" name="timeline_year" value="<?= (int) $timelineYear ?>">
        <div class="card-head audit-filter-head">
            <div>
                <h2>Filtro de Auditorias</h2>
                <span class="text-secondary">Você pode selecionar mais de uma opção no mesmo filtro.</span>
            </div>
        </div>

        <div class="row g-3 audit-filter-row">
            <div class="col-lg-4">
                <label class="form-label">Buscar auditoria</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Código, NUP, tema ou objetivo" <?= !$moduleReady ? 'disabled' : '' ?>>
            </div>
            <div class="col-lg-2">
                <?php $renderFilterBox('audit_year', 'Ano', $filterOptions['audit_year'], $filters['audit_year']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('process_status', 'Status do processo', $filterOptions['process_status'], $filters['process_status']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('requesting_body', 'Órgão', $filterOptions['requesting_body'], $filters['requesting_body']); ?>
            </div>

            <div class="col-lg-3">
                <?php $renderFilterBox('theme', 'Tema', $filterOptions['theme'], $filters['theme']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('classification', 'Classificação', $filterOptions['classification'], $filters['classification']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('complexity', 'Complexidade', $filterOptions['complexity'], $filters['complexity']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('audit_phase', 'Fase da Auditoria', $filterOptions['audit_phase'], $filters['audit_phase']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('current_owner', 'Responsável Atual', $filterOptions['current_owner'], $filters['current_owner']); ?>
            </div>

            <div class="col-lg-3">
                <?php $renderFilterBox('item_kind', 'RDC', $itemKindOptionRows, $filters['item_kind']); ?>
            </div>
            <div class="col-lg-3">
                <?php $renderFilterBox('item_status_group', 'Situação do RDC', $filterOptions['item_status_group'], $filters['item_status_group']); ?>
            </div>
            <div class="col-12 d-flex gap-2 align-items-end audit-filter-actions">
                <button class="btn btn-primary" type="submit" <?= !$moduleReady ? 'disabled' : '' ?>><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="audit-dashboard-overview">
        <div class="audit-overview-top">
            <article class="metric-card universe-core">
                <small class="section-kicker">Universo filtrado</small>
                <span>Número de Auditorias</span>
                <strong><?= (int) $metrics['total'] ?></strong>
                <p class="text-secondary mb-0">Esse card representa o universo atual do filtro aplicado em toda a página.</p>
                <i class="bi bi-bullseye"></i>
            </article>

            <article class="metric-card rdc-core">
                <small class="section-kicker">RDC gerados</small>
                <span>Quantidade de RDC</span>
                <strong><?= (int) $metrics['rdc_total'] ?></strong>
                <p class="text-secondary mb-0">As auditorias geraram <?= (int) $metrics['rdc_total'] ?> quantidades de RDC.</p>
                <i class="bi bi-diagram-3"></i>
            </article>
        </div>

        <div class="audit-overview-bottom">
            <div class="audit-overview-branch audit-overview-branch--universe">
                <article class="metric-card universe-branch warning">
                    <span>Diligência/Relatório</span>
                    <strong><?= (int) $metrics['diligence_report'] ?></strong>
                    <i class="bi bi-hourglass-top"></i>
                </article>
                <article class="metric-card universe-branch muted">
                    <span>Monitoramento A Iniciar</span>
                    <strong><?= (int) $metrics['monitoring_pending'] ?></strong>
                    <i class="bi bi-hourglass-split"></i>
                </article>
                <article class="metric-card universe-branch">
                    <span>1&ordm; Monitoramento</span>
                    <strong><?= (int) $metrics['first_monitoring'] ?></strong>
                    <i class="bi bi-1-circle"></i>
                </article>
                <article class="metric-card universe-branch">
                    <span>2&ordm; Monitoramento</span>
                    <strong><?= (int) $metrics['second_monitoring'] ?></strong>
                    <i class="bi bi-2-circle"></i>
                </article>
                <article class="metric-card universe-branch">
                    <span>3&ordm; Monitoramento</span>
                    <strong><?= (int) $metrics['third_monitoring'] ?></strong>
                    <i class="bi bi-3-circle"></i>
                </article>
                <article class="metric-card universe-branch">
                    <span>4&ordm; Monitoramento</span>
                    <strong><?= (int) $metrics['fourth_monitoring'] ?></strong>
                    <i class="bi bi-4-circle"></i>
                </article>
            </div>

            <div class="audit-overview-branch audit-overview-branch--rdc">
                <article class="metric-card rdc-branch">
                    <span>Recomendações</span>
                    <strong><?= $totalRecomendacoes ?></strong>
                    <i class="bi bi-journal-check"></i>
                </article>
                <article class="metric-card rdc-branch">
                    <span>Determinações</span>
                    <strong><?= $totalDeterminacoes ?></strong>
                    <i class="bi bi-list-check"></i>
                </article>
                <article class="metric-card rdc-branch">
                    <span>Ciência</span>
                    <strong><?= $totalCiencias ?></strong>
                    <i class="bi bi-info-circle"></i>
                </article>
            </div>
        </div>
    </section>

    <section class="app-card mb-4" id="<?= e($timelineAnchor) ?>">
        <div class="card-head">
            <div class="timeline-nav-shell">
                <a class="timeline-nav-arrow" href="<?= e($buildUrl(['timeline_year' => $timelineYear - 1], $timelineAnchor)) ?>" aria-label="Ano anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <div class="timeline-year-title">
                    <h2>Linha do Tempo <?= (int) $timelineYear ?></h2>
                    <span class="text-secondary">Passe o mouse para ver o ID e clique para abrir o resumo da auditoria.</span>
                </div>
                <a class="timeline-nav-arrow" href="<?= e($buildUrl(['timeline_year' => $timelineYear + 1], $timelineAnchor)) ?>" aria-label="Próximo ano">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
        <div class="timeline-board">
            <?php foreach ($timelineColumns as $offset => $label): $index = $offset + 1; ?>
                <?php
                $monthEntries = $timelineByMonth[$index];
                $deadlineCount = count(array_filter($monthEntries, static fn (array $entry): bool => !empty($entry['is_dgba'])));
                $estimatedCount = count($monthEntries) - $deadlineCount;
                ?>
                <div class="timeline-month <?= ((int) date('n') === $index && $timelineYear === (int) date('Y')) ? 'timeline-month-current' : '' ?>">
                    <div class="timeline-month-head"><?= e($label) ?></div>
                    <div class="timeline-month-body">
                        <?php foreach ($monthEntries as $entry): ?>
                            <?php
                            $complexityClass = match ((int) ($entry['complexity'] ?? 0)) {
                                1 => 'timeline-chip-complexity-low',
                                2 => 'timeline-chip-complexity-medium',
                                3 => 'timeline-chip-complexity-high',
                                default => 'timeline-chip-complexity-unknown',
                            };
                            $complexityLabel = match ((int) ($entry['complexity'] ?? 0)) {
                                1 => 'Complexidade baixa',
                                2 => 'Complexidade média',
                                3 => 'Complexidade alta',
                                default => 'Complexidade não informada',
                            };
                            ?>
                            <button
                                type="button"
                                class="timeline-chip <?= $entry['deadline_is_current'] ? 'timeline-chip-current' : '' ?> <?= !empty($entry['is_dgba']) ? 'timeline-chip-dgba' : '' ?>"
                                title="<?= e($entry['audit_code'] . ' • ' . $complexityLabel) ?>"
                                data-timeline-entry='<?= e(json_encode($entry, JSON_UNESCAPED_UNICODE)) ?>'
                            >
                                <span class="timeline-chip-title">
                                    <span><?= e($entry['audit_code']) ?></span>
                                    <span class="timeline-chip-complexity <?= e($complexityClass) ?>" aria-hidden="true"></span>
                                </span>
                                <small><?= e($entry['deadline_is_current'] ? 'Hoje' : $entry['deadline_label']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <?php if (!$monthEntries): ?>
                            <div class="timeline-empty">-</div>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-month-foot">
                        <span class="timeline-month-total timeline-month-total-current"><?= $estimatedCount ?> Estimados</span>
                        <span class="timeline-month-total"><?= $deadlineCount ?> com Prazo</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Auditorias por Órgão Solicitante</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($byBody, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Fase da Auditoria</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($diligencePhase, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-5">
            <div class="app-card h-100">
                <div class="card-head"><h2>Por Tipo de Auditoria</h2></div>
                <canvas class="chart-canvas bar" data-chart-mode="horizontal-bar" data-chart-legend="none" data-chart='<?= e(json_encode($byType, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="app-card h-100" id="<?= e($rdcAnchor) ?>">
                <div class="card-head"><h2>Situação dos RDC</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($itemImplementation, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="app-card h-100">
                <div class="card-head"><h2>Determinações, Recomendações e Ciência por Auditoria</h2></div>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead><tr><th>Auditoria</th><th>Órgão</th><th>Determ.</th><th>Recom.</th><th>Ciência</th><th>Total</th></tr></thead>
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
                    <h2>Pontos de Controle dos RDC</h2>
                    <span class="text-secondary"><?= $filters['item_status_group'] !== [] ? e(count($filters['item_status_group']) . ' situação(ões) selecionada(s)') : 'Todos os status' ?></span>
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
                        <div class="empty-state">Nenhum item encontrado para a situação selecionada.</div>
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
                <thead><tr><th>Código</th><th>Órgão</th><th>Tema</th><th>Fase</th><th>Prazo</th><th>Itens</th><th></th></tr></thead>
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
                                <?php if (can_edit_audits($user)): ?>
                                    <a class="btn btn-sm btn-light" href="<?= url('audit_form.php?id=' . (int) $audit['id']) ?>"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
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
                    <p class="section-kicker mb-1">Linha do tempo anual</p>
                    <h2 class="modal-title fs-4 mb-0" data-timeline-title>Auditoria</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="detail-grid timeline-detail-grid">
                    <dt>ID da auditoria</dt><dd data-timeline-id>-</dd>
                    <dt>NUP</dt><dd data-timeline-nup>-</dd>
                    <dt>Órgão de controle</dt><dd data-timeline-body>-</dd>
                    <dt>Tema</dt><dd data-timeline-theme>-</dd>
                    <dt>Fase da auditoria</dt><dd data-timeline-phase>-</dd>
                    <dt>Responsável atual</dt><dd data-timeline-owner>-</dd>
                    <dt>Próximo prazo</dt><dd data-timeline-deadline>-</dd>
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
