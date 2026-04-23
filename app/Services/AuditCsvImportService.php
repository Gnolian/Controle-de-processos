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
        'has_diligence',
        'last_response_sent_date',
        'preliminary_document',
        'stage2_deadline_days',
        'comments_due_date',
        'stage2_final_response',
        'final_report',
        'stage2_service_deadline_days',
        'stage2_final_deadline',
        'stage2_final_answer',
        'stage2_status',
        'accord_report',
        'accord_report_date',
        'stage3_status',
        'control_point',
        'related_processes',
        'notes',
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
        'stage3_status',
        'monitor_1_start_date',
        'monitor_1_deadline_days',
        'monitor_1_final_deadline',
        'monitor_1_response',
        'monitor_2_start_date',
        'monitor_2_deadline_days',
        'monitor_2_final_deadline',
        'monitor_2_response',
        'monitor_3_start_date',
        'monitor_3_deadline_days',
        'monitor_3_final_deadline',
        'monitor_3_response',
        'monitor_4_start_date',
        'monitor_4_deadline_days',
        'monitor_4_final_deadline',
        'monitor_4_response',
    ];

    public function __construct(private readonly AuditRepository $audits = new AuditRepository())
    {
    }

    public function importNormalizedUploads(string $auditsPath, string $itemsPath, array $user): array
    {
        $auditRows = $this->readAssociativeRows($auditsPath, self::AUDIT_HEADERS);
        $itemRows = $this->readAssociativeRows($itemsPath, self::ITEM_HEADERS);

        if ($auditRows === []) {
            throw new RuntimeException('O CSV tratado de auditorias nao possui linhas validas.');
        }

        $itemsByAudit = [];
        foreach ($itemRows as $row) {
            $auditCode = trim((string) ($row['audit_code'] ?? ''));
            if ($auditCode === '') {
                continue;
            }

            $normalizedItem = $this->mapNormalizedItem($row);
            if ($normalizedItem === null) {
                continue;
            }

            $itemsByAudit[$auditCode][] = $normalizedItem;
        }

        $processed = 0;
        foreach ($auditRows as $row) {
            $audit = $this->mapNormalizedAudit($row);
            if ($audit === null) {
                continue;
            }

            $auditId = $this->audits->upsertAudit($audit, (int) $user['id']);
            $this->audits->replaceItems($auditId, $itemsByAudit[$audit['audit_code']] ?? []);
            $processed++;
        }

        return [
            'audits' => $processed,
            'items' => count($itemRows),
        ];
    }

    private function readAssociativeRows(string $path, array $requiredHeaders): array
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

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headerRow);
        $missing = array_diff($requiredHeaders, $headers);
        if ($missing !== []) {
            fclose($handle);
            throw new RuntimeException('CSV tratado invalido. Colunas ausentes: ' . implode(', ', $missing));
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

    private function mapNormalizedAudit(array $row): ?array
    {
        $auditCode = trim((string) ($row['audit_code'] ?? ''));
        if ($auditCode === '') {
            return null;
        }

        return [
            'audit_code' => $auditCode,
            'audit_nup' => $this->nullableText($row['audit_nup'] ?? ''),
            'audit_year' => $this->parseInt($row['audit_year'] ?? ''),
            'process_status' => $this->nullableText($row['process_status'] ?? ''),
            'requesting_body' => $this->nullableText($row['requesting_body'] ?? ''),
            'audit_type' => $this->nullableText($row['audit_type'] ?? ''),
            'objective' => $this->nullableText($row['objective'] ?? ''),
            'theme' => $this->nullableText($row['theme'] ?? ''),
            'classification' => $this->nullableText($row['classification'] ?? ''),
            'audit_phase' => $this->nullableText($row['audit_phase'] ?? ''),
            'current_owner' => $this->nullableText($row['current_owner'] ?? ''),
            'start_date' => $this->parseDate($row['start_date'] ?? ''),
            'has_diligence' => $this->parseBool($row['has_diligence'] ?? ''),
            'last_response_sent_date' => $this->parseDate($row['last_response_sent_date'] ?? ''),
            'preliminary_document' => $this->nullableText($row['preliminary_document'] ?? ''),
            'stage2_deadline_days' => $this->parseInt($row['stage2_deadline_days'] ?? ''),
            'comments_due_date' => $this->parseDate($row['comments_due_date'] ?? ''),
            'stage2_final_response' => $this->parseDate($row['stage2_final_response'] ?? ''),
            'final_report' => $this->nullableText($row['final_report'] ?? ''),
            'stage2_service_deadline_days' => $this->parseInt($row['stage2_service_deadline_days'] ?? ''),
            'stage2_final_deadline' => $this->parseDate($row['stage2_final_deadline'] ?? ''),
            'stage2_final_answer' => $this->nullableText($row['stage2_final_answer'] ?? ''),
            'stage2_status' => $this->nullableText($row['stage2_status'] ?? ''),
            'accord_report' => $this->nullableText($row['accord_report'] ?? ''),
            'accord_report_date' => $this->parseDate($row['accord_report_date'] ?? ''),
            'stage3_status' => $this->nullableText($row['stage3_status'] ?? ''),
            'control_point' => $this->nullableText($row['control_point'] ?? ''),
            'related_processes' => $this->nullableText($row['related_processes'] ?? ''),
            'notes' => $this->nullableText($row['notes'] ?? ''),
        ];
    }

    private function mapNormalizedItem(array $row): ?array
    {
        $kind = $this->normalizeKind($row['item_kind'] ?? '');
        if ($kind === null) {
            return null;
        }

        return [
            'item_code' => $this->nullableText($row['item_code'] ?? '') ?: $kind,
            'item_kind' => $kind,
            'item_description' => $this->nullableText($row['item_description'] ?? ''),
            'compliance_deadline_days' => $this->parseInt($row['compliance_deadline_days'] ?? ''),
            'compliance_start_date' => $this->parseDate($row['compliance_start_date'] ?? ''),
            'control_body_status' => $this->nullableText($row['control_body_status'] ?? ''),
            'dgba_status' => $this->nullableText($row['dgba_status'] ?? ''),
            'item_control_point' => $this->nullableText($row['item_control_point'] ?? ''),
            'stage3_status' => $this->nullableText($row['stage3_status'] ?? ''),
            'monitor_1_start_date' => $this->parseDate($row['monitor_1_start_date'] ?? ''),
            'monitor_1_deadline_days' => $this->parseInt($row['monitor_1_deadline_days'] ?? ''),
            'monitor_1_final_deadline' => $this->parseDate($row['monitor_1_final_deadline'] ?? ''),
            'monitor_1_response' => $this->nullableText($row['monitor_1_response'] ?? ''),
            'monitor_2_start_date' => $this->parseDate($row['monitor_2_start_date'] ?? ''),
            'monitor_2_deadline_days' => $this->parseInt($row['monitor_2_deadline_days'] ?? ''),
            'monitor_2_final_deadline' => $this->parseDate($row['monitor_2_final_deadline'] ?? ''),
            'monitor_2_response' => $this->nullableText($row['monitor_2_response'] ?? ''),
            'monitor_3_start_date' => $this->parseDate($row['monitor_3_start_date'] ?? ''),
            'monitor_3_deadline_days' => $this->parseInt($row['monitor_3_deadline_days'] ?? ''),
            'monitor_3_final_deadline' => $this->parseDate($row['monitor_3_final_deadline'] ?? ''),
            'monitor_3_response' => $this->nullableText($row['monitor_3_response'] ?? ''),
            'monitor_4_start_date' => $this->parseDate($row['monitor_4_start_date'] ?? ''),
            'monitor_4_deadline_days' => $this->parseInt($row['monitor_4_deadline_days'] ?? ''),
            'monitor_4_final_deadline' => $this->parseDate($row['monitor_4_final_deadline'] ?? ''),
            'monitor_4_response' => $this->nullableText($row['monitor_4_response'] ?? ''),
        ];
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value));
    }

    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || in_array($this->normalizeToken($value), ['N/A', 'NAO INFORMADO'], true)) {
            return null;
        }

        return $value;
    }

    private function fixText(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || in_array($this->normalizeToken($value), ['N/A', 'NAO'], true)) {
            return null;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            $date = \DateTime::createFromFormat('d/m/Y', $value);
            return $date ? $date->format('Y-m-d') : null;
        }

        return null;
    }

    private function parseInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || in_array($this->normalizeToken($value), ['N/A', 'NAO'], true)) {
            return null;
        }

        return preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    private function parseBool(string $value): int
    {
        return in_array($this->normalizeToken($value), ['SIM', 'S'], true) ? 1 : 0;
    }

    private function normalizeKind(string $value): ?string
    {
        return match ($this->normalizeToken($value)) {
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
        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? $value;
    }
}
