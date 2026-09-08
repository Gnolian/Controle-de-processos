<?php if ($message = flash()): ?>
    <?php $flashType = in_array($message['type'], ['success', 'danger', 'warning', 'info'], true) ? $message['type'] : 'success'; ?>
    <div class="alert alert-<?= e($flashType) ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= e($message['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>
