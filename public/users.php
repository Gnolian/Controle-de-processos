<?php

use App\Repositories\UserRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_role(['admin', 'coordenador']);
$repo = new UserRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $role = (string) ($_POST['role'] ?? 'servidor');
        if (!in_array($role, config('dropdowns.roles'), true)) {
            throw new RuntimeException('Perfil invalido.');
        }
        if (trim((string) ($_POST['name'] ?? '')) === '' || trim((string) ($_POST['email'] ?? '')) === '' || (string) ($_POST['password'] ?? '') === '') {
            throw new RuntimeException('Preencha nome, email e senha.');
        }
        $repo->create($_POST);
        flash('Usuario criado com sucesso.');
        redirect('users.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$users = $repo->all();
$pageTitle = 'Usuarios';
$activeNav = 'users';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Acessos</p>
            <h1>Administracao de usuarios</h1>
        </div>
    </section>

    <section class="app-card mb-4">
        <div class="card-head"><h2>Novo usuario</h2></div>
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label">Nome<input class="form-control mt-1" name="name" required></label></div>
            <div class="col-md-3"><label class="form-label">Email<input class="form-control mt-1" type="email" name="email" required></label></div>
            <div class="col-md-2"><label class="form-label">Senha<input class="form-control mt-1" type="password" name="password" required></label></div>
            <div class="col-md-2">
                <label class="form-label">Perfil
                    <select class="form-select mt-1" name="role">
                        <?php foreach (config('dropdowns.roles') as $role): ?>
                            <option value="<?= e($role) ?>"><?= e($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="col-md-2">
                <label class="form-label d-block">Acesso auditorias
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="audit_access" value="1">
                    </div>
                </label>
            </div>
            <div class="col-md-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> Criar</button></div>
        </form>
    </section>

    <section class="app-card p-0 overflow-hidden">
        <table class="table modern-table mb-0">
            <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Auditorias</th><th>Ativo</th><th>Criado em</th></tr></thead>
            <tbody>
                <?php foreach ($users as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['email']) ?></td>
                        <td><span class="badge text-bg-light"><?= e($item['role']) ?></span></td>
                        <td><?= !empty($item['audit_access']) || in_array($item['role'], ['admin', 'coordenador'], true) ? '<span class="badge text-bg-info">Sim</span>' : '<span class="badge text-bg-secondary">Nao</span>' ?></td>
                        <td><?= $item['active'] ? '<span class="badge text-bg-success">Sim</span>' : '<span class="badge text-bg-secondary">Nao</span>' ?></td>
                        <td><?= e($item['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
