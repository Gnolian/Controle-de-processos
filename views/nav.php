<?php $navUser = current_user(); ?>
<aside class="app-sidebar">
    <a class="sidebar-brand" href="<?= url('dashboard.php') ?>">
        <span class="brand-mark"><i class="bi bi-kanban"></i></span>
        <span>Controle<br><strong>Processos</strong></span>
    </a>

    <nav class="sidebar-nav">
        <a class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Painel pessoal</a>
        <a class="<?= ($activeNav ?? '') === 'processes' ? 'active' : '' ?>" href="<?= url('processes.php') ?>"><i class="bi bi-folder2-open"></i> Processos</a>
        <?php if ($navUser && can_manage($navUser)): ?>
            <a class="<?= ($activeNav ?? '') === 'import' ? 'active' : '' ?>" href="<?= url('import.php') ?>"><i class="bi bi-cloud-upload"></i> Importar CSV</a>
            <a class="<?= ($activeNav ?? '') === 'management' ? 'active' : '' ?>" href="<?= url('management.php') ?>"><i class="bi bi-bar-chart"></i> Gerencial</a>
            <a class="<?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>" href="<?= url('audit.php') ?>"><i class="bi bi-clock-history"></i> Auditoria</a>
            <a class="<?= ($activeNav ?? '') === 'sync' ? 'active' : '' ?>" href="<?= url('sync.php') ?>"><i class="bi bi-arrow-repeat"></i> Sincronizacao</a>
            <a class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" href="<?= url('users.php') ?>"><i class="bi bi-people"></i> Usuarios</a>
            <a class="<?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>" href="<?= url('settings.php') ?>"><i class="bi bi-sliders"></i> Integracao</a>
        <?php endif; ?>
    </nav>
</aside>

<div class="app-main">
    <header class="app-topbar">
        <div>
            <p class="topbar-kicker mb-0">Sistema interno</p>
            <strong><?= e($pageTitle ?? 'Controle de Processos') ?></strong>
        </div>
        <div class="topbar-actions">
            <a class="btn btn-primary btn-sm" href="<?= url('process_form.php') ?>"><i class="bi bi-plus-lg"></i> Novo processo</a>
            <div class="user-chip">
                <span><?= e($navUser['name'] ?? '') ?></span>
                <small><?= e($navUser['role'] ?? '') ?></small>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>
