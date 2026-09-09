<?php

use App\Services\InssMonitoringImportService;

require __DIR__ . '/../app/bootstrap.php';

$user = require_inss_monitoring_access();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!isset($_FILES['monitoring_file']) || $_FILES['monitoring_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Selecione a planilha XLSX ou um arquivo CSV válido.');
        }
        if ((int) $_FILES['monitoring_file']['size'] > 20 * 1024 * 1024) {
            throw new RuntimeException('O arquivo excede o limite de 20 MB.');
        }

        $imported = (new InssMonitoringImportService())->import(
            (string) $_FILES['monitoring_file']['tmp_name'],
            (string) $_FILES['monitoring_file']['name'],
            (int) $user['id']
        );
        flash($imported . ($imported === 1 ? ' demanda importada com sucesso.' : ' demandas importadas com sucesso.'));
        redirect('inss_monitoring.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$pageTitle = 'Importar monitoramento INSS';
$activeNav = 'inss';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Carga inicial e atualização</p>
            <h1>Importar monitoramento INSS</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('inss_monitoring.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <section class="app-card study-import-card">
        <div class="card-head"><h2>Base de respostas aos ofícios</h2></div>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
            <?= csrf_field() ?>
            <label class="form-label">
                Arquivo XLSX ou CSV
                <input class="form-control form-control-lg mt-1" type="file" name="monitoring_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </label>
            <div class="alert alert-info mb-0">
                Use a planilha “Base para monitoramento respostas ao INSS”. Registros são identificados pelo processo SEI e pelo número do ofício enviado. Uma nova importação atualiza os dados de origem sem apagar acompanhamentos já preenchidos na aplicação.
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Importar demandas</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
