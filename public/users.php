<?php

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
if (!can_manage($user)) {
    flash('Acesso restrito a coordenacao.', 'danger');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? 'servidor'));
    $password = (string) ($_POST['password'] ?? '');

    if ($name && $email && $password && in_array($role, config('dropdowns.roles'), true)) {
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        flash('Usuario criado.');
        redirect('users.php');
    }

    flash('Preencha todos os campos.', 'danger');
}

$users = db()->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY name')->fetchAll();
$pageTitle = 'Usuarios';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-heading">
        <div>
            <p class="eyebrow">Administracao</p>
            <h1>Usuarios</h1>
        </div>
    </section>

    <section class="panel">
        <h2>Novo usuario</h2>
        <form method="post" class="form-grid four">
            <label>Nome<input name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Senha<input type="password" name="password" required></label>
            <label>
                Perfil
                <select name="role">
                    <?php foreach (config('dropdowns.roles') as $role): ?>
                        <option value="<?= e($role) ?>"><?= e($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button primary" type="submit">Criar</button>
        </form>
    </section>

    <section class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Ativo</th></tr></thead>
            <tbody>
                <?php foreach ($users as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['email']) ?></td>
                        <td><?= e($item['role']) ?></td>
                        <td><?= $item['active'] ? 'Sim' : 'Nao' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>

