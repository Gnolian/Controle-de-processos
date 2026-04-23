<?php

use App\Services\AuditCsvImportService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_audit_access();

if (!audits_module_ready()) {
    flash('Antes de importar a base de auditorias, execute a migration 004_add_audits_module.sql no banco.', 'danger');
    redirect('audits.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        if (!isset($_FILES['audits_csv']) || $_FILES['audits_csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Envie o CSV tratado de auditorias.');
        }

        if (!isset($_FILES['items_csv']) || $_FILES['items_csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Envie o CSV tratado de itens da auditoria.');
        }

        $result = (new AuditCsvImportService())->importNormalizedUploads(
            $_FILES['audits_csv']['tmp_name'],
            $_FILES['items_csv']['tmp_name'],
            $user
        );

        flash("Base tratada importada: {$result['audits']} auditoria(s) e {$result['items']} item(ns) processado(s).");
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
            <h1>Importar base tratada</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('audits.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="app-card">
        <div class="card-head"><h2>CSV tratado para auditorias e itens</h2></div>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                CSV tratado de auditorias
                <input class="form-control form-control-lg mt-1" type="file" name="audits_csv" accept=".csv,text/csv" required>
            </label>
            <label class="form-label">
                CSV tratado de itens
                <input class="form-control form-control-lg mt-1" type="file" name="items_csv" accept=".csv,text/csv" required>
            </label>
            <div class="alert alert-info mb-0">
                Esta tela importa diretamente os arquivos tratados, com campos repetidos e prontos para carga.
                Use o arquivo principal das auditorias e o arquivo complementar com determinacoes, recomendacoes e ciencias.
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Importar base tratada</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
