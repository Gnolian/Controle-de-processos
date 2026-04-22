<?php

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_role(['admin', 'coordenador']);
$filters = [
    'process_id' => trim((string) ($_GET['process_id'] ?? '')),
    'user_id' => trim((string) ($_GET['user_id'] ?? '')),
    'from' => trim((string) ($_GET['from'] ?? '')),
    'to' => trim((string) ($_GET['to'] ?? '')),
];
$logs = (new AuditLogRepository())->search($filters);
$users = (new UserRepository())->all();
$pageTitle = 'Auditoria';
$activeNav = 'audit';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <section class="page-title-row">
        <div>
            <p class="section-kicker">Trilha de mudancas</p>
            <h1>Auditoria</h1>
        </div>
    </section>

    <form class="filter-card" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">ID do processo<input class="form-control" name="process_id" value="<?= e($filters['process_id']) ?>"></label></div>
            <div class="col-md-3">
                <label class="form-label">Usuario
                    <select class="form-select" name="user_id">
                        <option value="">Todos</option>
                        <?php foreach ($users as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= selected($filters['user_id'], (string) $item['id']) ?>><?= e($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="col-md-2"><label class="form-label">De<input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></label></div>
            <div class="col-md-2"><label class="form-label">Ate<input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></label></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= url('audit.php') ?>">Limpar</a></div>
        </div>
    </form>

    <section class="app-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead><tr><th>Data</th><th>Processo</th><th>Usuario</th><th>Campo</th><th>Antes</th><th>Depois</th><th>Origem</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['created_at']) ?></td>
                            <td><?= e($log['process_number'] ?: '-') ?></td>
                            <td><?= e($log['user_name'] ?: 'Sistema') ?></td>
                            <td><span class="badge text-bg-light"><?= e($log['field_name']) ?></span></td>
                            <td class="audit-value"><?= e($log['old_value'] ?: '-') ?></td>
                            <td class="audit-value"><?= e($log['new_value'] ?: '-') ?></td>
                            <td><?= e($log['origin']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="7"><div class="empty-state">Nenhum registro encontrado.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

