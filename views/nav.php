<?php $navUser = current_user(); ?>
<aside class="app-sidebar">
    <a class="sidebar-brand" href="<?= url('audits.php') ?>">
        <span class="brand-mark"><i class="bi bi-grid-1x2"></i></span>
        <span>Painéis<br><strong>DGBA</strong></span>
    </a>

    <nav class="sidebar-nav">
        <?php if ($navUser && can_access_audits($navUser)): ?>
            <a class="<?= ($activeNav ?? '') === 'audits' ? 'active' : '' ?>" href="<?= url('audits.php') ?>"><i class="bi bi-shield-check"></i> Auditorias</a>
        <?php endif; ?>
        <?php if ($navUser && can_manage($navUser)): ?>
            <a class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" href="<?= url('users.php') ?>"><i class="bi bi-people"></i> Usuários</a>
        <?php endif; ?>
    </nav>
</aside>

<div class="app-main">
    <header class="app-topbar">
        <div>
            <p class="topbar-kicker mb-0">Sistema interno</p>
            <strong><?= e($pageTitle ?? 'Painéis DGBA') ?></strong>
        </div>
        <div class="topbar-actions">
            <?php if (($activeNav ?? '') === 'audits' && $navUser && can_edit_audits($navUser)): ?>
                <a class="btn btn-primary btn-sm" href="<?= url('audit_form.php') ?>"><i class="bi bi-plus-lg"></i> Nova auditoria</a>
            <?php endif; ?>
            <div class="user-chip">
                <span><?= e($navUser['name'] ?? '') ?></span>
                <small><?= e($navUser['role'] ?? '') ?></small>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>
