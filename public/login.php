<?php

use App\Services\AuthService;

require __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    $user = current_user();
    redirect(can_access_process_area($user) ? 'dashboard.php' : 'audits.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if ((new AuthService())->attempt(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''))) {
            $user = current_user();
            redirect($user && can_access_process_area($user) ? 'dashboard.php' : 'audits.php');
        }
        $error = 'Email ou senha invalidos.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Entrar';
$bodyClass = 'login-body';
require __DIR__ . '/../views/header.php';
?>

<main class="login-shell">
    <section class="login-hero">
        <span class="login-icon"><i class="bi bi-shield-lock"></i></span>
        <h1>Controle de Processos</h1>
        <p>Uma interface interna para preencher, acompanhar, auditar e sincronizar processos com a planilha gerencial.</p>
    </section>

    <section class="login-card">
        <p class="text-primary fw-semibold mb-2">Acesso interno</p>
        <h2 class="h4 mb-4">Entrar no sistema</h2>

        <?php require __DIR__ . '/../views/flash.php'; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                Email
                <input class="form-control form-control-lg mt-1" type="email" name="email" required autofocus>
            </label>
            <label class="form-label">
                Senha
                <input class="form-control form-control-lg mt-1" type="password" name="password" required>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
        </form>

        <div class="text-center mt-3">
            <a class="btn btn-link text-decoration-none" href="<?= url('change_password.php') ?>">
                <i class="bi bi-key"></i> Alterar minha senha
            </a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
