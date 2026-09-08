<?php

use App\Services\AuthService;

require __DIR__ . '/../app/bootstrap.php';

$error = null;
$loggedUser = current_user();
if ($loggedUser) {
    if (can_access_audits($loggedUser)) {
        redirect('audits.php');
    }

    unset($_SESSION['user_id']);
    $error = 'Seu usuário não possui acesso aos painéis da DGBA.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if ((new AuthService())->attempt(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''))) {
            $user = current_user();
            if ($user && can_access_audits($user)) {
                redirect('audits.php');
            }

            unset($_SESSION['user_id']);
            $error = 'Seu usuário não possui acesso aos painéis da DGBA.';
        } else {
            $error = 'Email ou senha inválidos.';
        }
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
        <h1>Painéis DGBA</h1>
        <p>Ambiente interno para acompanhamento e gestão das auditorias da DGBA.</p>
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
