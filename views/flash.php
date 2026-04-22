<?php if ($message = flash()): ?>
    <div class="alert alert-<?= $message['type'] === 'danger' ? 'danger' : 'success' ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= e($message['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

