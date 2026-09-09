<?php

use App\Repositories\InssMonitoringRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_inss_monitoring_access();
$repository = new InssMonitoringRepository();
$page = max(1, (int) ($_GET['page'] ?? 1));
$highlightId = max(0, (int) ($_GET['highlight'] ?? 0));
$filters = ['q' => trim((string) ($_GET['q'] ?? ''))];
foreach (InssMonitoringRepository::FILTER_COLUMNS as $field) {
    $filters[$field] = trim((string) ($_GET[$field] ?? ''));
}

$result = $repository->search($filters, $page, 20);
$metrics = $repository->dashboardMetrics($filters);
$deadlineChart = $repository->groupCounts('deadline_status', $filters);
$statusChart = $repository->groupCounts('status', $filters);

$fixedOptions = [
    'deadline_status' => ['Sem prazo', 'No prazo', 'Atenção', 'Prazo vencido', 'Respondido'],
    'status' => ['Aguardando resposta', 'Resposta recebida', 'Em análise', 'Em cobrança', 'Concluído'],
    'conclusive_response' => ['A avaliar', 'Sim', 'Parcialmente', 'Não'],
    'needs_follow_up' => ['A avaliar', 'Sim', 'Não'],
    'priority' => ['Alta', 'Média', 'Baixa'],
    'owner' => $repository->ownerOptions(),
];

$filterOptions = [];
foreach (InssMonitoringRepository::FILTER_COLUMNS as $field) {
    $values = array_values(array_unique(array_merge($fixedOptions[$field] ?? [], $repository->distinctValues($field))));
    if (in_array($field, ['inss_response_date', 'follow_up_date'], true)) {
        rsort($values);
    }
    $filterOptions[$field] = $values;
}

$renderFilter = static function (string $name, string $label, array $options, string $current): void {
    $dateField = in_array($name, ['inss_response_date', 'follow_up_date'], true);
    ?>
    <div class="inss-filter-field">
        <label class="form-label" for="filter-<?= e($name) ?>"><?= e($label) ?></label>
        <select class="form-select" id="filter-<?= e($name) ?>" name="<?= e($name) ?>">
            <option value="">Todos</option>
            <option value="__empty__" <?= selected($current, '__empty__') ?>>Não informado</option>
            <?php foreach ($options as $option): ?>
                <option value="<?= e($option) ?>" <?= selected($current, $option) ?>><?= e($dateField ? format_date($option) : $option) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};

$buildUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters, static fn (string $value): bool => $value !== '');
    $params['page'] = $targetPage;
    return url('inss_monitoring.php?' . http_build_query($params) . '#monitoring-results');
};

$badgeClass = static function (?string $value, string $type): string {
    $normalized = mb_strtoupper(trim((string) $value), 'UTF-8');
    if ($normalized === '') {
        return 'muted';
    }
    if ($type === 'priority') {
        return str_starts_with($normalized, 'ALTA') ? 'danger' : (str_starts_with($normalized, 'MÉDIA') || str_starts_with($normalized, 'MEDIA') ? 'warning' : (str_starts_with($normalized, 'BAIXA') ? 'success' : 'muted'));
    }
    if ($type === 'deadline') {
        return str_contains($normalized, 'VENC') || str_contains($normalized, 'ATRAS') ? 'danger' : (str_contains($normalized, 'ATEN') ? 'warning' : (str_contains($normalized, 'RESP') ? 'info' : (str_contains($normalized, 'PRAZO') ? 'success' : 'muted')));
    }
    if ($type === 'yesno') {
        return str_starts_with($normalized, 'SIM') ? 'danger' : (str_starts_with($normalized, 'NÃO') || str_starts_with($normalized, 'NAO') ? 'success' : 'muted');
    }
    return str_contains($normalized, 'CONCLU') ? 'success' : (str_contains($normalized, 'COBRAN') ? 'warning' : (str_contains($normalized, 'RESP') ? 'info' : 'muted'));
};

$safeLink = static function (?string $link): ?string {
    $link = trim((string) $link);
    if ($link === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $link)) {
        $link = 'https://' . ltrim($link, '/');
    }
    return filter_var($link, FILTER_VALIDATE_URL) !== false ? $link : null;
};

