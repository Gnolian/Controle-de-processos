<?php

use App\Repositories\AuditRepository;
use App\Services\AuditService;

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../views/components.php';

if (!function_exists('audit_defaults')) {
    function audit_defaults(?array $audit = null, array $items = []): array
    {
        $defaults = array_fill_keys(AuditRepository::AUDIT_COLUMNS, '');
        $defaults['audit_year'] = date('Y');
        $defaults['flag_estimated'] = 0;
        $defaults['has_diligence'] = 0;
        $defaults['deadline_is_current'] = 0;
        $defaults['flag_stage2_diligence'] = 0;
        $defaults['items'] = array_map(static function (array $item): array {
            if (isset($item['item_kind'])) {
                $item['item_kind'] = str_replace(['DETERMINAÇÃO', 'RECOMENDAÇÃO', 'CIÊNCIA'], ['DETERMINACAO', 'RECOMENDACAO', 'CIENCIA'], (string) $item['item_kind']);
            }
            return $item;
        }, $items);

        $data = array_merge($defaults, $audit ?: []);
        $data['stage3_accord_report'] = $data['stage3_accord_report'] ?: ($data['accord_report'] ?? '');
        $data['stage3_accord_report_date'] = $data['stage3_accord_report_date'] ?: ($data['accord_report_date'] ?? '');
        $data['control_summary'] = $data['control_summary'] ?: ($data['related_processes'] ?? '');

        return $data;
    }
}

$user = require_audit_access();
$moduleReady = audits_schema_ready();
if (!$moduleReady) {
    flash('Antes de cadastrar auditorias, execute a migration 004_add_audits_module.sql e depois a 005_expand_audits_for_timeline.sql.', 'danger');
    redirect('audits.php');
}

$repo = new AuditRepository();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$audit = $id ? $repo->find($id) : null;
$items = $id ? $repo->items($id) : [];

if ($id && !$audit) {
    flash('Auditoria nao encontrada.', 'danger');
    redirect('audits.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $savedId = (new AuditService())->save($_POST, $user, $id);
        flash('Auditoria salva com sucesso.');
        redirect('audit_detail.php?id=' . $savedId);
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
        $audit = $_POST;
        $items = $_POST['items'] ?? [];
    }
}

