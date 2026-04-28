<?php

use App\Repositories\ProcessRepository;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_login();
$pageTitle = 'Lista de processos';
$activeNav = 'processes';
$repo = new ProcessRepository();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'owner' => trim((string) ($_GET['owner'] ?? '')),
    'requesting_agency' => trim((string) ($_GET['requesting_agency'] ?? '')),
    'response_status' => trim((string) ($_GET['response_status'] ?? '')),
    'andrea_review_status' => trim((string) ($_GET['andrea_review_status'] ?? '')),
    'deadline' => trim((string) ($_GET['deadline'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$total = $repo->count($filters, $user);
$pages = max(1, (int) ceil($total / $limit));
$processes = $repo->search($filters, $user, $limit, ($page - 1) * $limit);

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Busca e controle</p>
            <h1>Processos</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?= url('export.php?' . http_build_query($filters)) ?>"><i class="bi bi-download"></i> CSV</a>
            <?php if (can_manage($user)): ?>
                <a class="btn btn-outline-primary" href="<?= url('import.php') ?>"><i class="bi bi-cloud-upload"></i> Importar</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= url('process_form.php') ?>"><i class="bi bi-plus-lg"></i> Novo</a>
        </div>
    </section>

    <form class="filter-card" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Busca global</label>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Número, descrição, órgão, bloco ou anotação">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Todos</option>
                    <?php foreach (config('dropdowns.status') as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['status'], $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Resposta</label>
                <select class="form-select" name="response_status">
                    <option value="">Todas</option>
                    <?php foreach (config('dropdowns.workflow') as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['response_status'], $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Revisão</label>
                <select class="form-select" name="andrea_review_status">
                    <option value="">Todas</option>
                    <?php foreach (config('dropdowns.workflow') as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['andrea_review_status'], $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Prazo</label>
                <select class="form-select" name="deadline">
                    <option value="">Todos</option>
                    <option value="late" <?= selected($filters['deadline'], 'late') ?>>Atrasados</option>
                    <option value="tomorrow" <?= selected($filters['deadline'], 'tomorrow') ?>>Amanhã</option>
                    <option value="three_days" <?= selected($filters['deadline'], 'three_days') ?>>3 dias</option>
                    <option value="tempo_habil" <?= selected($filters['deadline'], 'tempo_habil') ?>>Tempo Hábil</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Responsável</label>
                <input class="form-control" name="owner" value="<?= e($filters['owner']) ?>">
            </div>
            <div class="col-lg-3">
                <label class="form-label">Órgão solicitante</label>
                <input class="form-control" name="requesting_agency" value="<?= e($filters['requesting_agency']) ?>">
            </div>
            <div class="col-lg-6 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('processes.php') ?>">Limpar</a>
            </div>
        </div>
    </form>

    <section class="app-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 modern-table">
                <thead>
                    <tr>
                        <th>Processo</th>
                        <th>Responsável</th>
                        <th>Resumo</th>
                        <th>Prazo</th>
                        <th>Fluxo</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processes as $process): ?>
                        <tr>
                            <td><strong><?= e($process['process_number']) ?></strong><small><?= e($process['requesting_agency']) ?></small></td>
                            <td><?= e($process['response_owner'] ?: '-') ?></td>
                            <td class="text-truncate-cell"><?= e($process['general_description']) ?></td>
                            <td><?= deadline_badge($process) ?></td>
                            <td><?= workflow_badge($process['response_status']) ?></td>
                            <td><?= status_badge($process['status']) ?></td>
                            <td class="text-end actions-cell">
                                <a class="btn btn-sm btn-light" href="<?= url('process_detail.php?id=' . (int) $process['id']) ?>" title="Detalhes"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-light" href="<?= url('process_form.php?id=' . (int) $process['id']) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$processes): ?>
                        <tr><td colspan="7"><div class="empty-state">Nenhum processo encontrado.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span><?= $total ?> registro(s)</span>
            <nav>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a class="page-link-mini <?= $i === $page ? 'active' : '' ?>" href="<?= url('processes.php?' . http_build_query(array_merge($filters, ['page' => $i]))) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
