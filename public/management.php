<?php

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
if (!can_manage($user)) {
    flash('Acesso restrito a coordenacao.', 'danger');
    redirect('dashboard.php');
}

$repo = new ProcessRepository();
$metrics = $repo->metrics($user);
$pageTitle = 'Gerencial';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="shell">
    <section class="page-heading">
        <div>
            <p class="eyebrow">Visao gerencial</p>
            <h1>Indicadores</h1>
        </div>
        <a class="button ghost" href="<?= url('export.php') ?>">Exportar base completa</a>
    </section>

    <section class="metric-grid">
        <article class="metric"><span>Total</span><strong><?= $metrics['total'] ?></strong></article>
        <article class="metric"><span>Abertos</span><strong><?= $metrics['open'] ?></strong></article>
        <article class="metric urgent"><span>Atrasados</span><strong><?= $metrics['late'] ?></strong></article>
        <article class="metric attention"><span>Vencem em 7 dias</span><strong><?= $metrics['due_soon'] ?></strong></article>
    </section>

    <section class="insight-grid">
        <?php foreach (['by_status' => 'Por status', 'by_response_owner' => 'Por responsavel pela resposta', 'by_creator' => 'Por usuario'] as $key => $title): ?>
            <article class="panel">
                <h2><?= e($title) ?></h2>
                <?php foreach ($metrics[$key] as $row): ?>
                    <?php $percent = $metrics['total'] > 0 ? round(((int) $row['total'] / $metrics['total']) * 100) : 0; ?>
                    <div class="bar-row">
                        <div>
                            <strong><?= e($row['label']) ?></strong>
                            <span><?= (int) $row['total'] ?> processo(s)</span>
                        </div>
                        <meter min="0" max="100" value="<?= $percent ?>"></meter>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>