$values = audit_defaults($audit, $items);
$pageTitle = $id ? 'Editar auditoria' : 'Nova auditoria';
$activeNav = 'audits';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker"><?= $id ? 'Atualizacao' : 'Cadastro manual' ?></p>
            <h1><?= e($pageTitle) ?></h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= $id ? url('audit_detail.php?id=' . $id) : url('audits.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <form method="post" class="app-card form-card">
        <?= csrf_field() ?>

        <div class="form-section">
            <h2>Identificacao</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_input('audit_code', 'Codigo da auditoria', $values, 'text', true, 'bi-hash'); ?>
                <?php field_input('audit_nup', 'NUP', $values, 'text', true, 'bi-file-earmark-text'); ?>
                <?php field_input('audit_year', 'Ano', $values, 'number', false, 'bi-calendar3'); ?>
                <?php field_input('requesting_body', 'Orgao de controle', $values, 'text', true, 'bi-building'); ?>
                <?php field_input('audit_type', 'Tipo de auditoria', $values, 'text', true, 'bi-diagram-3'); ?>
                <?php field_input('process_status', 'Status da auditoria', $values, 'text', false, 'bi-flag'); ?>
                <?php field_input('audit_phase', 'Fase da auditoria', $values, 'text', true, 'bi-signpost-split'); ?>
                <?php field_input('classification', 'Classificacao', $values, 'text', false, 'bi-bookmark'); ?>
                <?php field_input('current_owner', 'Responsavel atual', $values, 'text', false, 'bi-person-badge'); ?>
                <?php field_input('start_date', 'Data de inicio', $values, 'date', false, 'bi-calendar-event'); ?>
                <?php field_input('last_date_response', 'Ultima data de resposta', $values, 'date', false, 'bi-reply'); ?>
                <?php field_input('deadline_label', 'Proximo prazo / deadline', $values, 'text', false, 'bi-hourglass-split'); ?>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-3 mt-1">
                <?php field_checkbox('deadline_is_current', 'Data atual', $values, 'Marque quando o prazo puder chegar a qualquer momento.'); ?>
                <?php field_checkbox('flag_estimated', 'Prazo estimado', $values); ?>
                <?php field_checkbox('has_diligence', 'Em diligencia', $values); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Contexto e ponto de controle</h2>
            <div class="row g-3">
                <?php field_textarea('objective', 'Objetivo da auditoria', $values, 4); ?>
                <?php field_textarea('theme', 'Tema', $values, 3); ?>
                <?php field_textarea('control_summary', 'Resumo do ponto de controle', $values, 4); ?>
                <?php field_textarea('related_processes', 'Processos relacionados', $values, 2); ?>
                <?php field_textarea('notes', 'Observacoes internas', $values, 3); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Diligencia e etapa 2</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_input('last_response_sent_date_diligence', 'Ultima resposta enviada em diligencia', $values, 'date', false, 'bi-send-check'); ?>
                <?php field_input('stage2_start_date', 'Inicio da etapa 2', $values, 'date', false, 'bi-play-circle'); ?>
                <?php field_checkbox('flag_stage2_diligence', 'Etapa 2 em diligencia', $values); ?>
                <?php field_input('stage2_date_last_response_diligence', 'Ultima resposta da etapa 2', $values, 'date', false, 'bi-chat-left-text'); ?>
                <?php field_input('stage2_preliminary_document', 'Documento preliminar etapa 2', $values, 'text', false, 'bi-file-earmark-medical'); ?>
                <?php field_input('stage2_deadline_days', 'Prazo etapa 2 (dias)', $values, 'number', false, 'bi-clock'); ?>
                <?php field_input('comments_due_date', 'Prazo para comentarios', $values, 'date', false, 'bi-chat-right-dots'); ?>
                <?php field_input('stage2_final_response', 'Resposta final etapa 2', $values, 'date', false, 'bi-reply-all'); ?>
                <?php field_input('stage2_final_report', 'Relatorio final etapa 2', $values, 'text', false, 'bi-journal-text'); ?>
                <?php field_input('stage2_service_deadline_days', 'Prazo de servico etapa 2', $values, 'number', false, 'bi-hourglass'); ?>
                <?php field_input('stage2_final_deadline', 'Prazo final etapa 2', $values, 'date', false, 'bi-calendar2-check'); ?>
                <?php field_input('stage2_status', 'Status etapa 2', $values, 'text', false, 'bi-activity'); ?>
            </div>
            <div class="row g-3 mt-1">
                <?php field_textarea('stage2_final_answer', 'Resposta final etapa 2', $values, 3); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Etapa 3 e acordao</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php field_input('stage3_accord_report', 'Acordao / relatorio', $values, 'text', false, 'bi-file-earmark-richtext'); ?>
                <?php field_input('stage3_accord_report_date', 'Data do acordao / relatorio', $values, 'date', false, 'bi-calendar-date'); ?>
                <?php field_input('stage3_status', 'Status da etapa 3', $values, 'text', false, 'bi-kanban'); ?>
            </div>
        </div>

        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="form-section">
                <h2><?= e($i . 'o monitoramento') ?></h2>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php field_input("monitoring{$i}_start_date", 'Data de inicio', $values, 'date', false, 'bi-play'); ?>
                    <?php field_input("monitoring{$i}_service_deadline_days", 'Prazo de servico (dias)', $values, 'number', false, 'bi-clock-history'); ?>
                    <?php field_input("monitoring{$i}_final_deadline", 'Prazo final', $values, 'date', false, 'bi-calendar-check'); ?>
                    <?php field_input("monitoring{$i}_final_response", 'Resposta final', $values, 'date', false, 'bi-send'); ?>
                    <?php field_input($i === 1 ? "monitoring{$i}_gap_days_from_report" : "monitoring{$i}_gap_days_from_previous", 'Intervalo em dias', $values, 'number', false, 'bi-arrows-collapse'); ?>
                    <?php field_input("monitoring{$i}_next_monitoring_forecast", 'Previsao do proximo monitoramento', $values, 'date', false, 'bi-calendar-plus'); ?>
                    <?php field_input("monitoring{$i}_status", 'Status do monitoramento', $values, 'text', false, 'bi-clipboard2-check'); ?>
                </div>
            </div>
        <?php endfor; ?>

        <div class="form-section">
            <div class="card-head">
                <h2>Itens da auditoria</h2>
                <button class="btn btn-outline-primary btn-sm" type="button" id="add-audit-item"><i class="bi bi-plus-lg"></i> Adicionar item</button>
            </div>
            <div class="table-responsive">
                <table class="table modern-table audit-items-editor align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Codigo</th>
                            <th>Status orgao</th>
                            <th>Status DGBA</th>
                            <th>Status geral</th>
                            <th>Prazo</th>
                            <th>Inicio</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="audit-items-body">
                        <?php foreach ($values['items'] as $index => $item): ?>
                            <tr class="audit-item-row">
                                <td>
                                    <select class="form-select form-select-sm" name="items[<?= (int) $index ?>][item_kind]">
                                        <option value="">Tipo</option>
                                        <option value="DETERMINACAO" <?= selected((string) ($item['item_kind'] ?? ''), 'DETERMINACAO') ?>>Determinacao</option>
                                        <option value="RECOMENDACAO" <?= selected((string) ($item['item_kind'] ?? ''), 'RECOMENDACAO') ?>>Recomendacao</option>
                                        <option value="CIENCIA" <?= selected((string) ($item['item_kind'] ?? ''), 'CIENCIA') ?>>Ciencia</option>
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm" name="items[<?= (int) $index ?>][item_code]" value="<?= e((string) ($item['item_code'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" name="items[<?= (int) $index ?>][control_body_status]" value="<?= e((string) ($item['control_body_status'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" name="items[<?= (int) $index ?>][dgba_status]" value="<?= e((string) ($item['dgba_status'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" name="items[<?= (int) $index ?>][status_geral]" value="<?= e((string) ($item['status_geral'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" type="number" name="items[<?= (int) $index ?>][compliance_deadline_days]" value="<?= e((string) ($item['compliance_deadline_days'] ?? '')) ?>"></td>
                                <td><input class="form-control form-control-sm" type="date" name="items[<?= (int) $index ?>][compliance_start_date]" value="<?= e((string) ($item['compliance_start_date'] ?? '')) ?>"></td>
                                <td class="text-end"><button class="btn btn-sm btn-light text-danger remove-audit-item" type="button"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            <tr class="audit-item-row-detail">
                                <td colspan="8">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <label class="form-label">Descricao do item</label>
                                            <textarea class="form-control" rows="3" name="items[<?= (int) $index ?>][item_description]"><?= e((string) ($item['item_description'] ?? '')) ?></textarea>
                                        </div>
                                        <div class="col-lg-6">
                                            <label class="form-label">Ponto de controle do item</label>
                                            <textarea class="form-control" rows="3" name="items[<?= (int) $index ?>][item_control_point]"><?= e((string) ($item['item_control_point'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <template id="audit-item-template">
                <tr class="audit-item-row">
                    <td>
                        <select class="form-select form-select-sm" data-name="item_kind">
                            <option value="">Tipo</option>
                            <option value="DETERMINACAO">Determinacao</option>
                            <option value="RECOMENDACAO">Recomendacao</option>
                            <option value="CIENCIA">Ciencia</option>
                        </select>
                    </td>
                    <td><input class="form-control form-control-sm" data-name="item_code"></td>
                    <td><input class="form-control form-control-sm" data-name="control_body_status"></td>
                    <td><input class="form-control form-control-sm" data-name="dgba_status"></td>
                    <td><input class="form-control form-control-sm" data-name="status_geral"></td>
                    <td><input class="form-control form-control-sm" type="number" data-name="compliance_deadline_days"></td>
                    <td><input class="form-control form-control-sm" type="date" data-name="compliance_start_date"></td>
                    <td class="text-end"><button class="btn btn-sm btn-light text-danger remove-audit-item" type="button"><i class="bi bi-x-lg"></i></button></td>
                </tr>
                <tr class="audit-item-row-detail">
                    <td colspan="8">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label">Descricao do item</label>
                                <textarea class="form-control" rows="3" data-name="item_description"></textarea>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Ponto de controle do item</label>
                                <textarea class="form-control" rows="3" data-name="item_control_point"></textarea>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>
        </div>

        <div class="form-actions sticky-actions">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar auditoria</button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
