<?php if ($message = flash()): ?>
    <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
<?php endif; ?>