$pageTitle = 'Monitoramento INSS';
$activeNav = 'inss';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell inss-monitoring-page">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="hero-panel inss-hero-panel">
        <div>
            <p class="section-kicker">Respostas a ofícios</p>
            <h1>Monitoramento INSS</h1>
            <p class="text-secondary mb-0">Acompanhe respostas sobre apuração de irregularidades e solicitações relacionadas aos beneficiários.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="<?= url('inss_monitoring_form.php') ?>"><i class="bi bi-plus-lg"></i> Nova demanda</a>
            <a class="btn btn-outline-primary" href="<?= url('inss_monitoring_import.php') ?>"><i class="bi bi-cloud-upload"></i> Importar planilha</a>
        </div>
    </section>

    <form class="filter-card inss-filter-card" method="get" action="<?= url('inss_monitoring.php') ?>">
        <div class="card-head">
            <div>
                <h2>Filtros de acompanhamento</h2>
                <span class="text-secondary">Refine o painel e a lista de demandas.</span>
            </div>
        </div>
        <div class="inss-filters-grid inss-filters-grid--primary">
            <div class="inss-filter-field inss-filter-search">
                <label class="form-label" for="inss-search">Busca geral</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" id="inss-search" name="q" value="<?= e($filters['q']) ?>" placeholder="Processo, ofício, beneficiário, CPF, NB ou assunto">
                </div>
            </div>
            <?php $renderFilter('deadline_status', 'Situação do prazo', $filterOptions['deadline_status'], $filters['deadline_status']); ?>
            <?php $renderFilter('status', 'Status', $filterOptions['status'], $filters['status']); ?>
            <?php $renderFilter('conclusive_response', 'Resposta conclusiva?', $filterOptions['conclusive_response'], $filters['conclusive_response']); ?>
            <?php $renderFilter('needs_follow_up', 'Necessita nova cobrança?', $filterOptions['needs_follow_up'], $filters['needs_follow_up']); ?>
            <?php $renderFilter('owner', 'Responsável', $filterOptions['owner'], $filters['owner']); ?>
            <?php $renderFilter('priority', 'Prioridade', $filterOptions['priority'], $filters['priority']); ?>
        </div>
        <details class="inss-more-filters">
            <summary><i class="bi bi-sliders"></i> Mais filtros</summary>
            <div class="inss-filters-grid mt-3">
                <?php $renderFilter('inss_response_date', 'Data da resposta INSS', $filterOptions['inss_response_date'], $filters['inss_response_date']); ?>
                <?php $renderFilter('response_office_number', 'Nº Ofício/Resposta INSS', $filterOptions['response_office_number'], $filters['response_office_number']); ?>
                <?php $renderFilter('response_sei', 'SEI da resposta', $filterOptions['response_sei'], $filters['response_sei']); ?>
                <?php $renderFilter('follow_up_date', 'Data da cobrança', $filterOptions['follow_up_date'], $filters['follow_up_date']); ?>
                <?php $renderFilter('follow_up_count', 'Quantidade de cobranças', $filterOptions['follow_up_count'], $filters['follow_up_count']); ?>
            </div>
        </details>
        <div class="form-actions inss-filter-actions">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Aplicar filtros</button>
            <a class="btn btn-outline-secondary" href="<?= url('inss_monitoring.php') ?>">Limpar</a>
        </div>
    </form>

    <section class="inss-metrics-grid" aria-label="Indicadores do monitoramento">
        <article class="inss-metric inss-metric--total"><span>Total monitorado</span><strong><?= $metrics['total'] ?></strong><i class="bi bi-inboxes"></i></article>
        <article class="inss-metric"><span>Aguardando resposta</span><strong><?= $metrics['awaiting_response'] ?></strong><i class="bi bi-hourglass-split"></i></article>
        <article class="inss-metric inss-metric--info"><span>Respostas recebidas</span><strong><?= $metrics['with_response'] ?></strong><i class="bi bi-envelope-check"></i></article>
        <article class="inss-metric inss-metric--danger"><span>Prazos vencidos</span><strong><?= $metrics['overdue'] ?></strong><i class="bi bi-exclamation-triangle"></i></article>
        <article class="inss-metric inss-metric--warning"><span>Nova cobrança</span><strong><?= $metrics['needs_follow_up'] ?></strong><i class="bi bi-arrow-repeat"></i></article>
        <article class="inss-metric inss-metric--priority"><span>Prioridade alta</span><strong><?= $metrics['high_priority'] ?></strong><i class="bi bi-flag"></i></article>
    </section>

    <section class="inss-chart-grid">
        <article class="app-card chart-card">
            <div class="card-head"><h2>Situação dos prazos</h2></div>
            <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($deadlineChart, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
        </article>
        <article class="app-card chart-card">
            <div class="card-head"><h2>Status do acompanhamento</h2></div>
            <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($statusChart, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
        </article>
    </section>

    <section class="app-card inss-results-panel" id="monitoring-results">
        <div class="card-head inss-results-head">
            <div>
                <p class="section-kicker mb-1">Demandas monitoradas</p>
                <h2><?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? 'registro encontrado' : 'registros encontrados' ?></h2>
            </div>
            <span class="text-secondary">Ordenação por prioridade, prazo e tempo decorrido</span>
        </div>

        <?php if (!$result['items']): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Nenhuma demanda encontrada</h3>
                <p>Revise os filtros ou adicione uma nova demanda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table modern-table inss-monitoring-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Processo e ofício</th>
                            <th>Demanda</th>
                            <th>Envio</th>
                            <th>Prazo</th>
                            <th>Status</th>
                            <th>Resposta</th>
                            <th>Cobrança</th>
                            <th>Responsável</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['items'] as $item): ?>
                            <?php $seiLink = $safeLink($item['sei_link']); ?>
                            <tr id="monitoring-<?= (int) $item['id'] ?>" class="<?= $highlightId === (int) $item['id'] ? 'inss-row-highlight' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <strong><?= e((string) $item['sei_process']) ?></strong>
                                        <?php if ($seiLink): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($seiLink) ?>" target="_blank" rel="noopener noreferrer" title="Abrir processo no SEI" aria-label="Abrir processo no SEI"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                                    </div>
                                    <small>Ofício <?= e((string) ($item['sent_office_number'] ?: '-')) ?></small>
                                    <?php if ($item['sent_office_sei']): ?><small>SEI <?= e((string) $item['sent_office_sei']) ?></small><?php endif; ?>
                                </td>
                                <td class="inss-demand-cell">
                                    <strong><?= e((string) ($item['subject'] ?: $item['demand_type'] ?: 'Sem assunto')) ?></strong>
                                    <?php if ($item['beneficiary']): ?><small><?= e((string) $item['beneficiary']) ?></small><?php endif; ?>
                                    <details class="inss-row-details">
                                        <summary>Ver detalhes</summary>
                                        <dl>
                                            <div><dt>Tipo</dt><dd><?= e((string) ($item['demand_type'] ?: '-')) ?></dd></div>
                                            <div><dt>Origem</dt><dd><?= e((string) ($item['demand_origin'] ?: '-')) ?></dd></div>
                                            <div><dt>CPF</dt><dd><?= e((string) ($item['cpf'] ?: '-')) ?></dd></div>
                                            <div><dt>NB</dt><dd><?= e((string) ($item['benefit_number'] ?: '-')) ?></dd></div>
                                            <div><dt>Unidade INSS</dt><dd><?= e((string) ($item['inss_recipient_unit'] ?: '-')) ?></dd></div>
                                            <div><dt>Observação</dt><dd><?= e((string) ($item['notes'] ?: '-')) ?></dd></div>
                                        </dl>
                                    </details>
                                </td>
                                <td><span><?= e(format_date($item['sent_to_inss_date'])) ?></span><small><?= $item['elapsed_days'] !== null ? (int) $item['elapsed_days'] . ' dias' : 'Data não informada' ?></small></td>
                                <td><span class="inss-status-badge inss-status-badge--<?= e($badgeClass($item['deadline_status'], 'deadline')) ?>"><?= e((string) ($item['deadline_status'] ?: 'Não informado')) ?></span></td>
                                <td><span class="inss-status-badge inss-status-badge--<?= e($badgeClass($item['status'], 'status')) ?>"><?= e((string) ($item['status'] ?: 'Não informado')) ?></span></td>
                                <td>
                                    <span><?= e(format_date($item['inss_response_date'])) ?></span>
                                    <small><?= e((string) ($item['conclusive_response'] ?: 'Conclusão não avaliada')) ?></small>
                                    <?php if ($item['response_office_number']): ?><small>Ofício <?= e((string) $item['response_office_number']) ?></small><?php endif; ?>
                                    <?php if ($item['response_sei']): ?><small>SEI <?= e((string) $item['response_sei']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <span class="inss-status-badge inss-status-badge--<?= e($badgeClass($item['needs_follow_up'], 'yesno')) ?>"><?= e((string) ($item['needs_follow_up'] ?: 'Não informado')) ?></span>
                                    <small><?= (int) ($item['follow_up_count'] ?? 0) ?> cobrança(s)</small>
                                    <?php if ($item['follow_up_date']): ?><small>Em <?= e(format_date($item['follow_up_date'])) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <span><?= e((string) ($item['owner'] ?: 'Não atribuído')) ?></span>
                                    <span class="inss-priority inss-priority--<?= e($badgeClass($item['priority'], 'priority')) ?>"><i class="bi bi-flag-fill"></i><?= e((string) ($item['priority'] ?: 'Sem prioridade')) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="inss-row-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('inss_monitoring_form.php?id=' . (int) $item['id']) ?>"><i class="bi bi-pencil-square"></i> Editar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $result['pages'] > 1): ?>
                <nav class="study-pagination" aria-label="Paginação do monitoramento INSS">
                    <?php if ((int) $result['page'] > 1): ?><a class="page-link-mini" href="<?= e($buildUrl((int) $result['page'] - 1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                    <?php for ($i = 1; $i <= (int) $result['pages']; $i++): ?><a class="page-link-mini <?= $i === (int) $result['page'] ? 'active' : '' ?>" href="<?= e($buildUrl($i)) ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ((int) $result['page'] < (int) $result['pages']): ?><a class="page-link-mini" href="<?= e($buildUrl((int) $result['page'] + 1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
