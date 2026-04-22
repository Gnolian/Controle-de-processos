<?php

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

try {
    (new ProcessRepository())->delete($id, $user);
    flash('Processo excluido.');
} catch (Throwable $exception) {
    flash($exception->getMessage(), 'danger');
}

redirect('dashboard.php');

