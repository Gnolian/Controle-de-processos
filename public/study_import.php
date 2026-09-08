<?php

use App\Services\StudyImportService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_study_access();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!isset($_FILES['studies_file']) || $_FILES['studies_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Selecione uma planilha XLSX ou um arquivo CSV válido.');
        }
        if ((int) $_FILES['studies_file']['size'] > 20 * 1024 * 1024) {
            throw new RuntimeException('O arquivo excede o limite de 20 MB.');
        }

        $imported = (new StudyImportService())->import(
            (string) $_FILES['studies_file']['tmp_name'],
            (string) $_FILES['studies_file']['name'],
            (int) $user['id']
        );
        flash($imported . ($imported === 1 ? ' estudo importado com sucesso.' : ' estudos importados com sucesso.'));
        redirect('studies.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$pageTitle = 'Importar banco de estudos';
$activeNav = 'studies';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Atualização do acervo</p>
            <h1>Importar banco de estudos</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('studies.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="app-card study-import-card">
        <div class="card-head"><h2>Planilha de estudos</h2></div>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                Arquivo XLSX ou CSV
                <input class="form-control form-control-lg mt-1" type="file" name="studies_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </label>
            <div class="alert alert-info mb-0">
                A importação reconhece os cabeçalhos da matriz de evidências. Estudos já cadastrados são atualizados e novos registros são incluídos sem duplicar os links existentes.
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Importar estudos</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
