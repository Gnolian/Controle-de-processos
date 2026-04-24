<?php

use App\Repositories\ProcessRepository;

function status_badge(string $status): string
{
    $class = match ($status) {
        'Aberto' => 'text-bg-primary',
        'Arquivar' => 'text-bg-warning',
        'Arquivado (concluido)' => 'text-bg-success',
        default => 'text-bg-secondary',
    };

    return '<span class="badge rounded-pill ' . $class . '">' . e($status) . '</span>';
}

function workflow_badge(?string $status): string
{
    $class = match ($status) {
        'Concluido' => 'text-bg-success',
        'Em andamento' => 'text-bg-info',
        'A iniciar' => 'text-bg-warning',
        'N/A' => 'text-bg-secondary',
        default => 'text-bg-light',
    };

    return '<span class="badge rounded-pill ' . $class . '">' . e($status ?: '-') . '</span>';
}

function deadline_badge(array $process): string
{
    if (($process['deadline_type'] ?? 'data') === 'tempo_habil') {
        return '<span class="badge rounded-pill text-bg-secondary"><i class="bi bi-briefcase"></i> Tempo Habil</span>';
    }

    $deadline = $process['external_deadline_mds'] ?: ($process['adjusted_internal_deadline'] ?: $process['internal_deadline_gab']);
    if (!$deadline) {
        return '<span class="badge rounded-pill text-bg-secondary"><i class="bi bi-briefcase"></i> Tempo Habil</span>';
    }

    $today = strtotime(date('Y-m-d'));
    $time = strtotime($deadline);
    $diff = (int) floor(($time - $today) / 86400);

    if (($process['status'] ?? '') === 'Aberto' && $diff < 0) {
        return '<span class="badge rounded-pill text-bg-danger"><i class="bi bi-exclamation-triangle"></i> ' . e(format_date($deadline)) . '</span>';
    }

    if (($process['status'] ?? '') === 'Aberto' && $diff <= 7) {
        return '<span class="badge rounded-pill text-bg-warning"><i class="bi bi-hourglass-split"></i> ' . e(format_date($deadline)) . '</span>';
    }

    return '<span class="badge rounded-pill text-bg-success"><i class="bi bi-check2-circle"></i> ' . e(format_date($deadline)) . '</span>';
}

function process_defaults(?array $process = null): array
{
    $defaults = array_fill_keys(ProcessRepository::COLUMNS, '');
    $defaults['updated_at'] = date('Y-m-d');
    $defaults['deadline_type'] = 'data';
    $defaults['response_status'] = 'A iniciar';
    $defaults['andrea_review_status'] = 'N/A';
    $defaults['signed_status'] = 'N/A';
    $defaults['sent_gab_status'] = 'N/A';
    $defaults['status'] = 'Aberto';

    return array_merge($defaults, $process ?: []);
}

function field_input(string $name, string $label, array $values, string $type = 'text', bool $required = false, string $icon = 'bi-pencil'): void
{
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi <?= e($icon) ?>"></i></span>
            <input class="form-control" id="<?= e($name) ?>" type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e((string) $values[$name]) ?>" <?= $required ? 'required' : '' ?>>
        </div>
    </div>
    <?php
}

function field_select_assoc(string $name, string $label, array $options, array $values, string $icon = 'bi-list-check', bool $required = false): void
{
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi <?= e($icon) ?>"></i></span>
            <select class="form-select" id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                <?php foreach ($options as $value => $labelText): ?>
                    <option value="<?= e($value) ?>" <?= selected((string) ($values[$name] ?? ''), (string) $value) ?>><?= e($labelText) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
}

function field_textarea(string $name, string $label, array $values, int $rows = 4, bool $required = false): void
{
    ?>
    <div class="col-12">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <textarea class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= $rows ?>" <?= $required ? 'required' : '' ?>><?= e((string) $values[$name]) ?></textarea>
    </div>
    <?php
}

function field_select(string $name, string $label, array $options, array $values, string $icon = 'bi-list-check'): void
{
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi <?= e($icon) ?>"></i></span>
            <select class="form-select" id="<?= e($name) ?>" name="<?= e($name) ?>">
                <?php foreach ($options as $option): ?>
                    <option value="<?= e($option) ?>" <?= selected((string) $values[$name], $option) ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
}

function field_checkbox(string $name, string $label, array $values, string $help = ''): void
{
    $checked = !empty($values[$name]);
    ?>
    <div class="col">
        <label class="form-label d-block"><?= e($label) ?></label>
        <div class="form-check form-switch border rounded-3 px-3 py-2 bg-light-subtle">
            <input class="form-check-input" type="checkbox" role="switch" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
            <label class="form-check-label ms-2" for="<?= e($name) ?>"><?= $checked ? 'Sim' : 'Nao' ?></label>
            <?php if ($help !== ''): ?>
                <small class="d-block text-secondary mt-1"><?= e($help) ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function audit_item_kind_label(?string $kind): string
{
    return match ($kind) {
        'DETERMINACAO', 'DETERMINAÇÃO' => 'Determinacao',
        'RECOMENDACAO', 'RECOMENDAÇÃO' => 'Recomendacao',
        'CIENCIA', 'CIÊNCIA' => 'Ciencia',
        default => (string) ($kind ?: '-'),
    };
}
