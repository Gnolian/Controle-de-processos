<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use RuntimeException;

class AuditCsvImportService
{
    private const AUDIT_HEADERS = [
        'audit_code',
        'audit_nup',
        'audit_year',
        'process_status',
        'requesting_body',
        'audit_type',
        'objective',
        'theme',
        'classification',
        'audit_phase',
        'current_owner',
        'start_date',
        'last_date_response',
        'deadline',
        'flag_estimated',
        'has_diligence',
        'last_response_sent_date_diligence',
        'H1',
        'stage2_start_date',
        'flag_stage2_diligence',
        'stage2_date_last_response_diligence',
        'stage2_preliminary_document',
        'stage2_deadline_days',
        'stage2_comments_due_date',
        'stage2_final_response',
        'stage2_final_report',
        'stage2_service_deadline_days',
        'stage2_final_deadline',
        'stage2_final_answer',
        'stage2_status',
        'stage3_accord_report',
        'stage3_accord_report_date',
        'stage3_status',
        'monitoring1_start_date',
        'monitoring1_service_deadline_days',
        'monitoring1_final_deadline',
        'monitoring1_final_response',
        'monitoring1_gap_days_from_report',
        'monitoring1_next_monitoring_forecast',
        'monitoring1_status',
        'monitoring2_start_date',
        'monitoring2_service_deadline_days',
        'monitoring2_final_deadline',
        'monitoring2_final_response',
        'monitoring2_gap_days_from_previous',
        'monitoring2_next_monitoring_forecast',
        'monitoring2_status',
        'monitoring3_start_date',
        'monitoring3_service_deadline_days',
        'monitoring3_final_deadline',
        'monitoring3_final_response',
        'monitoring3_gap_days_from_previous',
        'monitoring3_next_monitoring_forecast',
        'monitoring3_status',
        'monitoring4_start_date',
        'monitoring4_service_deadline_days',
        'monitoring4_final_deadline',
        'monitoring4_final_response',
        'monitoring4_gap_days_from_previous',
        'monitoring4_next_monitoring_forecast',
        'related_processes',
        'H2',
    ];

    private const ITEM_HEADERS = [
        'audit_code',
        'item_kind',
        'item_code',
        'item_description',
        'compliance_deadline_days',
        'compliance_start_date',
        'control_body_status',
        'dgba_status',
        'item_control_point',
        'status_geral',
    ];

    public function __construct(
        private readonly AuditService $service = new AuditService(),
        private readonly AuditRepository $repository = new AuditRepository(),
    )
    {
    }

