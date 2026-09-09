<?php

use App\Repositories\InssMonitoringRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_inss_monitoring_access();
$repository = new InssMonitoringRepository();
$requestId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['record_id'] ?? null)
    : ($_GET['id'] ?? null);
$id = filter_var($requestId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$record = $id ? $repository->find($id) : null;

if ($id && !$record) {
    flash('Demanda de monitoramento não encontrada.', 'danger');
    redirect('inss_monitoring.php');
}

$allColumns = array_merge(InssMonitoringRepository::BASE_COLUMNS, InssMonitoringRepository::TRACKING_COLUMNS);
$values = array_merge(array_fill_keys($allColumns, ''), $record ?: []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $savedId = $repository->save($_POST, $id, (int) $user['id']);
        flash($id ? 'Monitoramento atualizado com sucesso.' : 'Demanda adicionada ao monitoramento.');
        redirect('inss_monitoring.php?highlight=' . $savedId . '#monitoring-' . $savedId);
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
        $values = array_merge($values, $_POST);
    }
}

$renderInput = static function (
    string $name,
    string $label,
    array $formValues,
    string $type = 'text',
    bool $required = false,
    string $placeholder = ''
): void {
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <input class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"
            value="<?= e((string) ($formValues[$name] ?? '')) ?>" <?= $required ? 'required' : '' ?>
            <?= $placeholder !== '' ? 'placeholder="' . e($placeholder) . '"' : '' ?>>
    </div>
    <?php
};

$renderSelect = static function (string $name, string $label, array $options, array $formValues): void {
    $current = trim((string) ($formValues[$name] ?? ''));
    if ($current !== '' && !in_array($current, $options, true)) {
        array_unshift($options, $current);
    }
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <select class="form-select" id="<?= e($name) ?>" name="<?= e($name) ?>">
            <option value="">Selecione</option>
            <?php foreach ($options as $option): ?>
                <option value="<?= e($option) ?>" <?= selected($current, $option) ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};

$pageTitle = $id ? 'Editar monitoramento INSS' : 'Nova demanda INSS';
$activeNav = 'inss';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker">Respostas a ofícios</p>
            <h1><?= e($pageTitle) ?></h1>
            <?php if ($record && $record['elapsed_days'] !== null): ?>
                <p class="text-muted mb-0"><?= (int) $record['elapsed_days'] ?> dias decorridos desde o envio ao INSS.</p>
            <?php endif; ?>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('inss_monitoring.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <form method="post" action="<?= url('inss_monitoring_form.php' . ($id ? '?id=' . $id : '')) ?>" class="app-card form-card inss-form-card">
        <?= csrf_field() ?>
        <?php if ($id): ?>
            <input type="hidden" name="record_id" value="<?= (int) $id ?>">
        <?php endif; ?>

        <div class="form-section">
            <h2>Ofício enviado</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <?php $renderInput('external_id', 'ID da planilha', $values, 'number'); ?>
                <?php $renderInput('sei_process', 'Processo SEI', $values, 'text', true, '71000.000000/2026-00'); ?>
                <?php $renderInput('sent_office_number', 'Nº do ofício enviado', $values); ?>
                <?php $renderInput('sent_office_sei', 'SEI do ofício enviado', $values); ?>
                <?php $renderInput('office_date', 'Data do ofício', $values, 'date'); ?>
                <?php $renderInput('sent_to_inss_date', 'Data de envio ao INSS', $values, 'date'); ?>
                <div class="col-12">
                    <label class="form-label" for="inss_recipient_unit">Unidade destinatária INSS</label>
                    <input class="form-control" id="inss_recipient_unit" name="inss_recipient_unit" value="<?= e((string) $values['inss_recipient_unit']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="sei_link">Link do processo no SEI</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input class="form-control" id="sei_link" name="sei_link" inputmode="url" value="<?= e((string) $values['sei_link']) ?>" placeholder="https://sei.mds.gov.br/..."><span class="input-group-text">Opcional</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Demanda e beneficiário</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <div class="col-12">
                    <label class="form-label" for="subject">Assunto</label>
                    <textarea class="form-control" id="subject" name="subject" rows="2"><?= e((string) $values['subject']) ?></textarea>
                </div>
                <?php $renderInput('demand_type', 'Tipo de demanda', $values); ?>
                <?php $renderInput('demand_origin', 'Origem da demanda', $values); ?>
                <?php $renderInput('beneficiary', 'Beneficiário/Interessado', $values); ?>
                <?php $renderInput('cpf', 'CPF', $values); ?>
                <?php $renderInput('benefit_number', 'NB', $values); ?>
            </div>
        </div>

        <div class="form-section inss-tracking-form-section">
            <h2>Acompanhamento da resposta</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <?php $renderSelect('deadline_status', 'Situação do prazo', ['Sem prazo', 'No prazo', 'Atenção', 'Prazo vencido', 'Respondido'], $values); ?>
                <?php $renderSelect('status', 'Status', ['Aguardando resposta', 'Resposta recebida', 'Em análise', 'Em cobrança', 'Concluído'], $values); ?>
                <?php $renderSelect('priority', 'Prioridade', ['Alta', 'Média', 'Baixa'], $values); ?>
                <?php $renderInput('inss_response_date', 'Data da resposta INSS', $values, 'date'); ?>
                <?php $renderInput('response_office_number', 'Nº Ofício/Resposta INSS', $values); ?>
                <?php $renderInput('response_sei', 'SEI da resposta', $values); ?>
                <?php $renderSelect('conclusive_response', 'Resposta conclusiva?', ['A avaliar', 'Sim', 'Parcialmente', 'Não'], $values); ?>
                <?php $renderSelect('needs_follow_up', 'Necessita nova cobrança?', ['A avaliar', 'Sim', 'Não'], $values); ?>
                <?php $renderInput('follow_up_date', 'Data da cobrança', $values, 'date'); ?>
                <?php $renderInput('follow_up_count', 'Quantidade de cobranças', $values, 'number'); ?>
                <?php $renderSelect('owner', 'Responsável pelo acompanhamento', $repository->ownerOptions(), $values); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Observações</h2>
            <label class="form-label" for="notes">Informações complementares</label>
            <textarea class="form-control" id="notes" name="notes" rows="5"><?= e((string) $values['notes']) ?></textarea>
        </div>

        <div class="form-actions sticky-actions">
            <a class="btn btn-outline-secondary" href="<?= url('inss_monitoring.php') ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?= $id ? 'Atualizar registro' : 'Salvar monitoramento' ?></button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
