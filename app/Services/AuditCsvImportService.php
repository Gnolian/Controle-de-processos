<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use RuntimeException;

class AuditCsvImportService
{
    public function __construct(
        private readonly AuditService $service = new AuditService(),
        private readonly AuditRepository $repository = new AuditRepository(),
    ) {
    }

    public function importNormalizedUploads(string $auditsPath, string $itemsPath, array $user): array
    {
        $auditRows = $this->readRows($auditsPath);
        $itemRows = $this->readRows($itemsPath);

        if ($auditRows === []) {
            throw new RuntimeException('O CSV tratado de auditorias não possui linhas válidas.');
        }

        $itemsByAudit = [];
        $validItemCount = 0;
        foreach ($itemRows as $row) {
            $auditCode = trim((string) $this->value($row, ['audit_code']));
            if ($auditCode === '') {
                continue;
            }

            if ($this->skipItemRow($row)) {
                continue;
            }

            $validItemCount++;

            $itemsByAudit[$auditCode][] = [
                'item_kind' => $this->value($row, ['item_kind']),
                'item_code' => $this->value($row, ['item_code']),
                'item_description' => $this->value($row, ['item_description']),
                'compliance_deadline_days' => $this->value($row, ['compliance_deadline_days']),
                'compliance_start_date' => $this->value($row, ['compliance_start_date']),
                'control_body_status' => $this->value($row, ['control_body_status']),
                'dgba_status' => $this->value($row, ['dgba_status']),
                'item_control_point' => $this->value($row, ['item_control_point']),
                'status_geral' => $this->value($row, ['status_geral']),
                'stage3_status' => $this->value($row, ['stage3_status']),
                'monitor_1_start_date' => $this->value($row, ['monitor_1_start_date']),
                'monitor_1_deadline_days' => $this->value($row, ['monitor_1_deadline_days']),
                'monitor_1_final_deadline' => $this->value($row, ['monitor_1_final_deadline']),
                'monitor_1_response' => $this->value($row, ['monitor_1_response']),
                'monitor_2_start_date' => $this->value($row, ['monitor_2_start_date']),
                'monitor_2_deadline_days' => $this->value($row, ['monitor_2_deadline_days']),
                'monitor_2_final_deadline' => $this->value($row, ['monitor_2_final_deadline']),
                'monitor_2_response' => $this->value($row, ['monitor_2_response']),
                'monitor_3_start_date' => $this->value($row, ['monitor_3_start_date']),
                'monitor_3_deadline_days' => $this->value($row, ['monitor_3_deadline_days']),
                'monitor_3_final_deadline' => $this->value($row, ['monitor_3_final_deadline']),
                'monitor_3_response' => $this->value($row, ['monitor_3_response']),
                'monitor_4_start_date' => $this->value($row, ['monitor_4_start_date']),
                'monitor_4_deadline_days' => $this->value($row, ['monitor_4_deadline_days']),
                'monitor_4_final_deadline' => $this->value($row, ['monitor_4_final_deadline']),
                'monitor_4_response' => $this->value($row, ['monitor_4_response']),
            ];
        }

        $processed = 0;
        foreach ($auditRows as $row) {
            $auditCode = trim((string) $this->value($row, ['audit_code']));
            if ($auditCode === '') {
                continue;
            }

            [$deadlineLabel, $deadlineDate, $deadlineIsCurrent] = $this->resolveDeadline($row);

            $payload = [
                'audit_code' => $auditCode,
                'audit_nup' => $this->value($row, ['audit_nup']),
                'audit_year' => $this->value($row, ['audit_year']),
                'process_status' => $this->value($row, ['process_status']),
                'requesting_body' => $this->value($row, ['requesting_body']),
                'audit_type' => $this->value($row, ['audit_type']),
                'objective' => $this->value($row, ['objective']),
                'theme' => $this->value($row, ['theme']),
                'classification' => $this->value($row, ['classification']),
                'audit_phase' => $this->value($row, ['audit_phase']),
                'current_owner' => $this->value($row, ['current_owner']),
                'start_date' => $this->value($row, ['start_date']),
                'last_date_response' => $this->value($row, ['last_date_response', 'last_response_sent_date']),
                'deadline_label' => $deadlineLabel,
                'deadline_date' => $deadlineDate,
                'deadline_is_current' => $deadlineIsCurrent,
                'flag_estimated' => $this->value($row, ['flag_estimated']),
                'has_diligence' => $this->value($row, ['has_diligence']),
                'last_response_sent_date_diligence' => $this->value($row, ['last_response_sent_date_diligence', 'last_response_sent_date']),
                'stage2_start_date' => $this->value($row, ['stage2_start_date']),
                'flag_stage2_diligence' => $this->value($row, ['flag_stage2_diligence']),
                'stage2_date_last_response_diligence' => $this->value($row, ['stage2_date_last_response_diligence']),
                'stage2_preliminary_document' => $this->value($row, ['stage2_preliminary_document', 'preliminary_document']),
                'last_response_sent_date' => $this->value($row, ['last_response_sent_date']),
                'preliminary_document' => $this->value($row, ['preliminary_document']),
                'stage2_deadline_days' => $this->value($row, ['stage2_deadline_days']),
                'comments_due_date' => $this->value($row, ['comments_due_date', 'stage2_comments_due_date']),
                'stage2_final_response' => $this->value($row, ['stage2_final_response']),
                'stage2_final_report' => $this->value($row, ['stage2_final_report', 'final_report']),
                'final_report' => $this->value($row, ['final_report']),
                'stage2_service_deadline_days' => $this->value($row, ['stage2_service_deadline_days']),
                'stage2_final_deadline' => $this->value($row, ['stage2_final_deadline']),
                'stage2_final_answer' => $this->value($row, ['stage2_final_answer']),
                'stage2_status' => $this->value($row, ['stage2_status']),
                'stage3_accord_report' => $this->value($row, ['stage3_accord_report', 'accord_report']),
                'stage3_accord_report_date' => $this->value($row, ['stage3_accord_report_date', 'accord_report_date']),
                'accord_report' => $this->value($row, ['accord_report']),
                'accord_report_date' => $this->value($row, ['accord_report_date']),
                'stage3_status' => $this->value($row, ['stage3_status']),
                'monitoring1_start_date' => $this->value($row, ['monitoring1_start_date']),
                'monitoring1_service_deadline_days' => $this->value($row, ['monitoring1_service_deadline_days']),
                'monitoring1_final_deadline' => $this->value($row, ['monitoring1_final_deadline']),
                'monitoring1_final_response' => $this->value($row, ['monitoring1_final_response']),
                'monitoring1_gap_days_from_report' => $this->value($row, ['monitoring1_gap_days_from_report']),
                'monitoring1_next_monitoring_forecast' => $this->value($row, ['monitoring1_next_monitoring_forecast']),
                'monitoring1_status' => $this->value($row, ['monitoring1_status']),
                'monitoring2_start_date' => $this->value($row, ['monitoring2_start_date']),
                'monitoring2_service_deadline_days' => $this->value($row, ['monitoring2_service_deadline_days']),
                'monitoring2_final_deadline' => $this->value($row, ['monitoring2_final_deadline']),
                'monitoring2_final_response' => $this->value($row, ['monitoring2_final_response']),
                'monitoring2_gap_days_from_previous' => $this->value($row, ['monitoring2_gap_days_from_previous']),
                'monitoring2_next_monitoring_forecast' => $this->value($row, ['monitoring2_next_monitoring_forecast']),
                'monitoring2_status' => $this->value($row, ['monitoring2_status']),
                'monitoring3_start_date' => $this->value($row, ['monitoring3_start_date']),
                'monitoring3_service_deadline_days' => $this->value($row, ['monitoring3_service_deadline_days']),
                'monitoring3_final_deadline' => $this->value($row, ['monitoring3_final_deadline']),
                'monitoring3_final_response' => $this->value($row, ['monitoring3_final_response']),
                'monitoring3_gap_days_from_previous' => $this->value($row, ['monitoring3_gap_days_from_previous']),
                'monitoring3_next_monitoring_forecast' => $this->value($row, ['monitoring3_next_monitoring_forecast']),
                'monitoring3_status' => $this->value($row, ['monitoring3_status']),
                'monitoring4_start_date' => $this->value($row, ['monitoring4_start_date']),
                'monitoring4_service_deadline_days' => $this->value($row, ['monitoring4_service_deadline_days']),
                'monitoring4_final_deadline' => $this->value($row, ['monitoring4_final_deadline']),
                'monitoring4_final_response' => $this->value($row, ['monitoring4_final_response']),
                'monitoring4_gap_days_from_previous' => $this->value($row, ['monitoring4_gap_days_from_previous']),
                'monitoring4_next_monitoring_forecast' => $this->value($row, ['monitoring4_next_monitoring_forecast']),
                'monitoring4_status' => $this->value($row, ['monitoring4_status']),
                'control_point' => $this->value($row, ['control_point']),
                'related_processes' => $this->value($row, ['related_processes']),
                'control_summary' => $this->value($row, ['control_summary', 'control_point', 'notes', 'related_processes']),
                'notes' => $this->value($row, ['notes']),
            ];

            $existing = $this->repository->findByCode($auditCode);
            $this->service->save($payload + ['items' => $itemsByAudit[$auditCode] ?? []], $user, $existing ? (int) $existing['id'] : null);
            $processed++;
        }

        return [
            'audits' => $processed,
            'items' => $validItemCount,
        ];
    }