    public function importNormalizedUploads(string $auditsPath, string $itemsPath, array $user): array
    {
        $auditRows = $this->readRows($auditsPath, self::AUDIT_HEADERS);
        $itemRows = $this->readRows($itemsPath, self::ITEM_HEADERS);

        if ($auditRows === []) {
            throw new RuntimeException('O CSV tratado de auditorias nao possui linhas validas.');
        }

        $itemsByAudit = [];
        foreach ($itemRows as $row) {
            $auditCode = trim((string) ($row['audit_code'] ?? ''));
            if ($auditCode === '') {
                continue;
            }

            $itemsByAudit[$auditCode][] = [
                'item_kind' => $row['item_kind'] ?? '',
                'item_code' => $row['item_code'] ?? '',
                'item_description' => $row['item_description'] ?? '',
                'compliance_deadline_days' => $row['compliance_deadline_days'] ?? '',
                'compliance_start_date' => $row['compliance_start_date'] ?? '',
                'control_body_status' => $row['control_body_status'] ?? '',
                'dgba_status' => $row['dgba_status'] ?? '',
                'item_control_point' => $row['item_control_point'] ?? '',
                'status_geral' => $row['status_geral'] ?? '',
            ];
        }

        $processed = 0;
        foreach ($auditRows as $row) {
            if (trim((string) ($row['audit_code'] ?? '')) === '') {
                continue;
            }

            $payload = [
                'audit_code' => $row['audit_code'] ?? '',
                'audit_nup' => $row['audit_nup'] ?? '',
                'audit_year' => $row['audit_year'] ?? '',
                'process_status' => $row['process_status'] ?? '',
                'requesting_body' => $row['requesting_body'] ?? '',
                'audit_type' => $row['audit_type'] ?? '',
                'objective' => $row['objective'] ?? '',
                'theme' => $row['theme'] ?? '',
                'classification' => $row['classification'] ?? '',
                'audit_phase' => $row['audit_phase'] ?? '',
                'current_owner' => $row['current_owner'] ?? '',
                'start_date' => $row['start_date'] ?? '',
                'last_date_response' => $row['last_date_response'] ?? '',
                'deadline_label' => $row['deadline'] ?? '',
                'flag_estimated' => $row['flag_estimated'] ?? '',
                'has_diligence' => $row['has_diligence'] ?? '',
                'last_response_sent_date_diligence' => $row['last_response_sent_date_diligence'] ?? '',
                'stage2_start_date' => $row['stage2_start_date'] ?? '',
                'flag_stage2_diligence' => $row['flag_stage2_diligence'] ?? '',
                'stage2_date_last_response_diligence' => $row['stage2_date_last_response_diligence'] ?? '',
                'stage2_preliminary_document' => $row['stage2_preliminary_document'] ?? '',
                'stage2_deadline_days' => $row['stage2_deadline_days'] ?? '',
                'comments_due_date' => $row['stage2_comments_due_date'] ?? '',
                'stage2_final_response' => $row['stage2_final_response'] ?? '',
                'stage2_final_report' => $row['stage2_final_report'] ?? '',
                'stage2_service_deadline_days' => $row['stage2_service_deadline_days'] ?? '',
                'stage2_final_deadline' => $row['stage2_final_deadline'] ?? '',
                'stage2_final_answer' => $row['stage2_final_answer'] ?? '',
                'stage2_status' => $row['stage2_status'] ?? '',
                'stage3_accord_report' => $row['stage3_accord_report'] ?? '',
                'stage3_accord_report_date' => $row['stage3_accord_report_date'] ?? '',
                'stage3_status' => $row['stage3_status'] ?? '',
                'monitoring1_start_date' => $row['monitoring1_start_date'] ?? '',
                'monitoring1_service_deadline_days' => $row['monitoring1_service_deadline_days'] ?? '',
                'monitoring1_final_deadline' => $row['monitoring1_final_deadline'] ?? '',
                'monitoring1_final_response' => $row['monitoring1_final_response'] ?? '',
                'monitoring1_gap_days_from_report' => $row['monitoring1_gap_days_from_report'] ?? '',
                'monitoring1_next_monitoring_forecast' => $row['monitoring1_next_monitoring_forecast'] ?? '',
                'monitoring1_status' => $row['monitoring1_status'] ?? '',
                'monitoring2_start_date' => $row['monitoring2_start_date'] ?? '',
                'monitoring2_service_deadline_days' => $row['monitoring2_service_deadline_days'] ?? '',
                'monitoring2_final_deadline' => $row['monitoring2_final_deadline'] ?? '',
                'monitoring2_final_response' => $row['monitoring2_final_response'] ?? '',
                'monitoring2_gap_days_from_previous' => $row['monitoring2_gap_days_from_previous'] ?? '',
                'monitoring2_next_monitoring_forecast' => $row['monitoring2_next_monitoring_forecast'] ?? '',
                'monitoring2_status' => $row['monitoring2_status'] ?? '',
                'monitoring3_start_date' => $row['monitoring3_start_date'] ?? '',
                'monitoring3_service_deadline_days' => $row['monitoring3_service_deadline_days'] ?? '',
                'monitoring3_final_deadline' => $row['monitoring3_final_deadline'] ?? '',
                'monitoring3_final_response' => $row['monitoring3_final_response'] ?? '',
                'monitoring3_gap_days_from_previous' => $row['monitoring3_gap_days_from_previous'] ?? '',
                'monitoring3_next_monitoring_forecast' => $row['monitoring3_next_monitoring_forecast'] ?? '',
                'monitoring3_status' => $row['monitoring3_status'] ?? '',
                'monitoring4_start_date' => $row['monitoring4_start_date'] ?? '',
                'monitoring4_service_deadline_days' => $row['monitoring4_service_deadline_days'] ?? '',
                'monitoring4_final_deadline' => $row['monitoring4_final_deadline'] ?? '',
                'monitoring4_final_response' => $row['monitoring4_final_response'] ?? '',
                'monitoring4_gap_days_from_previous' => $row['monitoring4_gap_days_from_previous'] ?? '',
                'monitoring4_next_monitoring_forecast' => $row['monitoring4_next_monitoring_forecast'] ?? '',
                'monitoring4_status' => $row['monitoring4_status'] ?? '',
                'related_processes' => $row['related_processes'] ?? '',
                'control_summary' => $row['control_summary'] ?? ($row['notes'] ?? ($row['H2'] ?: ($row['related_processes'] ?? ''))),
            ];

            $existing = $this->repository->findByCode((string) $payload['audit_code']);
            $this->service->save($payload + ['items' => $itemsByAudit[$payload['audit_code']] ?? []], $user, $existing ? (int) $existing['id'] : null);
            $processed++;
        }

        return [
            'audits' => $processed,
            'items' => count($itemRows),
        ];
    }

    private function readRows(string $path, array $defaultHeaders): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo CSV nao encontrado.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o CSV.');
        }

        $headerRow = fgetcsv($handle, 0, ';');
        if ($headerRow === false) {
            fclose($handle);
            throw new RuntimeException('CSV vazio ou invalido.');
        }

        $headers = [];
        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)));
            $headers[] = $normalized !== '' ? $normalized : ($defaultHeaders[$index] ?? 'col_' . $index);
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
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
