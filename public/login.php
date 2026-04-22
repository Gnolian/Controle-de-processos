<?php

require __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $validPassword = false;
    if ($user) {
        $stored = (string) $user['password_hash'];
        $validPassword = password_verify($password, $stored)
            || hash_equals(strtolower($stored), hash('sha256', $password));
    }

    if ($user && $validPassword) {
        $_SESSION['user_id'] = (int) $user['id'];
        redirect('dashboard.php');
    }

    $error = 'Email ou senha invalidos.';
}

$pageTitle = 'Entrar';
require __DIR__ . '/../views/header.php';
?>

<main class="auth-page">
    <section class="login-panel">
        <div>
            <p class="eyebrow">Acesso interno</p>
            <h1>Controle de Processos</h1>
            <p class="muted">Preencha uma vez, acompanhe prazos e exporte a planilha quando precisar.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <label>
                Email
                <input type="email" name="email" required autofocus>
            </label>
            <label>
                Senha
                <input type="password" name="password" required>
            </label>
            <button class="button primary" type="submit">Entrar</button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
