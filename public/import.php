<?php

use App\Services\CsvImportService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_process_access();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Envie um arquivo CSV valido.');
        }

        $result = (new CsvImportService())->importUploaded($_FILES['csv']['tmp_name'], $user);
        flash("Importacao concluida: {$result['created']} criado(s), {$result['updated']} atualizado(s), {$result['ignored']} ignorado(s).");
        redirect('processes.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$pageTitle = 'Importar CSV';
$activeNav = 'import';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Carga inicial</p>
            <h1>Importar processos da planilha</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('processes.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="app-card">
        <div class="card-head">
            <div>
                <h2>Arquivo CSV do Excel/SharePoint</h2>
                <p class="text-secondary mb-0">Use o CSV exportado da primeira pagina da planilha. O sistema atualiza processos existentes pelo numero e cria os novos.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                CSV
                <input class="form-control form-control-lg mt-1" type="file" name="csv" accept=".csv,text/csv" required>
            </label>
            <div class="alert alert-info mb-0">
                Antes da importação, execute as migrations se ainda não executou. Processos sem prazos internos ou externos serão marcados como Tempo Hábil.
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Importar CSV</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
