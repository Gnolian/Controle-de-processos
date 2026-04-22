<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\ExcelGraphClient;
use App\Integrations\GraphAuthClient;
use App\Repositories\AuditLogRepository;
use App\Repositories\IntegrationSettingsRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\SyncRepository;
use RuntimeException;

class SyncService
{
    private ProcessRepository $processes;
    private SyncRepository $sync;
    private IntegrationSettingsRepository $settingsRepo;
    private AuditLogRepository $audit;

    public function __construct(?ProcessRepository $processes = null, ?SyncRepository $sync = null, ?IntegrationSettingsRepository $settingsRepo = null, ?AuditLogRepository $audit = null)
    {
        $this->processes = $processes ?? new ProcessRepository();
        $this->sync = $sync ?? new SyncRepository();
        $this->settingsRepo = $settingsRepo ?? new IntegrationSettingsRepository();
        $this->audit = $audit ?? new AuditLogRepository();
    }

    public function syncProcess(int $processId, array $user): void
    {
        $process = $this->processes->find($processId, $user);
        if (!$process) {
            throw new RuntimeException('Processo nao encontrado para sincronizacao.');
        }

        $settings = $this->settingsRepo->all();
        if (($settings['sync_enabled'] ?? '0') !== '1') {
            $this->sync->markPending($processId);
            $this->sync->log($processId, 'info', 'Sincronizacao automatica desativada. Processo permanece pendente.');
            return;
        }

        try {
            $client = $this->excelClient($settings);
            $columns = $this->columnNames($client->listColumns());
            $values = $this->rowValues($columns, $process);
            $state = $this->sync->stateFor($processId);
            $rowIndex = isset($state['excel_row_index']) && $state['excel_row_index'] !== null ? (int) $state['excel_row_index'] : $this->findRowIndexByProcessNumber($client, $columns, (string) $process['process_number']);

            $response = $rowIndex === null
                ? $client->addRow($values)
                : $client->updateRow($rowIndex, $values);

            $newIndex = $rowIndex ?? (isset($response['index']) ? (int) $response['index'] : null);
            $this->sync->markSuccess($processId, $newIndex, $response);
        } catch (\Throwable $exception) {
            $this->sync->markFailed($processId, $exception->getMessage(), $process);
        }
    }

    public function syncFailed(array $user): int
    {
        $count = 0;
        foreach ($this->sync->failedProcesses() as $process) {
            $this->syncProcess((int) $process['id'], $user);
            $count++;
        }

        return $count;
    }

    public function importFromExcel(array $user): int
    {
        $settings = $this->settingsRepo->all();
        $client = $this->excelClient($settings);
        $columns = $this->columnNames($client->listColumns());
        $rows = $client->listRows()['value'] ?? [];
        $imported = 0;

        foreach ($rows as $row) {
            $payload = $this->payloadFromRow($columns, $row['values'][0] ?? []);
            if (($payload['process_number'] ?? '') === '') {
                continue;
            }

            $existing = $this->processes->findByNumber((string) $payload['process_number']);
            if ($existing) {
                $this->processes->update((int) $existing['id'], $payload);
                $this->audit->recordProcessChanges((int) $existing['id'], (int) $user['id'], $existing, $payload, 'sincronizacao');
                $this->sync->markSuccess((int) $existing['id'], isset($row['index']) ? (int) $row['index'] : null, $row);
            } else {
                $newId = $this->processes->create($payload, $user);
                $this->audit->recordProcessChanges($newId, (int) $user['id'], [], $payload, 'sincronizacao');
                $this->sync->markSuccess($newId, isset($row['index']) ? (int) $row['index'] : null, $row);
            }

            $imported++;
        }

        $this->sync->log(null, 'info', "Importacao da planilha concluida: {$imported} linha(s) processada(s).");
        return $imported;
    }

    public function testConnection(): array
    {
        $settings = $this->settingsRepo->all();
        $client = $this->excelClient($settings);

        return [
            'columns' => $this->columnNames($client->listColumns()),
            'rows' => count($client->listRows()['value'] ?? []),
        ];
    }

    private function excelClient(array $settings): ExcelGraphClient
    {
        $token = (new GraphAuthClient())->refreshToken($settings);
        if (!empty($token['refresh_token'])) {
            $this->settingsRepo->save(['refresh_token' => $token['refresh_token']]);
        }

        return new ExcelGraphClient($token['access_token'], $settings);
    }

    private function columnNames(array $columnsResponse): array
    {
        $columns = [];
        foreach ($columnsResponse['value'] ?? [] as $column) {
            $columns[] = (string) ($column['name'] ?? '');
        }

        if (!$columns) {
            throw new RuntimeException('Nao foi possivel ler as colunas da tabela Excel.');
        }

        return $columns;
    }

    private function rowValues(array $columns, array $process): array
    {
        $mapping = \config('excel_columns');
        $values = [];

        foreach ($columns as $columnName) {
            $field = $mapping[$columnName] ?? null;
            $values[] = $field ? ($process[$field] ?? '') : '';
        }

        return $values;
    }

    private function payloadFromRow(array $columns, array $values): array
    {
        $mapping = \config('excel_columns');
        $payload = array_fill_keys(ProcessRepository::COLUMNS, '');

        foreach ($columns as $index => $columnName) {
            $field = $mapping[$columnName] ?? null;
            if ($field && in_array($field, ProcessRepository::COLUMNS, true)) {
                $payload[$field] = (string) ($values[$index] ?? '');
            }
        }

        return $this->processes->buildPayload($payload);
    }

    private function findRowIndexByProcessNumber(ExcelGraphClient $client, array $columns, string $processNumber): ?int
    {
        $mapping = \config('excel_columns');
        $processColumnIndex = null;
        foreach ($columns as $index => $columnName) {
            if (($mapping[$columnName] ?? '') === 'process_number') {
                $processColumnIndex = $index;
                break;
            }
        }

        if ($processColumnIndex === null) {
            throw new RuntimeException('A tabela Excel nao possui coluna reconhecida para Numero do Processo.');
        }

        foreach ($client->listRows()['value'] ?? [] as $row) {
            $values = $row['values'][0] ?? [];
            if (($values[$processColumnIndex] ?? '') === $processNumber) {
                return isset($row['index']) ? (int) $row['index'] : null;
            }
        }

        return null;
    }
}
