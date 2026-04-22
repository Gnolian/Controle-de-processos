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
        <article class="metric-card warning"><span>Vencem em 7 dias</span><strong><?= $metrics['due_soon'] ?></strong><i class="bi bi-hourglass-split"></i></article>
        <article class="metric-card warning"><span>Revisao pendente</span><strong><?= $metrics['review_pending'] ?></strong><i class="bi bi-eye"></i></article>
        <article class="metric-card warning"><span>Aguardando GAB</span><strong><?= $metrics['gab_pending'] ?></strong><i class="bi bi-send"></i></article>
    </section>

    <section class="row g-4">
        <?php foreach (['by_status' => 'Por status', 'by_response_owner' => 'Ranking de responsaveis', 'by_agency' => 'Por orgao solicitante'] as $key => $title): ?>
            <div class="col-lg-4">
                <div class="app-card h-100">
                    <div class="card-head"><h2><?= e($title) ?></h2></div>
                    <?php foreach ($metrics[$key] as $row): ?>
                        <?php $percent = $metrics['total'] > 0 ? round(((int) $row['total'] / $metrics['total']) * 100) : 0; ?>
                        <div class="progress-row">
                            <div><strong><?= e($row['label']) ?></strong><span><?= (int) $row['total'] ?></span></div>
                            <div class="progress"><div class="progress-bar" style="width: <?= $percent ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Status</h2></div>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($metrics['by_status'], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card">
                <div class="card-head"><h2>Responsaveis com maior volume</h2></div>
                <canvas class="chart-canvas bar" data-chart='<?= e(json_encode($metrics['by_response_owner'], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

