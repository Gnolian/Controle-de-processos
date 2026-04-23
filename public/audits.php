<?php

use App\Repositories\AuditRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_audit_access();
$repo = new AuditRepository();

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'requesting_body' => trim((string) ($_GET['requesting_body'] ?? '')),
    'audit_type' => trim((string) ($_GET['audit_type'] ?? '')),
    'audit_phase' => trim((string) ($_GET['audit_phase'] ?? '')),
    'diligence' => trim((string) ($_GET['diligence'] ?? '')),
    'item_kind' => trim((string) ($_GET['item_kind'] ?? '')),
];

$audits = $repo->list($filters);
$byBody = array_map(fn (array $row) => $row + ['url' => url('audits.php?requesting_body=' . urlencode($row['label']))], $repo->countsByBody());
$diligence = $repo->diligenceSummary();
$phases = array_map(fn (array $row) => $row + ['url' => url('audits.php?diligence=0&audit_phase=' . urlencode($row['label']))], $diligence['by_phase']);
$byType = array_map(fn (array $row) => $row + ['url' => url('audits.php?audit_type=' . urlencode($row['label']))], $repo->countsByType());
$itemTotals = $repo->itemTotalsPerAudit();

$pageTitle = 'Auditorias';
$activeNav = 'audits';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="hero-panel">
        <div>
            <p class="section-kicker">CGU e TCU</p>
            <h1>Painel de auditorias</h1>
            <p class="text-secondary mb-0">Controle interativo das auditorias, fases e determinações com acesso restrito.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="<?= url('audit_import.php') ?>"><i class="bi bi-cloud-upload"></i> Importar base</a>
            <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-clockwise"></i> Limpar filtros</a>
        </div>
    </section>

    <form class="filter-card" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Buscar auditoria</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Codigo, NUP, tema ou objetivo">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Órgão</label>
                <input class="form-control" name="requesting_body" value="<?= e($filters['requesting_body']) ?>">
            </div>
            <div class="col-lg-3">
                <label class="form-label">Tipo</label>
                <input class="form-control" name="audit_type" value="<?= e($filters['audit_type']) ?>">
            </div>
            <div class="col-lg-3">
                <label class="form-label">Fase</label>
                <input class="form-control" name="audit_phase" value="<?= e($filters['audit_phase']) ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Diligência</label>
                <select class="form-select" name="diligence">
                    <option value="">Todas</option>
                    <option value="1" <?= selected($filters['diligence'], '1') ?>>Em diligência</option>
                    <option value="0" <?= selected($filters['diligence'], '0') ?>>Sem diligência</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Tipo de item</label>
                <select class="form-select" name="item_kind">
                    <option value="">Todos</option>
                    <option value="DETERMINAÇÃO" <?= selected($filters['item_kind'], 'DETERMINAÇÃO') ?>>Determinação</option>
                    <option value="RECOMENDAÇÃO" <?= selected($filters['item_kind'], 'RECOMENDAÇÃO') ?>>Recomendação</option>
                    <option value="CIÊNCIA" <?= selected($filters['item_kind'], 'CIÊNCIA') ?>>Ciência</option>
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Auditorias por órgão solicitante</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($byBody, JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Diligências e fases</h2></div>
                <a class="metric-inline d-block mb-3" href="<?= url('audits.php?diligence=1') ?>">
                    <span>Auditorias em diligência</span>
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
                <div class="card-head"><h2>Determinações, recomendações e ciência por auditoria</h2></div>
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
                <thead><tr><th>Código</th><th>Órgão</th><th>Tipo</th><th>Fase</th><th>Diligência</th><th>Itens</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($audits as $audit): ?>
                        <tr class="clickable-row" data-href="<?= url('audit_detail.php?id=' . (int) $audit['id']) ?>">
                            <td><strong><?= e($audit['audit_code']) ?></strong><small><?= e($audit['audit_nup']) ?></small></td>
                            <td><?= e($audit['requesting_body']) ?></td>
                            <td><?= e($audit['audit_type']) ?></td>
                            <td><?= e($audit['audit_phase'] ?: '-') ?></td>
                            <td><?= $audit['has_diligence'] ? '<span class="badge text-bg-warning">Em diligência</span>' : '<span class="badge text-bg-secondary">Nao</span>' ?></td>
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

