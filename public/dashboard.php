<?php

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$repo = new ProcessRepository();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'owner' => trim((string) ($_GET['owner'] ?? '')),
];
$processes = $repo->search($filters, $user);
$metrics = $repo->metrics($user);
$pageTitle = 'Painel';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-heading">
        <div>
            <p class="eyebrow">Painel de acompanhamento</p>
            <h1>Processos</h1>
        </div>
        <a class="button primary" href="<?= url('process_form.php') ?>">Novo processo</a>
    </section>

    <section class="metric-grid">
        <article class="metric"><span>Total</span><strong><?= $metrics['total'] ?></strong></article>
        <article class="metric"><span>Abertos</span><strong><?= $metrics['open'] ?></strong></article>
        <article class="metric urgent"><span>Atrasados</span><strong><?= $metrics['late'] ?></strong></article>
        <article class="metric attention"><span>Vencem em 7 dias</span><strong><?= $metrics['due_soon'] ?></strong></article>
    </section>

    <form class="filters" method="get">
        <label>
            Buscar
            <input name="q" value="<?= e($filters['q']) ?>" placeholder="Numero, descricao, orgao, bloco">
        </label>
        <label>
            Status
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (config('dropdowns.status') as $status): ?>
                    <option value="<?= e($status) ?>" <?= selected($filters['status'], $status) ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Responsavel
            <input name="owner" value="<?= e($filters['owner']) ?>" placeholder="Nome">
        </label>
        <button class="button" type="submit">Filtrar</button>
        <a class="button ghost" href="<?= url('dashboard.php') ?>">Limpar</a>
        <a class="button ghost" href="<?= url('export.php?' . http_build_query($filters)) ?>">Exportar CSV</a>
    </form>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Processo</th>
                    <th>Responsavel</th>
                    <th>Descricao</th>
                    <th>Prazo</th>
                    <th>Resposta</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processes as $process): ?>
                    <?php
                        $deadline = $process['external_deadline_mds'] ?: ($process['adjusted_internal_deadline'] ?: $process['internal_deadline_gab']);
                        $isLate = $deadline && $process['status'] === 'Aberto' && $deadline < date('Y-m-d');
                    ?>
                    <tr>
                        <td><strong><?= e($process['process_number']) ?></strong><small><?= e($process['requesting_agency']) ?></small></td>
                        <td><?= e($process['response_owner']) ?></td>
                        <td><?= e($process['general_description']) ?></td>
                        <td><span class="<?= $isLate ? 'tag late' : 'tag' ?>"><?= e($deadline ?: 'Sem prazo') ?></span></td>
                        <td><?= e($process['response_status']) ?></td>
                        <td><span class="status-pill"><?= e($process['status']) ?></span></td>
                        <td class="actions">
                            <a href="<?= url('process_form.php?id=' . (int) $process['id']) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$processes): ?>
                    <tr><td colspan="7" class="empty">Nenhum processo encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>

