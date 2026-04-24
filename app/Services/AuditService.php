<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use RuntimeException;

class AuditService
{
    public function __construct(private readonly AuditRepository $audits = new AuditRepository())
    {
    }

    public function save(array $data, array $user, ?int $id = null): int
    {
        $payload = $this->buildAuditPayload($data);
        $this->validateAudit($payload, $id);
        $items = $this->buildItemsPayload($data['items'] ?? []);

        \db()->beginTransaction();
        try {
            if ($id === null) {
                $auditId = $this->audits->createAudit($payload, (int) $user['id']);
            } else {
                $audit = $this->audits->find($id);
                if (!$audit) {
                    throw new RuntimeException('Auditoria nao encontrada.');
                }

                $this->audits->updateAudit($id, $payload, (int) $user['id']);
                $auditId = $id;
            }

            $this->audits->replaceItems($auditId, $items);
            \db()->commit();
        } catch (\Throwable $exception) {
            \db()->rollBack();
            throw $exception;
        }

        return $auditId;
    }

    public function buildAuditPayload(array $data): array
    {
        $payload = [];
        foreach (AuditRepository::AUDIT_COLUMNS as $column) {
            $payload[$column] = trim((string) ($data[$column] ?? ''));
        }

        foreach ($this->dateColumns() as $column) {
            $payload[$column] = $this->parseDate($payload[$column]);
        }

        foreach ($this->intColumns() as $column) {
            $payload[$column] = $this->parseInt($payload[$column]);
        }

        foreach (['deadline_is_current', 'flag_estimated', 'has_diligence', 'flag_stage2_diligence'] as $column) {
            $payload[$column] = $this->parseBool($data[$column] ?? '');
        }

        $deadline = trim((string) ($data['deadline_label'] ?? ''));
        $payload['deadline_label'] = $deadline !== '' ? $deadline : null;
        $payload['deadline_is_current'] = $deadline !== '' && $this->normalizeToken($deadline) === 'DATAATUAL' ? 1 : $payload['deadline_is_current'];
        $payload['deadline_date'] = $payload['deadline_is_current'] ? null : ($payload['deadline_date'] ?: $this->parseDate($deadline));

        $payload['audit_year'] = $payload['audit_year'] ? max(2000, min(2100, (int) $payload['audit_year'])) : null;
        $payload['stage3_accord_report'] = $payload['stage3_accord_report'] ?: ($payload['accord_report'] ?: null);
        $payload['stage3_accord_report_date'] = $payload['stage3_accord_report_date'] ?: ($payload['accord_report_date'] ?: null);
        $payload['control_summary'] = $payload['control_summary'] ?: ($payload['related_processes'] ?: null);

        foreach (AuditRepository::AUDIT_COLUMNS as $column) {
            if ($payload[$column] === '') {
                $payload[$column] = null;
            }
        }

        return $payload;
    }

    public function buildItemsPayload(array $items): array
    {
        $payload = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = [];
            foreach (AuditRepository::ITEM_COLUMNS as $column) {
                $row[$column] = trim((string) ($item[$column] ?? ''));
            }

            if (($row['item_kind'] ?? '') === '' && ($row['item_description'] ?? '') === '' && ($row['item_control_point'] ?? '') === '') {
                continue;
            }

            $row['item_kind'] = $this->normalizeItemKind($row['item_kind']);
            if ($row['item_kind'] === null) {
                throw new RuntimeException('Selecione um tipo valido para cada item da auditoria.');
            }

            foreach ($this->itemDateColumns() as $column) {
                $row[$column] = $this->parseDate($row[$column]);
            }

            foreach ($this->itemIntColumns() as $column) {
                $row[$column] = $this->parseInt($row[$column]);
            }

            foreach (AuditRepository::ITEM_COLUMNS as $column) {
                if ($row[$column] === '') {
                    $row[$column] = null;
                }
            }

            $row['item_code'] = $row['item_code'] ?: $row['item_kind'];
            $payload[] = $row;
        }

