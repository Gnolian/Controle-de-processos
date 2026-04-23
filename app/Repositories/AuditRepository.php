<?php

declare(strict_types=1);

namespace App\Repositories;

class AuditRepository
{
    public function upsertAudit(array $payload, ?int $userId = null): int
    {
        $existing = $this->findByCode((string) $payload['audit_code']);
        $columns = [
            'audit_code', 'audit_nup', 'audit_year', 'process_status', 'requesting_body', 'audit_type',
            'objective', 'theme', 'classification', 'audit_phase', 'current_owner', 'start_date',
            'has_diligence', 'last_response_sent_date', 'preliminary_document', 'stage2_deadline_days',
            'comments_due_date', 'stage2_final_response', 'final_report', 'stage2_service_deadline_days',
            'stage2_final_deadline', 'stage2_final_answer', 'stage2_status', 'accord_report',
            'accord_report_date', 'stage3_status', 'control_point', 'related_processes', 'notes',
        ];

        if ($existing) {
            $sets = implode(', ', array_map(static fn (string $column) => "{$column} = ?", $columns));
            $values = array_map(static fn (string $column) => $payload[$column] ?? null, $columns);
            $values[] = $userId;
            $values[] = $existing['id'];
            \db()->prepare("UPDATE audits SET {$sets}, imported_by = ? WHERE id = ?")->execute($values);
            return (int) $existing['id'];
        }

        $insertColumns = array_merge($columns, ['imported_by']);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $values = array_map(static fn (string $column) => $payload[$column] ?? null, $columns);
        $values[] = $userId;
        \db()->prepare('INSERT INTO audits (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')')->execute($values);

        return (int) \db()->lastInsertId();
    }

    public function replaceItems(int $auditId, array $items): void
    {
        \db()->prepare('DELETE FROM audit_items WHERE audit_id = ?')->execute([$auditId]);
        $sql = 'INSERT INTO audit_items (
            audit_id, item_code, item_kind, item_description, compliance_deadline_days, compliance_start_date,
            control_body_status, dgba_status, item_control_point, stage3_status, monitor_1_start_date,
            monitor_1_deadline_days, monitor_1_final_deadline, monitor_1_response, monitor_2_start_date,
            monitor_2_deadline_days, monitor_2_final_deadline, monitor_2_response, monitor_3_start_date,
            monitor_3_deadline_days, monitor_3_final_deadline, monitor_3_response, monitor_4_start_date,
            monitor_4_deadline_days, monitor_4_final_deadline, monitor_4_response, item_order
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )';
        $stmt = \db()->prepare($sql);

        foreach ($items as $index => $item) {
            $stmt->execute([
                $auditId,
                $item['item_code'] ?? null,
                $item['item_kind'] ?? null,
                $item['item_description'] ?? null,
                $item['compliance_deadline_days'] ?? null,
                $item['compliance_start_date'] ?? null,
                $item['control_body_status'] ?? null,
                $item['dgba_status'] ?? null,
                $item['item_control_point'] ?? null,
                $item['stage3_status'] ?? null,
                $item['monitor_1_start_date'] ?? null,
                $item['monitor_1_deadline_days'] ?? null,
                $item['monitor_1_final_deadline'] ?? null,
                $item['monitor_1_response'] ?? null,
                $item['monitor_2_start_date'] ?? null,
                $item['monitor_2_deadline_days'] ?? null,
                $item['monitor_2_final_deadline'] ?? null,
                $item['monitor_2_response'] ?? null,
                $item['monitor_3_start_date'] ?? null,
                $item['monitor_3_deadline_days'] ?? null,
                $item['monitor_3_final_deadline'] ?? null,
                $item['monitor_3_response'] ?? null,
                $item['monitor_4_start_date'] ?? null,
                $item['monitor_4_deadline_days'] ?? null,
                $item['monitor_4_final_deadline'] ?? null,
                $item['monitor_4_response'] ?? null,
                $index + 1,
            ]);
        }
    }

    public function list(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.audit_code LIKE ? OR a.audit_nup LIKE ? OR a.theme LIKE ? OR a.objective LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term);
        }

        foreach ([
            'requesting_body' => 'a.requesting_body',
            'audit_type' => 'a.audit_type',
            'audit_phase' => 'a.audit_phase',
            'item_kind' => 'ai.item_kind',
        ] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $params[] = $filters[$filter];
            }
        }

        if (($filters['diligence'] ?? '') === '1') {
            $where[] = 'a.has_diligence = 1';
        }
        if (($filters['diligence'] ?? '') === '0') {
            $where[] = 'a.has_diligence = 0';
        }

        $sql = 'SELECT a.*,
                COUNT(ai.id) AS total_items,
                SUM(ai.item_kind = "DETERMINACAO") AS total_determinacoes,
                SUM(ai.item_kind = "RECOMENDACAO") AS total_recomendacoes,
                SUM(ai.item_kind = "CIENCIA") AS total_ciencias
            FROM audits a
            LEFT JOIN audit_items ai ON ai.audit_id = a.id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY a.id ORDER BY a.audit_year DESC, a.audit_code ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = \db()->prepare('SELECT * FROM audits WHERE id = ?');
        $stmt->execute([$id]);
        $audit = $stmt->fetch();

        return $audit ?: null;
    }

    public function items(int $auditId): array
    {
        $stmt = \db()->prepare('SELECT * FROM audit_items WHERE audit_id = ? ORDER BY item_order ASC');
        $stmt->execute([$auditId]);
        return $stmt->fetchAll();
    }

    public function countsByBody(): array
    {
        return \db()->query('SELECT COALESCE(NULLIF(requesting_body, ""), "Nao informado") AS label, COUNT(*) AS total
            FROM audits GROUP BY label ORDER BY total DESC, label ASC')->fetchAll();
    }

    public function diligenceSummary(): array
    {
        return [
            'in_diligence' => (int) \db()->query('SELECT COUNT(*) FROM audits WHERE has_diligence = 1')->fetchColumn(),
            'by_phase' => \db()->query('SELECT COALESCE(NULLIF(audit_phase, ""), "Nao informado") AS label, COUNT(*) AS total
                FROM audits WHERE has_diligence = 0 GROUP BY label ORDER BY total DESC, label ASC')->fetchAll(),
        ];
    }

    public function countsByType(): array
    {
        return \db()->query('SELECT COALESCE(NULLIF(audit_type, ""), "Nao informado") AS label, COUNT(*) AS total
            FROM audits GROUP BY label ORDER BY total DESC, label ASC')->fetchAll();
    }

    public function itemTotalsPerAudit(): array
    {
        return \db()->query('SELECT a.id, a.audit_code, a.audit_nup, a.requesting_body,
                SUM(ai.item_kind = "DETERMINACAO") AS determinacoes,
                SUM(ai.item_kind = "RECOMENDACAO") AS recomendacoes,
                SUM(ai.item_kind = "CIENCIA") AS ciencias,
                COUNT(ai.id) AS total
            FROM audits a
            INNER JOIN audit_items ai ON ai.audit_id = a.id
            WHERE ai.item_kind IN ("DETERMINACAO", "RECOMENDACAO", "CIENCIA")
            GROUP BY a.id, a.audit_code, a.audit_nup, a.requesting_body
            HAVING total > 0
            ORDER BY total DESC, a.audit_code ASC')->fetchAll();
    }

    private function findByCode(string $code): ?array
    {
        $stmt = \db()->prepare('SELECT id FROM audits WHERE audit_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $audit = $stmt->fetch();

        return $audit ?: null;
    }
}
