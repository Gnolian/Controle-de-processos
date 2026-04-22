<?php

declare(strict_types=1);

namespace App\Repositories;

class AuditLogRepository
{
    public function record(int $processId, ?int $userId, string $field, mixed $oldValue, mixed $newValue, string $origin = 'sistema interno'): void
    {
        if ((string) $oldValue === (string) $newValue) {
            return;
        }

        $stmt = \db()->prepare('INSERT INTO audit_logs (process_id, user_id, field_name, old_value, new_value, origin) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $processId,
            $userId,
            $field,
            $oldValue === null ? null : (string) $oldValue,
            $newValue === null ? null : (string) $newValue,
            $origin,
        ]);
    }

    public function recordProcessChanges(int $processId, ?int $userId, array $before, array $after, string $origin = 'sistema interno'): void
    {
        foreach (ProcessRepository::COLUMNS as $field) {
            $this->record($processId, $userId, $field, $before[$field] ?? null, $after[$field] ?? null, $origin);
        }
    }

    public function search(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['process_id'] ?? '') !== '') {
            $where[] = 'a.process_id = ?';
            $params[] = (int) $filters['process_id'];
        }

        if (($filters['user_id'] ?? '') !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'DATE(a.created_at) >= ?';
            $params[] = $filters['from'];
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'DATE(a.created_at) <= ?';
            $params[] = $filters['to'];
        }

        $sql = 'SELECT a.*, p.process_number, u.name AS user_name
                FROM audit_logs a
                LEFT JOIN processes p ON p.id = a.process_id
                LEFT JOIN users u ON u.id = a.user_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 300';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}

