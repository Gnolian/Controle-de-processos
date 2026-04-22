<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\SyncRepository;
use RuntimeException;

class CsvImportService
{
    private ProcessRepository $processes;
    private AuditLogRepository $audit;
    private SyncRepository $sync;

    public function __construct(?ProcessRepository $processes = null, ?AuditLogRepository $audit = null, ?SyncRepository $sync = null)
    {
        $this->processes = $processes ?? new ProcessRepository();
        $this->audit = $audit ?? new AuditLogRepository();
        $this->sync = $sync ?? new SyncRepository();
    }

    public function importUploaded(string $path, array $user): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo CSV nao encontrado.');
        }

        $rows = $this->readCsv($path);
        $headerIndex = $this->findHeaderIndex($rows);
        $headers = $rows[$headerIndex];
        $map = $this->buildColumnMap($headers);

        $created = 0;
        $updated = 0;
        $ignored = 0;

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $processNumber = $this->value($row, $map, 'process_number');
            if ($processNumber === '') {
                $ignored++;
                continue;
            }

            $payload = $this->payload($row, $map);
            $existing = $this->processes->findByNumber($processNumber);

            if ($existing) {
                $this->processes->update((int) $existing['id'], $payload);
                $this->audit->recordProcessChanges((int) $existing['id'], (int) $user['id'], $existing, $payload, 'importacao manual csv');
                $this->sync->markPending((int) $existing['id']);
                $updated++;
            } else {
                $id = $this->processes->create($payload, $user);
                $this->audit->recordProcessChanges($id, (int) $user['id'], [], $payload, 'importacao manual csv');
                $this->sync->markPending($id);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'ignored' => $ignored,
            'total' => $created + $updated,
        ];
    }

    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Nao foi possivel ler o CSV.');
        }

        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            }
        } elseif (function_exists('iconv')) {
            $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $content);
            if ($converted !== false) {
                $content = $converted;
            }
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ';')) !== false) {
            $rows[] = array_map([$this, 'clean'], $row);
        }
        fclose($stream);

        return $rows;
    }

    private function findHeaderIndex(array $rows): int
    {
        foreach ($rows as $index => $row) {
            foreach ($row as $cell) {
                if ($this->key($cell) === 'numero do processo') {
                    return $index;
                }
            }
        }

        throw new RuntimeException('Nao encontrei a linha de cabecalho com a coluna Numero do Processo.');
    }

    private function buildColumnMap(array $headers): array
    {
        $aliases = [
            'numero do processo' => 'process_number',
            'data da atualizacao' => 'updated_at',
            'responsavel pela resposta' => 'response_owner',
            'prazo (em dias)' => 'deadline_days',
            'descricao geral' => 'general_description',
            'descricao detalhada' => 'detailed_description',
            'comentarios/anotacoes' => 'notes',
            'orgao solicitante' => 'requesting_agency',
            'data de assinatura (oficio gab)' => 'gab_signature_date',
            'prazo interno (oficio gab/snba)' => 'internal_deadline_gab',
            'prazo interno ajustado' => 'adjusted_internal_deadline',
            'prazo externo/mds' => 'external_deadline_mds',
            'responsavel pela revisao' => 'review_owner',
            'resposta' => 'response_status',
            'revisao andrea' => 'andrea_review_status',
            'assinado' => 'signed_status',
            'enviado gab' => 'sent_gab_status',
            'data envio gab' => 'gab_sent_date',
            'status' => 'status',
            'bloco interno' => 'internal_block',
        ];

        $map = [];
        foreach ($headers as $index => $header) {
            $key = $this->key($header);
            if (isset($aliases[$key])) {
                $map[$aliases[$key]] = $index;
            }
        }

        if (!isset($map['process_number'])) {
            throw new RuntimeException('A coluna Numero do Processo e obrigatoria.');
        }

        return $map;
    }

    private function payload(array $row, array $map): array
    {
        return $this->processes->buildPayload([
            'process_number' => $this->value($row, $map, 'process_number'),
            'updated_at' => $this->parseDate($this->value($row, $map, 'updated_at')),
            'response_owner' => $this->value($row, $map, 'response_owner'),
            'deadline_days' => $this->parseInt($this->value($row, $map, 'deadline_days')),
            'general_description' => $this->value($row, $map, 'general_description'),
            'detailed_description' => $this->value($row, $map, 'detailed_description'),
            'notes' => $this->value($row, $map, 'notes'),
            'requesting_agency' => $this->value($row, $map, 'requesting_agency'),
            'gab_signature_date' => $this->parseDate($this->value($row, $map, 'gab_signature_date')),
            'internal_deadline_gab' => $this->parseDate($this->value($row, $map, 'internal_deadline_gab')),
            'adjusted_internal_deadline' => $this->parseDate($this->value($row, $map, 'adjusted_internal_deadline')),
            'external_deadline_mds' => $this->parseDate($this->value($row, $map, 'external_deadline_mds')),
            'review_owner' => $this->value($row, $map, 'review_owner'),
            'response_status' => $this->workflow($this->value($row, $map, 'response_status'), 'A iniciar'),
            'andrea_review_status' => $this->workflow($this->value($row, $map, 'andrea_review_status')),
            'signed_status' => $this->workflow($this->value($row, $map, 'signed_status')),
            'sent_gab_status' => $this->workflow($this->value($row, $map, 'sent_gab_status')),
            'gab_sent_date' => $this->parseDate($this->value($row, $map, 'gab_sent_date')),
            'status' => $this->status($this->value($row, $map, 'status')),
            'internal_block' => $this->value($row, $map, 'internal_block'),
        ]);
    }

    private function value(array $row, array $map, string $field): string
    {
        $index = $map[$field] ?? null;
        return $index === null ? '' : $this->clean((string) ($row[$index] ?? ''));
    }

    private function clean(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/[ \t]+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function key(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $this->clean($value));
        if (function_exists('iconv')) {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }
        $value = strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '' || in_array($this->key($value), ['sem data', 'n/a', 'na'], true)) {
            return null;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            $date = \DateTime::createFromFormat('d/m/Y', $value);
            return $date ? $date->format('Y-m-d') : null;
        }

        if (preg_match('/^\d{5}$/', $value)) {
            return (new \DateTime('1899-12-30'))->modify('+' . (int) $value . ' days')->format('Y-m-d');
        }

        return null;
    }

    private function parseInt(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    private function workflow(string $value, string $default = 'N/A'): string
    {
        return match ($this->key($value)) {
            'a iniciar' => 'A iniciar',
            'em andamento' => 'Em andamento',
            'concluido' => 'Concluido',
            'n/a', 'na' => 'N/A',
            default => $default,
        };
    }

    private function status(string $value): string
    {
        return match ($this->key($value)) {
            'aberto' => 'Aberto',
            'arquivar' => 'Arquivar',
            'arquivado (concluido)' => 'Arquivado (concluido)',
            default => 'Aberto',
        };
    }
}
