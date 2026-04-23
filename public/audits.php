<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_audit_access();
$moduleReady = audits_module_ready();
$repo = new AuditRepository();

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'requesting_body' => trim((string) ($_GET['requesting_body'] ?? '')),
    'audit_type' => trim((string) ($_GET['audit_type'] ?? '')),
    'audit_phase' => trim((string) ($_GET['audit_phase'] ?? '')),
    'diligence' => trim((string) ($_GET['diligence'] ?? '')),
    'item_kind' => trim((string) ($_GET['item_kind'] ?? '')),
];

$audits = [];
$byBody = [];
$diligence = ['in_diligence' => 0, 'by_phase' => []];
$phases = [];
$byType = [];
$itemTotals = [];

if ($moduleReady) {
    $audits = $repo->list($filters);
    $byBody = array_map(fn (array $row) => $row + ['url' => url('audits.php?requesting_body=' . urlencode($row['label']))], $repo->countsByBody());
    $diligence = $repo->diligenceSummary();
    $phases = array_map(fn (array $row) => $row + ['url' => url('audits.php?diligence=0&audit_phase=' . urlencode($row['label']))], $diligence['by_phase']);
    $byType = array_map(fn (array $row) => $row + ['url' => url('audits.php?audit_type=' . urlencode($row['label']))], $repo->countsByType());
    $itemTotals = $repo->itemTotalsPerAudit();
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
            <strong>database/migrations/004_add_audits_module.sql</strong>
        </div>
    <?php endif; ?>

    <section class="hero-panel">
        <div>
            <p class="section-kicker">CGU e TCU</p>
            <h1>Painel de auditorias</h1>
            <p class="text-secondary mb-0">Controle interativo das auditorias, fases e itens com acesso restrito.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary <?= !$moduleReady ? 'disabled' : '' ?>" href="<?= $moduleReady ? url('audit_import.php') : '#' ?>"><i class="bi bi-cloud-upload"></i> Importar base tratada</a>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-clockwise"></i> Limpar filtros</a>
        </div>
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
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit" <?= !$moduleReady ? 'disabled' : '' ?>><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Auditorias por orgao solicitante</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($byBody, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Diligencias e fases</h2></div>
                <a class="metric-inline d-block mb-3" href="<?= url('audits.php?diligence=1') ?>">
                    <span>Auditorias em diligencia</span>
                    <strong><?= $diligence['in_diligence'] ?></strong>
                </a>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($phases, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-5">
            <div class="app-card h-100">
                <div class="card-head"><h2>Por tipo de auditoria</h2></div>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($byType, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="app-card mt-4 p-0 overflow-hidden">
        <div class="card-head p-4 pb-0"><h2>Auditorias</h2><span class="text-secondary"><?= count($audits) ?> resultado(s)</span></div>
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Codigo</th><th>Orgao</th><th>Tipo</th><th>Fase</th><th>Diligencia</th><th>Itens</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($audits as $audit): ?>
                        <tr class="clickable-row" data-href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>">
                            <td><strong><?= e($audit['audit_code']) ?></strong><small><?= e($audit['audit_nup']) ?></small></td>
                            <td><?= e($audit['requesting_body']) ?></td>
                            <td><?= e($audit['audit_type']) ?></td>
                            <td><?= e($audit['audit_phase'] ?: '-') ?></td>
                            <td><?= $audit['has_diligence'] ? '<span class="badge text-bg-warning">Em diligencia</span>' : '<span class="badge text-bg-secondary">Nao</span>' ?></td>
                            <td><?= (int) $audit['total_items'] ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>"><i class="bi bi-eye"></i></a></td>
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

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
