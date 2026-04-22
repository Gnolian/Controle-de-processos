<?php

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$repo = new ProcessRepository();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$process = $id ? $repo->find($id, $user) : null;

if ($id && !$process) {
    flash('Processo nao encontrado.', 'danger');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $repo->save($_POST, $user, $id);
        flash('Processo salvo com sucesso.');
        redirect('dashboard.php');
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
    }
}

$defaults = array_fill_keys(ProcessRepository::COLUMNS, '');
$defaults['updated_at'] = date('Y-m-d');
$defaults['response_status'] = 'A iniciar';
$defaults['andrea_review_status'] = 'N/A';
$defaults['signed_status'] = 'N/A';
$defaults['sent_gab_status'] = 'N/A';
$defaults['status'] = 'Aberto';
$process = array_merge($defaults, $process ?: []);
$pageTitle = $id ? 'Editar processo' : 'Novo processo';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="shell narrow">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-heading">
        <div>
            <p class="eyebrow"><?= $id ? 'Atualizacao' : 'Cadastro' ?></p>
            <h1><?= e($pageTitle) ?></h1>
        </div>
        <a class="button ghost" href="<?= url('dashboard.php') ?>">Voltar</a>
    </section>

    <form method="post" class="process-form">
        <div class="form-grid two">
            <?php input('process_number', 'Numero do Processo', $process, 'text', true); ?>
            <?php input('updated_at', 'DATA DA ATUALIZACAO', $process, 'date'); ?>
            <?php input('response_owner', 'Responsavel pela Resposta', $process); ?>
            <?php input('deadline_days', 'Prazo (em dias)', $process, 'number'); ?>
        </div>

        <div class="form-grid">
            <?php textarea('general_description', 'Descricao Geral', $process); ?>
            <?php textarea('detailed_description', 'Descricao Detalhada', $process); ?>
            <?php textarea('notes', 'Comentarios/anotacoes', $process); ?>
        </div>

        <div class="form-grid two">
            <?php input('requesting_agency', 'Orgao Solicitante', $process); ?>
            <?php input('gab_signature_date', 'Data de assinatura (Oficio GAB)', $process, 'date'); ?>
            <?php input('internal_deadline_gab', 'Prazo Interno (OFICIO GAB/SNBA)', $process, 'date'); ?>
            <?php input('adjusted_internal_deadline', 'Prazo Interno AJUSTADO', $process, 'date'); ?>
            <?php input('external_deadline_mds', 'Prazo Externo/MDS', $process, 'date'); ?>
            <?php input('review_owner', 'Responsavel pela Revisao', $process); ?>
        </div>

        <div class="form-grid two">
            <?php select_field('response_status', 'Resposta', config('dropdowns.workflow'), $process); ?>
            <?php select_field('andrea_review_status', 'Revisao Andrea', config('dropdowns.workflow'), $process); ?>
            <?php select_field('signed_status', 'Assinado', config('dropdowns.workflow'), $process); ?>
            <?php select_field('sent_gab_status', 'Enviado Gab', config('dropdowns.workflow'), $process); ?>
            <?php input('gab_sent_date', 'Data envio GAB', $process, 'date'); ?>
            <?php select_field('status', 'STATUS', config('dropdowns.status'), $process); ?>
            <?php input('internal_block', 'Bloco interno', $process); ?>
        </div>

        <div class="form-actions">
            <button class="button primary" type="submit">Salvar</button>
            <?php if ($id): ?>
                <a class="button danger" href="<?= url('delete.php?id=' . $id) ?>" onclick="return confirm('Excluir este processo?')">Excluir</a>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php
function input(string $name, string $label, array $values, string $type = 'text', bool $required = false): void
{
    ?>
    <label>
        <?= e($label) ?>
        <input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e((string) $values[$name]) ?>" <?= $required ? 'required' : '' ?>>
    </label>
    <?php
}

function textarea(string $name, string $label, array $values): void
{
    ?>
    <label>
        <?= e($label) ?>
        <textarea name="<?= e($name) ?>" rows="4"><?= e((string) $values[$name]) ?></textarea>
    </label>
    <?php
}

function select_field(string $name, string $label, array $options, array $values): void
{
    ?>
    <label>
        <?= e($label) ?>
        <select name="<?= e($name) ?>">
            <?php foreach ($options as $option): ?>
                <option value="<?= e($option) ?>" <?= selected((string) $values[$name], $option) ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

require __DIR__ . '/../views/footer.php';
?>