        return $payload;
    }

    private function validateAudit(array $payload, ?int $id = null): void
    {
        foreach (['audit_code', 'audit_nup', 'requesting_body', 'audit_type', 'audit_phase'] as $field) {
            if (($payload[$field] ?? null) === null) {
                throw new RuntimeException('Preencha os campos principais da auditoria.');
            }
        }

        $existing = $this->audits->findByCode((string) $payload['audit_code']);
        if ($existing && (int) $existing['id'] !== (int) $id) {
            throw new RuntimeException('Ja existe uma auditoria com este codigo.');
        }
    }

    private function dateColumns(): array
    {
        return [
            'start_date',
            'last_date_response',
            'deadline_date',
            'last_response_sent_date_diligence',
            'stage2_start_date',
            'stage2_date_last_response_diligence',
            'last_response_sent_date',
            'comments_due_date',
            'stage2_final_response',
            'stage2_final_deadline',
            'stage3_accord_report_date',
            'accord_report_date',
            'monitoring1_start_date',
            'monitoring1_final_deadline',
            'monitoring1_final_response',
            'monitoring1_next_monitoring_forecast',
            'monitoring2_start_date',
            'monitoring2_final_deadline',
            'monitoring2_final_response',
            'monitoring2_next_monitoring_forecast',
            'monitoring3_start_date',
            'monitoring3_final_deadline',
            'monitoring3_final_response',
            'monitoring3_next_monitoring_forecast',
            'monitoring4_start_date',
            'monitoring4_final_deadline',
            'monitoring4_final_response',
            'monitoring4_next_monitoring_forecast',
        ];
    }

    private function intColumns(): array
    {
        return [
            'audit_year',
            'stage2_deadline_days',
            'stage2_service_deadline_days',
            'monitoring1_service_deadline_days',
            'monitoring1_gap_days_from_report',
            'monitoring2_service_deadline_days',
            'monitoring2_gap_days_from_previous',
            'monitoring3_service_deadline_days',
            'monitoring3_gap_days_from_previous',
            'monitoring4_service_deadline_days',
            'monitoring4_gap_days_from_previous',
        ];
    }

    private function itemDateColumns(): array
    {
        return [
            'compliance_start_date',
            'monitor_1_start_date',
            'monitor_1_final_deadline',
            'monitor_2_start_date',
            'monitor_2_final_deadline',
            'monitor_3_start_date',
            'monitor_3_final_deadline',
            'monitor_4_start_date',
            'monitor_4_final_deadline',
        ];
    }

    private function itemIntColumns(): array
    {
        return [
            'compliance_deadline_days',
            'monitor_1_deadline_days',
            'monitor_2_deadline_days',
            'monitor_3_deadline_days',
            'monitor_4_deadline_days',
        ];
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || in_array($this->normalizeToken($value), ['NA', 'N/A', 'NAO', 'DATAATUAL'], true)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            $date = \DateTime::createFromFormat('d/m/Y', $value);
            return $date ? $date->format('Y-m-d') : null;
        }

        return null;
    }

    private function parseInt(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || in_array($this->normalizeToken($value), ['NA', 'N/A', 'NAO'], true)) {
            return null;
        }

        return preg_match('/^-?\d+$/', $value) ? (int) $value : null;
    }

    private function parseBool(mixed $value): int
    {
        return in_array($this->normalizeToken((string) $value), ['SIM', 'S', '1', 'TRUE'], true) ? 1 : 0;
    }

    private function normalizeItemKind(?string $value): ?string
    {
        return match ($this->normalizeToken((string) $value)) {
            'DETERMINACAO' => 'DETERMINACAO',
            'RECOMENDACAO' => 'RECOMENDACAO',
            'CIENCIA' => 'CIENCIA',
            default => null,
        };
    }

    private function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $normalized !== false ? $normalized : $value;
        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    }
}