    private function readRows(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo CSV não encontrado.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o CSV.');
        }

        $headerRow = fgetcsv($handle, 0, ';');
        if ($headerRow === false) {
            fclose($handle);
            throw new RuntimeException('CSV vazio ou inválido.');
        }

        $headers = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            $headers[] = $normalized !== '' ? $normalized : 'col_' . $index;
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $this->fixText((string) ($row[$index] ?? ''));
            }

            if ($this->rowEmpty($assoc)) {
                continue;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    private function resolveDeadline(array $row): array
    {
        $explicit = trim((string) $this->value($row, ['deadline_label', 'deadline']));
        if ($explicit !== '') {
            $token = $this->normalizeToken($explicit);
            if ($token === 'DATAATUAL') {
                return ['DATA ATUAL', null, 1];
            }

            return [$explicit, $explicit, 0];
        }

        foreach ([
            'stage2_final_deadline',
            'monitoring1_final_deadline',
            'monitoring2_final_deadline',
            'monitoring3_final_deadline',
            'monitoring4_final_deadline',
            'comments_due_date',
        ] as $field) {
            $candidate = trim((string) $this->value($row, [$field]));
            if ($candidate !== '') {
                return [$candidate, $candidate, 0];
            }
        }

        return [null, null, 0];
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (!array_key_exists($normalized, $row)) {
                continue;
            }

            $value = trim((string) $row[$normalized]);
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row)) {
                return trim((string) $row[$normalized]);
            }
        }

        return null;
    }

    private function skipItemRow(array $row): bool
    {
        $kind = trim((string) $this->value($row, ['item_kind']));
        $code = trim((string) $this->value($row, ['item_code']));
        $description = trim((string) $this->value($row, ['item_description']));
        $controlPoint = trim((string) $this->value($row, ['item_control_point']));
        $controlStatus = trim((string) $this->value($row, ['control_body_status']));
        $dgbaStatus = trim((string) $this->value($row, ['dgba_status']));
        $deadline = trim((string) $this->value($row, ['compliance_deadline_days']));
        $startDate = trim((string) $this->value($row, ['compliance_start_date']));

        return $kind === ''
            && $code === ''
            && $description === ''
            && $controlPoint === ''
            && $controlStatus === ''
            && $dgbaStatus === ''
            && $deadline === ''
            && $startDate === '';
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = trim(mb_strtolower($header, 'UTF-8'));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        $header = $normalized !== false ? $normalized : $header;
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        return trim($header, '_');
    }

    private function rowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function fixText(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }

    private function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $normalized !== false ? $normalized : $value;
        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    }
}
