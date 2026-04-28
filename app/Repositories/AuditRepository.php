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
                SUM(ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO")) AS total_determinacoes,
                SUM(ai.item_kind IN ("RECOMENDACAO", "RECOMENDAÃ‡ÃƒO")) AS total_recomendacoes,
                SUM(ai.item_kind IN ("CIENCIA", "CIÃŠNCIA")) AS total_ciencias
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

    public function dashboardMetrics(array $filters = []): array
    {
        $diligenceReport = $this->countPhaseLike(['%DILIGENC%', '%RELATOR%'], $filters);
        $monitoringPending = $this->countPhaseLike(['%INICIAR%'], $filters);
        $first = $this->countPhaseLike(['1%', 'PRIMEIRO%', '%1O%MONITORAMENTO%', '%1Âº%MONITORAMENTO%'], $filters);
        $second = $this->countPhaseLike(['2%', 'SEGUNDO%', '%2O%MONITORAMENTO%', '%2Âº%MONITORAMENTO%'], $filters);
        $third = $this->countPhaseLike(['3%', 'TERCEIRO%', '%3O%MONITORAMENTO%', '%3Âº%MONITORAMENTO%'], $filters);
        $fourth = $this->countPhaseLike(['4%', 'QUARTO%', '%4O%MONITORAMENTO%', '%4Âº%MONITORAMENTO%'], $filters);

        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $sql = 'SELECT COUNT(DISTINCT a.id) FROM audits a' . $joins;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        return [
            'total' => $total,
            'rdc_total' => $this->countRdcItems($filters),
            'diligence_report' => $diligenceReport,
            'monitoring_pending' => $monitoringPending,
            'first_monitoring' => $first,
            'second_monitoring' => $second,
            'third_monitoring' => $third,
            'fourth_monitoring' => $fourth,
            'other_phases' => max(0, $total - ($diligenceReport + $monitoringPending + $first + $second + $third + $fourth)),
        ];
    }

    public function countsByBody(array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $sql = 'SELECT COALESCE(NULLIF(a.requesting_body, ""), "Não informado") AS label, COUNT(DISTINCT a.id) AS total
            FROM audits a' . $joins;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY label ORDER BY total DESC, label ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function diligencePhaseOverview(array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $sql = 'SELECT COALESCE(NULLIF(a.audit_phase, ""), "Não informado") AS raw_label, COUNT(DISTINCT a.id) AS total
            FROM audits a' . $joins;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY raw_label ORDER BY total DESC, raw_label ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

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

    public function countsByType(array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $sql = 'SELECT COALESCE(NULLIF(a.audit_type, ""), "Não informado") AS label, COUNT(DISTINCT a.id) AS total
            FROM audits a' . $joins;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY label ORDER BY total DESC, label ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function itemTotalsPerAudit(array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters, true);
        $sql = 'SELECT a.id, a.audit_code, a.audit_nup, a.requesting_body,
                SUM(ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO")) AS determinacoes,
                SUM(ai.item_kind IN ("RECOMENDACAO", "RECOMENDAÃ‡ÃƒO")) AS recomendacoes,
                SUM(ai.item_kind IN ("CIENCIA", "CIÃŠNCIA")) AS ciencias,
                COUNT(ai.id) AS total
            FROM audits a' . $joins . '
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO", "RECOMENDACAO", "RECOMENDAÃ‡ÃƒO", "CIENCIA", "CIÃŠNCIA")';
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY a.id, a.audit_code, a.audit_nup, a.requesting_body
            HAVING total > 0
            ORDER BY total DESC, a.audit_code ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function itemImplementationSummary(array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters, true);
        $sql = 'SELECT ai.control_body_status
            FROM audits a' . $joins . '
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO", "RECOMENDACAO", "RECOMENDAÃ‡ÃƒO", "CIENCIA", "CIÃŠNCIA")';
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.audit_code ASC, ai.item_order ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

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

    public function itemsByStatusGroup(?string $group = null, int $limit = 18, array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters, true);
        $sql = 'SELECT ai.*, a.id AS audit_id, a.audit_code, a.audit_nup, a.requesting_body
            FROM audits a' . $joins . '
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO", "RECOMENDACAO", "RECOMENDAÃ‡ÃƒO", "CIENCIA", "CIÃŠNCIA")';
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.audit_code ASC, ai.item_order ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

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

    public function timelineEntries(int $year = 2026, array $filters = []): array
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $currentYear = (int) date('Y');
        $sql = 'SELECT DISTINCT a.id, a.audit_code, a.audit_nup, a.requesting_body, a.theme, a.audit_phase, a.current_owner,
                a.deadline_label, a.deadline_date, a.deadline_is_current, a.flag_estimated, a.control_summary, a.related_processes
            FROM audits a' . $joins . '
            WHERE (';
        if ($year === $currentYear) {
            $sql .= 'a.deadline_is_current = 1 OR ';
        }
        $sql .= '(a.deadline_date BETWEEN ? AND ?))';
        $timelineParams = ["{$year}-01-01", "{$year}-12-31"];
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
            $timelineParams = array_merge($timelineParams, $params);
        }
        $sql .= ' ORDER BY a.deadline_is_current DESC, a.deadline_date ASC, a.audit_code ASC';

        $stmt = \db()->prepare($sql);
        $stmt->execute($timelineParams);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            $summary = $row['control_summary'] ?: $row['related_processes'] ?: '-';
            $ownerToken = $this->normalizeToken((string) ($row['current_owner'] ?? ''));

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
                'is_dgba' => str_contains($ownerToken, 'DGBA') ? 1 : 0,
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
        $allowed = ['audit_year', 'process_status', 'requesting_body', 'theme', 'classification', 'audit_phase', 'current_owner', 'audit_type'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        return \db()->query("SELECT DISTINCT {$column} AS value FROM audits WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC")->fetchAll();
    }

    public function itemStatusOptions(): array
    {
        $rows = \db()->query('SELECT control_body_status FROM audit_items ORDER BY item_order ASC')->fetchAll();
        $labels = [];
        foreach ($rows as $row) {
            $label = $this->classifyItemStatus((string) ($row['control_body_status'] ?? ''));
            $labels[$label] = true;
        }

        $result = array_map(static fn (string $label): array => ['value' => $label], array_keys($labels));
        usort($result, static fn (array $a, array $b) => strcmp($a['value'], $b['value']));

        return $result;
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
            'audit_year' => 'a.audit_year',
            'process_status' => 'a.process_status',
            'requesting_body' => 'a.requesting_body',
            'theme' => 'a.theme',
            'classification' => 'a.classification',
            'current_owner' => 'a.current_owner',
            'audit_type' => 'a.audit_type',
        ] as $filter => $column) {
            $values = $this->normalizeFilterValues($filters[$filter] ?? '');
            if ($values === []) {
                continue;
            }

            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $where[] = "{$column} IN ({$placeholders})";
            array_push($params, ...$values);
        }

        $phases = $this->normalizeFilterValues($filters['audit_phase'] ?? '');
        if ($phases !== []) {
            $phaseClauses = [];
            foreach ($phases as $phase) {
                if ($phase === 'Em diligência') {
                    $phaseClauses[] = 'UPPER(a.audit_phase) LIKE ?';
                    $params[] = '%DILIG%';
                } else {
                    $phaseClauses[] = 'a.audit_phase = ?';
                    $params[] = $phase;
                }
            }
            $where[] = '(' . implode(' OR ', $phaseClauses) . ')';
        }

        $itemKinds = $this->normalizeFilterValues($filters['item_kind'] ?? '');
        if ($itemKinds !== []) {
            $allKinds = [];
            foreach ($itemKinds as $itemKind) {
                $accented = match ($itemKind) {
                    'DETERMINACAO' => 'DETERMINAÃ‡ÃƒO',
                    'RECOMENDACAO' => 'RECOMENDAÃ‡ÃƒO',
                    'CIENCIA' => 'CIÃŠNCIA',
                    default => $itemKind,
                };
                $allKinds[] = $itemKind;
                $allKinds[] = $accented;
            }
            $allKinds = array_values(array_unique($allKinds));
            $placeholders = implode(', ', array_fill(0, count($allKinds), '?'));
            $where[] = "ai.item_kind IN ({$placeholders})";
            array_push($params, ...$allKinds);
        }

        $itemStatuses = $this->normalizeFilterValues($filters['item_status_group'] ?? '');
        if ($itemStatuses !== []) {
            $statusClauses = [];
            foreach ($itemStatuses as $status) {
                [$sql, $sqlParams] = $this->buildItemStatusClause($status);
                $statusClauses[] = $sql;
                array_push($params, ...$sqlParams);
            }
            $where[] = '(' . implode(' OR ', $statusClauses) . ')';
        }

        return [$where, $params];
    }

    private function buildAuditScope(array $filters, bool $withItems = false): array
    {
        $joins = $withItems
            || $this->normalizeFilterValues($filters['item_kind'] ?? '') !== []
            || $this->normalizeFilterValues($filters['item_status_group'] ?? '') !== []
            ? ' INNER JOIN audit_items ai ON ai.audit_id = a.id'
            : '';
        [$where, $params] = $this->buildFilters($filters);

        return [$joins, $where, $params];
    }

    private function countPhaseLike(array $patterns, array $filters = []): int
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters);
        $clauses = [];
        $phaseParams = [];
        foreach ($patterns as $pattern) {
            $clauses[] = 'UPPER(a.audit_phase) LIKE ?';
            $phaseParams[] = strtoupper($pattern);
        }

        $sql = 'SELECT COUNT(DISTINCT a.id) FROM audits a' . $joins . ' WHERE (' . implode(' OR ', $clauses) . ')';
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = \db()->prepare($sql);
        $stmt->execute(array_merge($phaseParams, $params));
        return (int) $stmt->fetchColumn();
    }

    private function countRdcItems(array $filters = []): int
    {
        [$joins, $where, $params] = $this->buildAuditScope($filters, true);
        $sql = 'SELECT COUNT(ai.id) FROM audits a' . $joins . '
            WHERE ai.item_kind IN ("DETERMINACAO", "DETERMINAÃ‡ÃƒO", "RECOMENDACAO", "RECOMENDAÃ‡ÃƒO", "CIENCIA", "CIÃŠNCIA")';
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $stmt = \db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function classifyItemStatus(string $value): string
    {
        $token = $this->normalizeToken($value);

        return match (true) {
            $token === '' || $token === 'NAO' || $token === 'NAINFORMADA' || $token === 'NAOAPLICA' || $token === 'N/A' => 'Não informada',
            str_contains($token, 'PERDADEOBJETO') => 'Perda de objeto',
            str_contains($token, 'IMPLEMENTADA') || str_contains($token, 'CUMPRIDA') => 'Implementada',
            str_contains($token, 'EMIMPLEMENTACAO') || str_contains($token, 'IMPLEMENTACAO') => 'Em implementação',
            default => trim($value) !== '' ? trim($value) : 'Não informada',
        };
    }

    private function phaseBucket(string $phase): string
    {
        $token = $this->normalizeToken($phase);

        return match (true) {
            $token === '' => 'Não informado',
            str_contains($token, 'DILIGENC') => 'Em diligência',
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

    private function normalizeFilterValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value
            ), static fn (string $item): bool => $item !== ''));
        }

        $scalar = trim((string) $value);
        return $scalar === '' ? [] : [$scalar];
    }

    private function buildItemStatusClause(string $status): array
    {
        return match ($status) {
            'Implementada' => ['(UPPER(ai.control_body_status) LIKE ? OR UPPER(ai.control_body_status) LIKE ?)', ['%IMPLEMENTADA%', '%CUMPRIDA%']],
            'Em implementação' => ['(UPPER(ai.control_body_status) LIKE ? OR UPPER(ai.control_body_status) LIKE ? OR UPPER(ai.control_body_status) LIKE ?)', ['%EM IMPLEMENT%', '%IMPLEMENTACAO%', '%IMPLEMENTAÇÃO%']],
            'Perda de objeto' => ['(UPPER(ai.control_body_status) LIKE ? OR UPPER(ai.control_body_status) LIKE ?)', ['%PERDA DE OBJETO%', '%PERDADEOBJETO%']],
            'Não informada' => ['(ai.control_body_status IS NULL OR TRIM(ai.control_body_status) = "" OR UPPER(ai.control_body_status) IN ("NAO", "NÃO", "N/A", "NAO INFORMADA", "NÃO INFORMADA", "NAO APLICA", "NÃO APLICA"))', []],
            default => ['ai.control_body_status = ?', [$status]],
        };
    }
}
