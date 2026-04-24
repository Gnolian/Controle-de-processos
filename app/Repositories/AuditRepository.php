<?php

declare(strict_types=1);

namespace App\Repositories;

class AuditRepository
{
    public const AUDIT_COLUMNS = [
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
        'deadline_label',
        'deadline_date',
        'deadline_is_current',
        'flag_estimated',
        'has_diligence',
        'last_response_sent_date_diligence',
        'stage2_start_date',
        'flag_stage2_diligence',
        'stage2_date_last_response_diligence',
        'stage2_preliminary_document',
        'last_response_sent_date',
        'preliminary_document',
        'stage2_deadline_days',
        'comments_due_date',
        'stage2_final_response',
        'stage2_final_report',
        'final_report',
        'stage2_service_deadline_days',
        'stage2_final_deadline',
        'stage2_final_answer',
        'stage2_status',
        'stage3_accord_report',
        'stage3_accord_report_date',
        'accord_report',
        'accord_report_date',
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
        'monitoring4_status',
        'control_point',
        'related_processes',
        'control_summary',
        'notes',
    ];

    public const ITEM_COLUMNS = [
        'item_code',
        'item_kind',
        'item_description',
        'compliance_deadline_days',
        'compliance_start_date',
        'control_body_status',
        'dgba_status',
        'item_control_point',
        'status_geral',
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

    public function upsertAudit(array $payload, ?int $userId = null): int
    {
        $existing = $this->findByCode((string) $payload['audit_code']);
        if ($existing) {
            $this->updateAudit((int) $existing['id'], $payload, $userId);
            return (int) $existing['id'];
        }

        return $this->createAudit($payload, $userId);
    }

    public function createAudit(array $payload, ?int $userId = null): int
    {
        $columns = array_merge(self::AUDIT_COLUMNS, ['imported_by']);
        $values = array_map(static fn (string $column) => $payload[$column] ?? null, self::AUDIT_COLUMNS);
        $values[] = $userId;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        \db()->prepare('INSERT INTO audits (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')')->execute($values);

        return (int) \db()->lastInsertId();
    }

    public function updateAudit(int $id, array $payload, ?int $userId = null): void
    {
        $sets = implode(', ', array_map(static fn (string $column) => "{$column} = ?", self::AUDIT_COLUMNS));
        $values = array_map(static fn (string $column) => $payload[$column] ?? null, self::AUDIT_COLUMNS);
        $values[] = $userId;
        $values[] = $id;
        \db()->prepare("UPDATE audits SET {$sets}, imported_by = ? WHERE id = ?")->execute($values);
    }

    public function replaceItems(int $auditId, array $items): void
    {
        \db()->prepare('DELETE FROM audit_items WHERE audit_id = ?')->execute([$auditId]);
        if ($items === []) {
            return;
        }

        $sql = 'INSERT INTO audit_items (
            audit_id, item_code, item_kind, item_description, compliance_deadline_days, compliance_start_date,
            control_body_status, dgba_status, item_control_point, status_geral, stage3_status,
            monitor_1_start_date, monitor_1_deadline_days, monitor_1_final_deadline, monitor_1_response,
            monitor_2_start_date, monitor_2_deadline_days, monitor_2_final_deadline, monitor_2_response,
            monitor_3_start_date, monitor_3_deadline_days, monitor_3_final_deadline, monitor_3_response,
            monitor_4_start_date, monitor_4_deadline_days, monitor_4_final_deadline, monitor_4_response, item_order
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
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
                $item['status_geral'] ?? null,
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
        [$where, $params] = $this->buildFilters($filters);
        $sql = 'SELECT a.*,
                COUNT(ai.id) AS total_items,
                SUM(ai.item_kind IN ("DETERMINACAO", "DETERMINAÇÃO")) AS total_determinacoes,
                SUM(ai.item_kind IN ("RECOMENDACAO", "RECOMENDAÇÃO")) AS total_recomendacoes,
                SUM(ai.item_kind IN ("CIENCIA", "CIÊNCIA")) AS total_ciencias
            FROM audits a
            LEFT JOIN audit_items ai ON ai.audit_id = a.id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY a.id ORDER BY a.deadline_is_current DESC, a.deadline_date IS NULL, a.deadline_date ASC, a.audit_year DESC, a.audit_code ASC';

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

    public function dashboardMetrics(): array
    {
        return [
            'total' => (int) \db()->query('SELECT COUNT(*) FROM audits')->fetchColumn(),
            'in_diligence' => $this->countPhaseLike(['%DILIGENC%']),
            'first_monitoring' => $this->countPhaseLike(['1%', 'PRIMEIRO%', '%1O%MONITORAMENTO%', '%1º%MONITORAMENTO%']),
            'second_monitoring' => $this->countPhaseLike(['2%', 'SEGUNDO%', '%2O%MONITORAMENTO%', '%2º%MONITORAMENTO%']),
            'third_monitoring' => $this->countPhaseLike(['3%', 'TERCEIRO%', '%3O%MONITORAMENTO%', '%3º%MONITORAMENTO%']),
            'fourth_monitoring' => $this->countPhaseLike(['4%', 'QUARTO%', '%4O%MONITORAMENTO%', '%4º%MONITORAMENTO%']),
        ];
    }

    public function countsByBody(): array
    {
        return \db()->query('SELECT COALESCE(NULLIF(requesting_body, ""), "Nao informado") AS label, COUNT(*) AS total
            FROM audits
            GROUP BY label
            ORDER BY total DESC, label ASC')->fetchAll();
    }

    public function diligencePhaseOverview(): array
    {
        $rows = \db()->query('SELECT COALESCE(NULLIF(audit_phase, ""), "Nao informado") AS raw_label, COUNT(*) AS total
            FROM audits
            GROUP BY raw_label
            ORDER BY total DESC, raw_label ASC')->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $label = $this->phaseBucket((string) $row['raw_label']);
            $grouped[$label] = ($grouped[$label] ?? 0) + (int) $row['total'];
        }

        $result = [];
        foreach ($grouped as $label => $total) {
            $result[] = ['label' => $label, 'total' => $total];
        }

        usort($result, static fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));

        return $result;
    }

    public function countsByType(): array
    {
        return \db()->query('SELECT COALESCE(NULLIF(audit_type, ""), "Nao informado") AS label, COUNT(*) AS total
            FROM audits
            GROUP BY label
            ORDER BY total DESC, label ASC')->fetchAll();
    }

    public function itemTotalsPerAudit(): array
    {
        return \db()->query('SELECT a.id, a.audit_code, a.audit_nup, a.requesting_body,
                SUM(ai.item_kind IN ("DETERMINACAO", "DETERMINAÇÃO")) AS determinacoes,
                SUM(ai.item_kind IN ("RECOMENDACAO", "RECOMENDAÇÃO")) AS recomendacoes,
                SUM(ai.item_kind IN ("CIENCIA", "CIÊNCIA")) AS ciencias,
                COUNT(ai.id) AS total
            FROM audits a
            INNER JOIN audit_items ai ON ai.audit_id = a.id
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÇÃO", "RECOMENDACAO", "RECOMENDAÇÃO", "CIENCIA", "CIÊNCIA")
            GROUP BY a.id, a.audit_code, a.audit_nup, a.requesting_body
            HAVING total > 0
            ORDER BY total DESC, a.audit_code ASC')->fetchAll();
    }

    public function itemImplementationSummary(): array
    {
        $rows = \db()->query('SELECT ai.*, a.id AS audit_id, a.audit_code, a.audit_nup, a.requesting_body
            FROM audit_items ai
            INNER JOIN audits a ON a.id = ai.audit_id
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÇÃO", "RECOMENDACAO", "RECOMENDAÇÃO", "CIENCIA", "CIÊNCIA")
            ORDER BY a.audit_code ASC, ai.item_order ASC')->fetchAll();

        $groups = [];
        foreach ($rows as $row) {
            $label = $this->classifyItemStatus((string) $row['control_body_status']);
            $groups[$label] = ($groups[$label] ?? 0) + 1;
        }

        $result = [];
        foreach ($groups as $label => $total) {
            $result[] = ['label' => $label, 'total' => $total];
        }

        usort($result, static fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));

        return $result;
    }

    public function itemsByStatusGroup(?string $group = null, int $limit = 18): array
    {
        $rows = \db()->query('SELECT ai.*, a.id AS audit_id, a.audit_code, a.audit_nup, a.requesting_body
            FROM audit_items ai
            INNER JOIN audits a ON a.id = ai.audit_id
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÇÃO", "RECOMENDACAO", "RECOMENDAÇÃO", "CIENCIA", "CIÊNCIA")
            ORDER BY a.audit_code ASC, ai.item_order ASC')->fetchAll();

        $cards = [];
        foreach ($rows as $row) {
            $label = $this->classifyItemStatus((string) $row['control_body_status']);
            if ($group !== null && $group !== '' && $label !== $group) {
                continue;
            }

            $cards[] = $row + ['status_group' => $label];
            if (count($cards) >= $limit) {
                break;
            }
        }

        return $cards;
    }

    public function timelineEntries(int $year = 2026): array
    {
        $stmt = \db()->prepare('SELECT id, audit_code, audit_nup, requesting_body, theme, audit_phase, current_owner,
                deadline_label, deadline_date, deadline_is_current, flag_estimated, control_summary, related_processes
            FROM audits
            WHERE deadline_is_current = 1
               OR (deadline_date BETWEEN ? AND ?)
            ORDER BY deadline_is_current DESC, deadline_date ASC, audit_code ASC');
        $stmt->execute(["{$year}-01-01", "{$year}-12-31"]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            $summary = $row['control_summary'] ?: $row['related_processes'] ?: '-';
            return [
                'id' => (int) $row['id'],
                'audit_code' => $row['audit_code'],
                'audit_nup' => $row['audit_nup'],
                'requesting_body' => $row['requesting_body'],
                'theme' => $row['theme'],
                'audit_phase' => $row['audit_phase'],
                'current_owner' => $row['current_owner'],
                'deadline_label' => $row['deadline_label'] ?: ($row['deadline_date'] ? \format_date($row['deadline_date']) : 'Sem prazo'),
                'deadline_date' => $row['deadline_date'],
                'deadline_is_current' => (int) $row['deadline_is_current'],
                'flag_estimated' => (int) $row['flag_estimated'],
                'control_summary' => $summary,
                'month_index' => (int) ($row['deadline_is_current'] ? date('n') : date('n', strtotime((string) $row['deadline_date']))),
            ];
        }, $rows);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = \db()->prepare('SELECT id FROM audits WHERE audit_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $audit = $stmt->fetch();

        return $audit ?: null;
    }

    public function distinctValues(string $column): array
    {
        $allowed = ['requesting_body', 'audit_type', 'audit_phase', 'process_status'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        return \db()->query("SELECT DISTINCT {$column} AS value FROM audits WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC")->fetchAll();
    }

    private function buildFilters(array $filters): array
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
            'process_status' => 'a.process_status',
        ] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $params[] = $filters[$filter];
            }
        }

        if (($filters['audit_phase'] ?? '') !== '') {
            if ($filters['audit_phase'] === 'Em diligencia') {
                $where[] = 'UPPER(a.audit_phase) LIKE ?';
                $params[] = '%DILIG%';
            } else {
                $where[] = 'a.audit_phase = ?';
                $params[] = $filters['audit_phase'];
            }
        }

        if (($filters['item_kind'] ?? '') !== '') {
            $accented = match ($filters['item_kind']) {
                'DETERMINACAO' => 'DETERMINAÇÃO',
                'RECOMENDACAO' => 'RECOMENDAÇÃO',
                'CIENCIA' => 'CIÊNCIA',
                default => $filters['item_kind'],
            };
            $where[] = 'ai.item_kind IN (?, ?)';
            $params[] = $filters['item_kind'];
            $params[] = $accented;
        }

        if (($filters['diligence'] ?? '') === '1') {
            $where[] = 'a.has_diligence = 1';
        } elseif (($filters['diligence'] ?? '') === '0') {
            $where[] = 'a.has_diligence = 0';
        }

        return [$where, $params];
    }

    private function countPhaseLike(array $patterns): int
    {
        $clauses = [];
        $params = [];
        foreach ($patterns as $pattern) {
            $clauses[] = 'UPPER(audit_phase) LIKE ?';
            $params[] = strtoupper($pattern);
        }

        $stmt = \db()->prepare('SELECT COUNT(*) FROM audits WHERE ' . implode(' OR ', $clauses));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function classifyItemStatus(string $value): string
    {
        $token = $this->normalizeToken($value);
        return match (true) {
            $token === '' || $token === 'NAO' || $token === 'NAINFORMADA' || $token === 'NAOAPLICA' || $token === 'N/A' => 'Nao informada',
            str_contains($token, 'PERDADEOBJETO') => 'Perda de objeto',
            str_contains($token, 'IMPLEMENTADA') || str_contains($token, 'CUMPRIDA') => 'Implementada',
            str_contains($token, 'EMIMPLEMENTACAO') || str_contains($token, 'IMPLEMENTACAO') => 'Em implementacao',
            default => 'Outros',
        };
    }

    private function phaseBucket(string $phase): string
    {
        $token = $this->normalizeToken($phase);

        return match (true) {
            $token === '' => 'Nao informado',
            str_contains($token, 'DILIGENC') => 'Em diligencia',
            default => $phase,
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
