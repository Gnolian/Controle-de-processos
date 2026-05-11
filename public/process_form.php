<?php

use App\Repositories\ProcessRepository;
use App\Services\ProcessService;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

$user = require_process_access();
$repo = new ProcessRepository();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$process = $id ? $repo->find($id, $user) : null;

if ($id && !$process) {
    flash('Processo não encontrado.', 'danger');
    redirect('processes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $savedId = (new ProcessService())->save($_POST, $user, $id);
        flash('Processo salvo com sucesso.');
        redirect('process_detail.php?id=' . $savedId);
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$process = process_defaults($process);
$pageTitle = $id ? 'Editar processo' : 'Novo processo';
$activeNav = 'processes';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker"><?= $id ? 'Atualização' : 'Cadastro' ?></p>
            <h1><?= e($pageTitle) ?></h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= $id ? url('process_detail.php?id=' . $id) : url('processes.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <form method="post" class="app-card form-card">
        <?= csrf_field() ?>

        <div class="form-section">
            <h2>Identificação</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_input('process_number', 'Número do Processo', $process, 'text', true, 'bi-hash'); ?>
                <?php field_input('updated_at', 'Data da Atualização', $process, 'date', false, 'bi-calendar-event'); ?>
                <?php field_input('response_owner', 'Responsável pela Resposta', $process, 'text', true, 'bi-person'); ?>
                <?php field_input('deadline_days', 'Prazo (em dias)', $process, 'number', true, 'bi-clock'); ?>
                <?php field_select_assoc('deadline_type', 'Tipo de prazo', ['data' => 'Data definida', 'tempo_habil' => 'Tempo Hábil'], $process, 'bi-briefcase', true); ?>
                <?php field_input('requesting_agency', 'Órgão Solicitante', $process, 'text', true, 'bi-building'); ?>
                <?php field_input('internal_block', 'Bloco interno', $process, 'text', false, 'bi-box'); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Descrições e anotações</h2>
            <div class="row g-3">
                <?php field_textarea('general_description', 'Descrição Geral', $process, 3, true); ?>
                <?php field_textarea('detailed_description', 'Descrição Detalhada', $process, 4); ?>
                <?php field_textarea('notes', 'Comentários/anotações', $process, 3); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Prazos e revisão</h2>
            <p class="text-secondary">Se o processo for de Tempo Hábil, selecione esse tipo de prazo na identificação. Caso contrário, preencha todos os campos de data desta seção.</p>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_input('gab_signature_date', 'Data de assinatura (Ofício GAB)', $process, 'date', false, 'bi-pen'); ?>
                <?php field_input('internal_deadline_gab', 'Prazo Interno (OFÍCIO GAB/SNBA)', $process, 'date', false, 'bi-calendar-week'); ?>
                <?php field_input('adjusted_internal_deadline', 'Prazo Interno AJUSTADO', $process, 'date', false, 'bi-calendar-check'); ?>
                <?php field_input('external_deadline_mds', 'Prazo Externo/MDS', $process, 'date', false, 'bi-calendar2-range'); ?>
                <?php field_input('review_owner', 'Responsável pela Revisão', $process, 'text', true, 'bi-person-check'); ?>
                <?php field_input('gab_sent_date', 'Data envio GAB', $process, 'date', false, 'bi-send'); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Status controlados</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_select('response_status', 'Resposta', config('dropdowns.workflow'), $process); ?>
                <?php field_select('andrea_review_status', 'Revisão Andrea', config('dropdowns.workflow'), $process); ?>
                <?php field_select('signed_status', 'Assinado', config('dropdowns.workflow'), $process); ?>
                <?php field_select('sent_gab_status', 'Enviado Gab', config('dropdowns.workflow'), $process); ?>
                <?php field_select('status', 'STATUS', config('dropdowns.status'), $process, 'bi-flag'); ?>
            </div>
        </div>

        <div class="form-actions sticky-actions">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar processo</button>
            <?php if ($id): ?>
                <button class="btn btn-outline-danger" type="submit" formaction="<?= url('delete.php?id=' . $id) ?>" formmethod="post" data-confirm="Excluir este processo? Essa ação não pode ser desfeita."><i class="bi bi-trash"></i> Excluir</button>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
