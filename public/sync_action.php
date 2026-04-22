<?php

use App\Services\SyncService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

try {
    (new SyncService())->syncProcess($id, $user);
    flash('Processo reenviado para a planilha.');
} catch (Throwable $exception) {
    flash($exception->getMessage(), 'danger');
}

redirect('process_detail.php?id=' . $id);

