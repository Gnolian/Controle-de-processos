<?php

use App\Services\AuditCsvImportService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_audit_access();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Envie um arquivo CSV valido.');
        }

        $result = (new AuditCsvImportService())->importUploaded($_FILES['csv']['tmp_name'], $user);
        flash("Base de auditorias importada: {$result['audits']} auditoria(s) processada(s).");
        redirect('audits.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$pageTitle = 'Importar auditorias';
$activeNav = 'audits';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Carga de auditorias</p>
            <h1>Importar base CSV</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="app-card">
        <div class="card-head"><h2>Base exportada do painel de auditorias</h2></div>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                CSV da base
                <input class="form-control form-control-lg mt-1" type="file" name="csv" accept=".csv,text/csv" required>
            </label>
            <div class="alert alert-info mb-0">
                O importador limpa o formato da planilha, cria auditorias únicas por código e vincula determinações, recomendações e ciências.
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Importar auditorias</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>

