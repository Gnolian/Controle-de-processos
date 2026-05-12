<?php

use App\Repositories\UserRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_role(['admin', 'coordenador']);
$repo = new UserRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'create');
        $role = (string) ($_POST['role'] ?? 'servidor');
        if (!in_array($role, config('dropdowns.roles'), true)) {
            throw new RuntimeException('Perfil inválido.');
        }

        if ($action === 'update_access') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $targetUser = $repo->findById($targetId);
            if (!$targetUser) {
                throw new RuntimeException('Usuário não encontrado.');
            }
            if ($targetId === (int) $user['id'] && empty($_POST['active'])) {
                throw new RuntimeException('Você não pode desativar seu próprio usuário.');
            }
            if ($targetId === (int) $user['id'] && !empty($_POST['audit_only'])) {
                throw new RuntimeException('Você não pode limitar seu próprio usuário para somente auditorias.');
            }

            $repo->updateAccess($targetId, [
                'role' => $role,
                'active' => !empty($_POST['active']),
                'audit_access' => !empty($_POST['audit_access']) || !empty($_POST['audit_only']),
                'audit_only' => !empty($_POST['audit_only']),
            ]);
            flash('Acessos do usuário atualizados com sucesso.');
            redirect('users.php');
        } else {
            if (trim((string) ($_POST['name'] ?? '')) === '' || trim((string) ($_POST['email'] ?? '')) === '' || (string) ($_POST['password'] ?? '') === '') {
                throw new RuntimeException('Preencha nome, email e senha.');
            }
            if (!empty($_POST['audit_only'])) {
                $_POST['audit_access'] = '1';
            }
            $repo->create($_POST);
            flash('Usuário criado com sucesso.');
            redirect('users.php');
        }
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$editUser = null;
if (!empty($_GET['edit'])) {
    $editUser = $repo->findById((int) $_GET['edit']);
    if (!$editUser) {
        flash('Usuário não encontrado para edição.', 'danger');
        redirect('users.php');
    }
}

$users = $repo->all();
$pageTitle = 'Usuários';
$activeNav = 'users';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Acessos</p>
            <h1>Administração de usuários</h1>
        </div>
    </section>

    <?php if (!$editUser): ?>
        <section class="app-card mb-4">
            <div class="card-head"><h2>Novo usuário</h2></div>
            <form method="post" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
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
                    <label class="form-label d-block">Acesso a auditorias
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="audit_access" value="1">
                        </div>
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block">Somente auditorias
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="audit_only" value="1">
                        </div>
                    </label>
                </div>
                <div class="col-md-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> Criar</button></div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($editUser): ?>
        <section class="app-card mb-4 border border-primary-subtle">
            <div class="card-head">
                <div>
                    <p class="section-kicker mb-1">Editar acesso</p>
                    <h2><?= e($editUser['name']) ?></h2>
                    <p class="text-muted mb-0"><?= e($editUser['email']) ?></p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('users.php') ?>"><i class="bi bi-x-lg"></i> Cancelar</a>
            </div>
            <form method="post" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_access">
                <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                <div class="col-md-3">
                    <label class="form-label">Perfil
                        <select class="form-select mt-1" name="role">
                            <?php foreach (config('dropdowns.roles') as $role): ?>
                                <option value="<?= e($role) ?>" <?= selected((string) $editUser['role'], (string) $role) ?>><?= e($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="form-label d-block">Acesso a auditorias
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="audit_access" value="1" <?= checked(!empty($editUser['audit_access'])) ?>>
                        </div>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="form-label d-block">Somente auditorias
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="audit_only" value="1" <?= checked(!empty($editUser['audit_only'])) ?>>
                        </div>
                        <small class="text-muted">Quando marcado, o usuário só acessa o painel de auditorias.</small>
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block">Usuário ativo
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="active" value="1" <?= checked(!empty($editUser['active'])) ?>>
                        </div>
                    </label>
                </div>
                <div class="col-md-1 d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="app-card p-0 overflow-hidden">
        <table class="table modern-table mb-0">
            <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Auditorias</th><th>Somente Auditorias</th><th>Ativo</th><th>Criado em</th><th class="text-end">Ações</th></tr></thead>
            <tbody>
                <?php foreach ($users as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['email']) ?></td>
                        <td><span class="badge text-bg-light"><?= e($item['role']) ?></span></td>
                        <td><?= !empty($item['audit_access']) || !empty($item['audit_only']) || in_array($item['role'], ['admin', 'coordenador'], true) ? '<span class="badge text-bg-info">Sim</span>' : '<span class="badge text-bg-secondary">Não</span>' ?></td>
                        <td><?= !empty($item['audit_only']) ? '<span class="badge text-bg-warning">Sim</span>' : '<span class="badge text-bg-secondary">Não</span>' ?></td>
                        <td><?= $item['active'] ? '<span class="badge text-bg-success">Sim</span>' : '<span class="badge text-bg-secondary">Não</span>' ?></td>
                        <td><?= e($item['created_at']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('users.php?edit=' . (int) $item['id']) ?>">
                                <i class="bi bi-pencil-square"></i> Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
