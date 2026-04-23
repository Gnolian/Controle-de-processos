<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use RuntimeException;

class AuditCsvImportService
{
    public function __construct(private readonly AuditRepository $audits = new AuditRepository())
    {
    }

    public function importUploaded(string $path, array $user): array
    {
        $rows = $this->readRows($path);
        if (count($rows) < 3) {
            throw new RuntimeException('CSV de auditorias invalido.');
        }

        $dataRows = array_slice($rows, 2);
        $currentAudit = null;
        $buffer = [];
        $count = 0;

        foreach ($dataRows as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }

            if ($this->cell($row, 0) !== '') {
                if ($currentAudit !== null) {
                    $this->persistAudit($currentAudit, $buffer, (int) $user['id']);
                    $count++;
                }
                $currentAudit = $this->mapAudit($row);
                $buffer = [];
            }

            if ($currentAudit !== null && $this->cell($row, 25) !== '' && $this->normalizeKind($this->cell($row, 25)) !== 'N/A') {
                $buffer[] = $this->mapItem($row);
            }
        }

        if ($currentAudit !== null) {
            $this->persistAudit($currentAudit, $buffer, (int) $user['id']);
            $count++;
        }

        return ['audits' => $count];
    }

    private function persistAudit(array $audit, array $items, int $userId): void
    {
        $auditId = $this->audits->upsertAudit($audit, $userId);
        $this->audits->replaceItems($auditId, $items);
    }

    private function readRows(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo CSV nao encontrado.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Nao foi possivel ler o CSV.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ';')) !== false) {
            $rows[] = array_map(fn ($value) => $this->fixText((string) $value), $row);
        }
        fclose($stream);

        return $rows;
    }

    private function mapAudit(array $row): array
    {
        return [
            'audit_code' => $this->cell($row, 0),
            'audit_nup' => $this->cell($row, 1),
            'audit_year' => $this->parseInt($this->cell($row, 2)),
            'process_status' => $this->cell($row, 3),
            'requesting_body' => $this->cell($row, 4),
            'audit_type' => $this->cell($row, 5),
            'objective' => $this->cell($row, 6),
            'theme' => $this->cell($row, 7),
            'classification' => $this->cell($row, 8),
            'audit_phase' => $this->cell($row, 9),
            'current_owner' => $this->cell($row, 10),
            'start_date' => $this->parseDate($this->cell($row, 11)),
            'has_diligence' => $this->parseBool($this->cell($row, 12)),
            'last_response_sent_date' => $this->parseDate($this->cell($row, 13)),
            'preliminary_document' => $this->cell($row, 14),
            'stage2_deadline_days' => $this->parseInt($this->cell($row, 15)),
            'comments_due_date' => $this->parseDate($this->cell($row, 16)),
            'stage2_final_response' => $this->parseDate($this->cell($row, 17)),
            'final_report' => $this->cell($row, 18),
            'stage2_service_deadline_days' => $this->parseInt($this->cell($row, 19)),
            'stage2_final_deadline' => $this->parseDate($this->cell($row, 20)),
            'stage2_final_answer' => $this->cell($row, 21),
            'stage2_status' => $this->cell($row, 22),
            'accord_report' => $this->cell($row, 23),
            'accord_report_date' => $this->parseDate($this->cell($row, 24)),
            'stage3_status' => $this->cell($row, 32),
            'control_point' => $this->cell($row, 60) ?: $this->cell($row, 31),
            'related_processes' => $this->cell($row, 61),
            'notes' => $this->cell($row, 62) ?: $this->cell($row, 87),
        ];
    }

    private function mapItem(array $row): array
    {
        return [
            'item_code' => $this->cell($row, 25),
            'item_kind' => $this->normalizeKind($this->cell($row, 25)),
            'item_description' => $this->cell($row, 26),
            'compliance_deadline_days' => $this->parseInt($this->cell($row, 27)),
            'compliance_start_date' => $this->parseDate($this->cell($row, 28)),
            'control_body_status' => $this->cell($row, 29),
            'dgba_status' => $this->cell($row, 30),
            'item_control_point' => $this->cell($row, 31),
            'stage3_status' => $this->cell($row, 32),
            'monitor_1_start_date' => $this->parseDate($this->cell($row, 33)),
            'monitor_1_deadline_days' => $this->parseInt($this->cell($row, 34)),
            'monitor_1_final_deadline' => $this->parseDate($this->cell($row, 35)),
            'monitor_1_response' => $this->cell($row, 36),
            'monitor_2_start_date' => $this->parseDate($this->cell($row, 37)),
            'monitor_2_deadline_days' => $this->parseInt($this->cell($row, 38)),
            'monitor_2_final_deadline' => $this->parseDate($this->cell($row, 39)),
            'monitor_2_response' => $this->cell($row, 40),
            'monitor_3_start_date' => $this->parseDate($this->cell($row, 43)),
            'monitor_3_deadline_days' => $this->parseInt($this->cell($row, 44)),
            'monitor_3_final_deadline' => $this->parseDate($this->cell($row, 45)),
            'monitor_3_response' => $this->cell($row, 46),
            'monitor_4_start_date' => $this->parseDate($this->cell($row, 49)),
            'monitor_4_deadline_days' => $this->parseInt($this->cell($row, 50)),
            'monitor_4_final_deadline' => $this->parseDate($this->cell($row, 51)),
            'monitor_4_response' => $this->cell($row, 52),
        ];
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

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function fixText(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        if ($value === '') {
            return '';
        }

        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        $value = $converted !== false ? $converted : $value;
        $double = @utf8_decode($value);
        if ($double !== false) {
            $reconverted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $double);
            if ($reconverted !== false && preg_match('/[ÁÉÍÓÚÃÕÇáéíóúãõç]/u', $reconverted)) {
                $value = $reconverted;
            }
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '' || in_array(strtoupper($value), ['N/A', 'NÃO', 'NAO'], true)) {
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
        return ctype_digit($value) ? (int) $value : null;
    }

    private function parseBool(string $value): int
    {
        return in_array(strtoupper($value), ['SIM', 'S'], true) ? 1 : 0;
    }

    private function normalizeKind(string $value): string
    {
        return match (strtoupper($value)) {
            'DETERMINAÇÃO', 'DETERMINACAO' => 'DETERMINAÇÃO',
            'RECOMENDAÇÃO', 'RECOMENDACAO' => 'RECOMENDAÇÃO',
            'CIÊNCIA', 'CIENCIA' => 'CIÊNCIA',
            default => 'N/A',
        };
    }
}

