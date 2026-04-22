<?php

use App\Services\DashboardService;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_role(['admin', 'coordenador']);
$pageTitle = 'Dashboard gerencial';
$activeNav = 'management';
$metrics = (new DashboardService())->metrics($user);

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <section class="hero-panel management">
        <div>
            <p class="section-kicker">Visao da coordenacao</p>
            <h1>Panorama gerencial</h1>
            <p class="text-secondary mb-0">Indicadores para priorizar prazos, responsaveis e gargalos de resposta.</p>
        </div>
        <a class="btn btn-light" href="<?= url('export.php') ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar Excel/CSV</a>
    </section>

    <section class="metric-grid xl">
        <article class="metric-card"><span>Total</span><strong><?= $metrics['total'] ?></strong><i class="bi bi-collection"></i></article>
        <article class="metric-card"><span>Abertos</span><strong><?= $metrics['open'] ?></strong><i class="bi bi-folder2-open"></i></article>
        <article class="metric-card success"><span>Concluidos</span><strong><?= $metrics['done'] ?></strong><i class="bi bi-check-circle"></i></article>
        <article class="metric-card muted"><span>Arquivados</span><strong><?= $metrics['archived'] ?></strong><i class="bi bi-archive"></i></article>
        <article class="metric-card danger"><span>Atrasados</span><strong><?= $metrics['late'] ?></strong><i class="bi bi-exclamation-triangle"></i></article>
        <article class="metric-card warning"><span>Vencem amanha</span><strong><?= $metrics['due_tomorrow'] ?></strong><i class="bi bi-calendar2-day"></i></article>
        <article class="metric-card warning"><span>Vencem em 3 dias</span><strong><?= $metrics['due_soon'] ?></strong><i class="bi bi-hourglass-split"></i></article>
        <article class="metric-card warning"><span>Revisao pendente</span><strong><?= $metrics['review_pending'] ?></strong><i class="bi bi-eye"></i></article>
    </section>

    <section class="row g-4">
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Responsaveis com processos abertos</h2></div>
                <?php foreach ($metrics['open_by_owner'] as $row): ?>
                    <?php $percent = $metrics['open'] > 0 ? round(((int) $row['total'] / $metrics['open']) * 100) : 0; ?>
                    <div class="progress-row">
                        <div><strong><?= e($row['label']) ?></strong><span><?= (int) $row['total'] ?> aberto(s)</span></div>
                        <div class="progress"><div class="progress-bar" style="width: <?= $percent ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card h-100">
                <div class="card-head"><h2>Quantidade em revisao</h2></div>
                <?php foreach ($metrics['review_by_owner'] as $row): ?>
                    <?php $percent = $metrics['review_pending'] > 0 ? round(((int) $row['total'] / $metrics['review_pending']) * 100) : 0; ?>
                    <div class="progress-row">
                        <div><strong><?= e($row['label']) ?></strong><span><?= (int) $row['total'] ?> em revisao</span></div>
                        <div class="progress"><div class="progress-bar" style="width: <?= $percent ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Responsaveis com processos abertos</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($metrics['open_by_owner'], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Fluxo aberto por responsavel</h2></div>
                <div class="table-responsive">
                    <table class="table modern-table">
                        <thead><tr><th>Responsavel</th><th>A iniciar</th><th>Em andamento</th><th>Concluido</th><th>Revisao</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($metrics['open_flow_by_owner'] as $row): ?>
                                <tr>
                                    <td><?= e($row['owner']) ?></td>
                                    <td><?= (int) $row['a_iniciar'] ?></td>
                                    <td><?= (int) $row['em_andamento'] ?></td>
                                    <td><?= (int) $row['concluido'] ?></td>
                                    <td><?= (int) $row['revisao_pendente'] ?></td>
                                    <td><strong><?= (int) $row['total'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
