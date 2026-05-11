<?php

use App\Services\ProcessService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_process_access();
$id = (int) ($_GET['id'] ?? 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Metodo invalido para exclusao.');
    }
    verify_csrf();
    (new ProcessService())->delete($id, $user);
    flash('Processo excluido com registro em auditoria.');
} catch (Throwable $exception) {
    flash($exception->getMessage(), 'danger');
}

redirect('processes.php');
