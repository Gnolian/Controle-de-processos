<?php

use App\Services\AuthService;

require __DIR__ . '/../app/bootstrap.php';

$currentUser = current_user();
$error = null;
$email = $currentUser['email'] ?? trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($email === '' || $currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new RuntimeException('Preencha todos os campos.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('A nova senha e a confirmação precisam ser iguais.');
        }

        if (mb_strlen($newPassword) < 8) {
            throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
        }

        (new AuthService())->changePassword($email, $currentPassword, $newPassword);

        flash('Senha alterada com sucesso. Entre com a nova senha.', 'success');
        redirect('login.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Alterar senha';
$bodyClass = 'login-body';
require __DIR__ . '/../views/header.php';
?>

<main class="login-shell">
    <section class="login-hero">
        <span class="login-icon"><i class="bi bi-key"></i></span>
        <h1>Alteração de senha</h1>
        <p>Use seu email e a senha atual para definir uma nova senha de acesso ao sistema.</p>
    </section>

    <section class="login-card">
        <p class="text-primary fw-semibold mb-2">Segurança de acesso</p>
        <h2 class="h4 mb-4">Atualizar senha</h2>

        <?php require __DIR__ . '/../views/flash.php'; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                Email
                <input class="form-control form-control-lg mt-1" type="email" name="email" value="<?= e($email) ?>" required autofocus>
            </label>
            <label class="form-label">
                Senha atual
                <input class="form-control form-control-lg mt-1" type="password" name="current_password" required>
            </label>
            <label class="form-label">
                Nova senha
                <input class="form-control form-control-lg mt-1" type="password" name="new_password" minlength="8" required>
            </label>
            <label class="form-label">
                Confirmar nova senha
                <input class="form-control form-control-lg mt-1" type="password" name="confirm_password" minlength="8" required>
            </label>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-shield-check"></i> Alterar senha</button>
            <a class="btn btn-outline-secondary btn-lg w-100" href="<?= url('login.php') ?>"><i class="bi bi-arrow-left"></i> Voltar para o login</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
