<?php $navUser = current_user(); ?>
<header class="topbar">
    <a class="brand" href="<?= url('dashboard.php') ?>">Controle de Processos</a>
    <nav>
        <a href="<?= url('dashboard.php') ?>">Processos</a>
        <?php if ($navUser && can_manage($navUser)): ?>
            <a href="<?= url('management.php') ?>">Gerencial</a>
            <a href="<?= url('users.php') ?>">Usuarios</a>
        <?php endif; ?>
        <span><?= e($navUser['name'] ?? '') ?></span>
        <a href="<?= url('logout.php') ?>">Sair</a>
    </nav>
</header>

