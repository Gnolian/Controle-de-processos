<?php

use App\Repositories\ProcessRepository;
use App\Services\DashboardService;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_login();
$pageTitle = 'Painel pessoal';
$activeNav = 'dashboard';
$dashboard = new DashboardService();
$metrics = $dashboard->metrics($user);
$queue = $dashboard->personalQueue($user);
$filters = ['q' => '', 'status' => 'Aberto', 'owner' => '', 'deadline' => ''];
$processes = (new ProcessRepository())->search($filters, $user, 8, 0);

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="hero-panel">
        <div>
            <p class="section-kicker">Bem-vindo, <?= e($user['name']) ?></p>
            <h1>Seu painel de processos</h1>
            <p class="text-secondary mb-0">Acompanhe prazos, gargalos e pendencias sem voltar para a planilha.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-light" href="<?= url('processes.php') ?>"><i class="bi bi-search"></i> Buscar processos</a>
            <a class="btn btn-primary" href="<?= url('process_form.php') ?>"><i class="bi bi-plus-lg"></i> Novo processo</a>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card"><span>Total</span><strong><?= $metrics['total'] ?></strong><i class="bi bi-collection"></i></article>
        <article class="metric-card"><span>Abertos</span><strong><?= $metrics['open'] ?></strong><i class="bi bi-folder2-open"></i></article>
        <article class="metric-card danger"><span>Atrasados</span><strong><?= $metrics['late'] ?></strong><i class="bi bi-exclamation-triangle"></i></article>
        <article class="metric-card warning"><span>Vencem em 7 dias</span><strong><?= $metrics['due_soon'] ?></strong><i class="bi bi-hourglass-split"></i></article>
    </section>

    <section class="row g-4">
        <div class="col-lg-7">
            <div class="app-card h-100">
                <div class="card-head">
                    <div>
                        <p class="section-kicker">Fila de trabalho</p>
                        <h2>Processos abertos prioritarios</h2>
                    </div>
                    <a href="<?= url('processes.php?status=Aberto') ?>">Ver todos</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($processes as $process): ?>
                        <a class="list-group-item process-row" href="<?= url('process_detail.php?id=' . (int) $process['id']) ?>">
                            <div>
                                <strong><?= e($process['process_number']) ?></strong>
                                <small><?= e($process['general_description']) ?></small>
                            </div>
                            <?= deadline_badge($process) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$processes): ?>
                        <div class="empty-state">Nenhum processo aberto para exibir.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="app-card h-100">
                <div class="card-head">
                    <div>
                        <p class="section-kicker">Distribuicao</p>
                        <h2>Status dos processos</h2>
                    </div>
                </div>
                <canvas class="chart-canvas" data-chart='<?= e(json_encode($metrics['by_status'], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

