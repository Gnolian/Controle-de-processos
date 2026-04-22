<?php

declare(strict_types=1);

namespace App\Repositories;

class SyncRepository
{
    public function stateFor(int $processId): ?array
    {
        $stmt = \db()->prepare('SELECT * FROM process_sync_state WHERE process_id = ?');
        $stmt->execute([$processId]);
        $state = $stmt->fetch();

        return $state ?: null;
    }

    public function markPending(int $processId): void
    {
        $this->upsertState($processId, [
            'sync_status' => 'pending',
            'last_error' => null,
        ]);
    }

    public function markSuccess(int $processId, ?int $rowIndex, array $response): void
    {
        $this->upsertState($processId, [
            'sync_status' => 'success',
            'excel_row_index' => $rowIndex,
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

        $this->log($processId, 'success', 'Sincronizacao realizada com sucesso.', [], $response);
    }

    public function markFailed(int $processId, string $message, array $payload = [], array $response = []): void
    {
        $this->upsertState($processId, [
            'sync_status' => 'failed',
            'last_error' => $message,
        ]);

        $this->log($processId, 'failed', $message, $payload, $response);
    }

    public function log(?int $processId, string $status, string $message, array $payload = [], array $response = []): void
    {
        $stmt = \db()->prepare('INSERT INTO sync_logs (process_id, status, message, payload, response) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $processId,
            $status,
            $message,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            $response ? json_encode($response, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function latest(int $limit = 100): array
    {
        $stmt = \db()->prepare('SELECT s.*, p.process_number FROM sync_logs s LEFT JOIN processes p ON p.id = s.process_id ORDER BY s.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function failedProcesses(): array
    {
        return \db()->query("SELECT p.*, ps.sync_status, ps.last_error FROM process_sync_state ps INNER JOIN processes p ON p.id = ps.process_id WHERE ps.sync_status = 'failed' ORDER BY ps.updated_at DESC")->fetchAll();
    }

    private function upsertState(int $processId, array $data): void
    {
        $current = $this->stateFor($processId);
        if (!$current) {
            \db()->prepare('INSERT INTO process_sync_state (process_id, sync_status, excel_row_index, last_synced_at, last_error) VALUES (?, ?, ?, ?, ?)')
                ->execute([
                    $processId,
                    $data['sync_status'] ?? 'pending',
                    $data['excel_row_index'] ?? null,
                    $data['last_synced_at'] ?? null,
                    $data['last_error'] ?? null,
                ]);
            return;
        }

        $fields = [];
        $values = [];
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        $values[] = $processId;
        \db()->prepare('UPDATE process_sync_state SET ' . implode(', ', $fields) . ' WHERE process_id = ?')->execute($values);
    }
}

