<?php

declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

class InssMonitoringRepository
{
    public const BASE_COLUMNS = [
        'external_id',
        'sei_process',
        'sent_office_number',
        'sent_office_sei',
        'office_date',
        'sent_to_inss_date',
        'inss_recipient_unit',
        'subject',
        'demand_type',
        'demand_origin',
        'beneficiary',
        'cpf',
        'benefit_number',
    ];

    public const TRACKING_COLUMNS = [
        'deadline_status',
        'status',
        'inss_response_date',
        'response_office_number',
        'response_sei',
        'conclusive_response',
        'needs_follow_up',
        'follow_up_date',
        'follow_up_count',
        'owner',
        'priority',
        'notes',
        'sei_link',
    ];

    public const FILTER_COLUMNS = [
        'deadline_status',
        'status',
        'inss_response_date',
        'response_office_number',
        'response_sei',
        'conclusive_response',
        'needs_follow_up',
        'follow_up_date',
        'follow_up_count',
        'owner',
        'priority',
    ];

    public function __construct()
    {
        $this->ensureTable();
    }

    public function find(int $id): ?array
    {
        $statement = \db()->prepare($this->selectSql() . ' WHERE m.id = ?');
        $statement->execute([$id]);
        $record = $statement->fetch();

        return $record ?: null;
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        [$whereSql, $params] = $this->whereSql($filters);
        $count = \db()->prepare('SELECT COUNT(*) FROM inss_monitoring m' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . $whereSql . " ORDER BY
            CASE
                WHEN UPPER(COALESCE(m.priority, '')) LIKE 'ALTA%' THEN 0
                WHEN UPPER(COALESCE(m.priority, '')) LIKE 'MÉDIA%' OR UPPER(COALESCE(m.priority, '')) LIKE 'MEDIA%' THEN 1
                WHEN UPPER(COALESCE(m.priority, '')) LIKE 'BAIXA%' THEN 2
                ELSE 3
            END,
            CASE
                WHEN UPPER(COALESCE(m.deadline_status, '')) LIKE '%VENC%' OR UPPER(COALESCE(m.deadline_status, '')) LIKE '%ATRAS%' THEN 0
                WHEN UPPER(COALESCE(m.deadline_status, '')) LIKE '%ATEN%' THEN 1
                ELSE 2
            END,
            m.inss_response_date IS NOT NULL,
            elapsed_days DESC,
            m.id DESC
            LIMIT {$perPage} OFFSET {$offset}";
        $statement = \db()->prepare($sql);
        $statement->execute($params);

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function dashboardMetrics(array $filters): array
    {
        [$whereSql, $params] = $this->whereSql($filters);
        $statement = \db()->prepare("SELECT
            COUNT(*) AS total,
            SUM(m.inss_response_date IS NULL AND UPPER(COALESCE(m.status, '')) NOT LIKE '%CONCLU%') AS awaiting_response,
            SUM(m.inss_response_date IS NOT NULL OR NULLIF(m.response_office_number, '') IS NOT NULL OR NULLIF(m.response_sei, '') IS NOT NULL) AS with_response,
            SUM(UPPER(COALESCE(m.deadline_status, '')) LIKE '%VENC%' OR UPPER(COALESCE(m.deadline_status, '')) LIKE '%ATRAS%') AS overdue,
            SUM(UPPER(COALESCE(m.needs_follow_up, '')) LIKE 'SIM%' OR UPPER(COALESCE(m.status, '')) LIKE '%COBRAN%') AS needs_follow_up,
            SUM(UPPER(COALESCE(m.priority, '')) LIKE 'ALTA%') AS high_priority
            FROM inss_monitoring m" . $whereSql);
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        return array_map('intval', array_merge([
            'total' => 0,
            'awaiting_response' => 0,
            'with_response' => 0,
            'overdue' => 0,
            'needs_follow_up' => 0,
            'high_priority' => 0,
        ], $row));
    }

    public function groupCounts(string $field, array $filters, int $limit = 8): array
    {
        if (!in_array($field, ['deadline_status', 'status', 'owner', 'priority', 'demand_type'], true)) {
            return [];
        }

        [$whereSql, $params] = $this->whereSql($filters);
        $statement = \db()->prepare(
            "SELECT COALESCE(NULLIF(TRIM(m.{$field}), ''), 'Não informado') AS label, COUNT(*) AS total
             FROM inss_monitoring m{$whereSql}
             GROUP BY label ORDER BY total DESC, label ASC LIMIT " . max(1, min(20, $limit))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function distinctValues(string $field): array
    {
        if (!in_array($field, self::FILTER_COLUMNS, true)) {
            return [];
        }

        $statement = \db()->query(
            "SELECT DISTINCT TRIM(CAST({$field} AS CHAR)) AS value
             FROM inss_monitoring
             WHERE {$field} IS NOT NULL AND TRIM(CAST({$field} AS CHAR)) <> ''
             ORDER BY value"
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) $row['value'],
            $statement->fetchAll()
        )));
    }

    public function ownerOptions(): array
    {
        $databaseOwners = $this->distinctValues('owner');
        $users = \db()->query('SELECT name FROM users WHERE active = 1 ORDER BY name')->fetchAll();
        $names = array_merge($databaseOwners, array_map(
            static fn (array $row): string => trim((string) $row['name']),
            $users
        ));
        $names = array_values(array_unique(array_filter($names)));
        natcasesort($names);

        return array_values($names);
    }

    public function save(array $data, ?int $id, int $userId): int
    {
        $columns = array_merge(self::BASE_COLUMNS, self::TRACKING_COLUMNS);
        $clean = [];
        foreach ($columns as $column) {
            $clean[$column] = trim(str_replace("\0", '', (string) ($data[$column] ?? '')));
        }

        if ($clean['sei_process'] === '') {
            throw new RuntimeException('Informe o número do processo SEI.');
        }

        foreach (['office_date', 'sent_to_inss_date', 'inss_response_date', 'follow_up_date'] as $dateColumn) {
            $clean[$dateColumn] = $this->validatedDate($clean[$dateColumn]);
        }

        if ($clean['follow_up_count'] === '') {
            $clean['follow_up_count'] = null;
        } elseif (filter_var($clean['follow_up_count'], FILTER_VALIDATE_INT) === false || (int) $clean['follow_up_count'] < 0) {
            throw new RuntimeException('A quantidade de cobranças deve ser um número igual ou maior que zero.');
        } else {
            $clean['follow_up_count'] = (int) $clean['follow_up_count'];
        }

        if ($clean['external_id'] === '') {
            $clean['external_id'] = null;
        } elseif (filter_var($clean['external_id'], FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('O ID informado é inválido.');
        } else {
            $clean['external_id'] = (int) $clean['external_id'];
        }

        $clean['sei_link'] = $this->normalizeLink($clean['sei_link']);
        $values = array_map(static fn (string $column): mixed => $clean[$column], $columns);

        if ($id !== null) {
            if ($this->find($id) === null) {
                throw new RuntimeException('Registro de monitoramento não encontrado.');
            }
            $assignments = array_map(static fn (string $column): string => $column . ' = ?', $columns);
            array_push($values, $userId, $id);
            $statement = \db()->prepare(
                'UPDATE inss_monitoring SET ' . implode(', ', $assignments)
                . ', updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            $statement->execute($values);

            return $id;
        }

        $insertColumns = array_merge(['source_key'], $columns, ['created_by', 'updated_by']);
        $insertValues = array_merge(
            [hash('sha256', 'manual|' . bin2hex(random_bytes(24)))],
            $values,
            [$userId, $userId]
        );
        $statement = \db()->prepare(
            'INSERT INTO inss_monitoring (' . implode(', ', $insertColumns) . ') VALUES ('
            . implode(', ', array_fill(0, count($insertColumns), '?')) . ')'
        );
        $statement->execute($insertValues);

        return (int) \db()->lastInsertId();
    }

    public function upsertImported(array $data, int $sourceRow, int $userId): void
    {
        $columns = array_merge(self::BASE_COLUMNS, self::TRACKING_COLUMNS);
        $identity = mb_strtolower(trim((string) ($data['sei_process'] ?? '')), 'UTF-8') . '|'
            . mb_strtolower(trim((string) ($data['sent_office_number'] ?? '')), 'UTF-8');
        $sourceKey = hash('sha256', $identity);
        $insertColumns = array_merge(['source_key', 'source_row'], $columns, ['created_by', 'updated_by']);
        $values = [$sourceKey, $sourceRow];
        foreach ($columns as $column) {
            $values[] = $data[$column] ?? null;
        }
        array_push($values, $userId, $userId);

        $updates = [];
        foreach (self::BASE_COLUMNS as $column) {
            $updates[] = $column . ' = COALESCE(VALUES(' . $column . '), ' . $column . ')';
        }
        foreach (self::TRACKING_COLUMNS as $column) {
            $updates[] = $column . ' = COALESCE(VALUES(' . $column . '), ' . $column . ')';
        }
        $updates[] = 'source_row = VALUES(source_row)';
        $updates[] = 'updated_by = VALUES(updated_by)';
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';

        $statement = \db()->prepare(
            'INSERT INTO inss_monitoring (' . implode(', ', $insertColumns) . ') VALUES ('
            . implode(', ', array_fill(0, count($insertColumns), '?')) . ') '
            . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
        );
        $statement->execute($values);
    }

    private function whereSql(array $filters): array
    {
        $where = [];
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = "CONCAT_WS(' ', m.sei_process, m.sent_office_number, m.sent_office_sei, m.subject,
                m.demand_type, m.demand_origin, m.beneficiary, m.cpf, m.benefit_number,
                m.response_office_number, m.response_sei, m.notes) LIKE ?";
            $params[] = '%' . $query . '%';
        }

        foreach (self::FILTER_COLUMNS as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($value === '__empty__') {
                $where[] = "(m.{$column} IS NULL OR TRIM(CAST(m.{$column} AS CHAR)) = '')";
            } else {
                $where[] = "CAST(m.{$column} AS CHAR) = ?";
                $params[] = $value;
            }
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function selectSql(): string
    {
        return 'SELECT m.*, DATEDIFF(COALESCE(m.inss_response_date, CURDATE()), m.sent_to_inss_date) AS elapsed_days
            FROM inss_monitoring m';
    }

    private function validatedDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Uma das datas informadas é inválida.');
        }

        return $value;
    }

    private function normalizeLink(string $link): ?string
    {
        if ($link === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $link)) {
            $link = 'https://' . ltrim($link, '/');
        }
        if (filter_var($link, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Informe um link SEI válido.');
        }

        return $link;
    }

    private function ensureTable(): void
    {
        \db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS inss_monitoring (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_key CHAR(64) NOT NULL UNIQUE,
    source_row INT UNSIGNED NULL,
    external_id INT UNSIGNED NULL,
    sei_process VARCHAR(80) NOT NULL,
    sent_office_number VARCHAR(100) NULL,
    sent_office_sei VARCHAR(80) NULL,
    office_date DATE NULL,
    sent_to_inss_date DATE NULL,
    inss_recipient_unit TEXT NULL,
    subject TEXT NULL,
    demand_type VARCHAR(255) NULL,
    demand_origin TEXT NULL,
    beneficiary TEXT NULL,
    cpf VARCHAR(255) NULL,
    benefit_number VARCHAR(120) NULL,
    deadline_status VARCHAR(80) NULL,
    status VARCHAR(100) NULL,
    inss_response_date DATE NULL,
    response_office_number VARCHAR(100) NULL,
    response_sei VARCHAR(80) NULL,
    conclusive_response VARCHAR(40) NULL,
    needs_follow_up VARCHAR(40) NULL,
    follow_up_date DATE NULL,
    follow_up_count INT UNSIGNED NULL,
    owner VARCHAR(160) NULL,
    priority VARCHAR(40) NULL,
    notes LONGTEXT NULL,
    sei_link TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inss_process (sei_process),
    INDEX idx_inss_status (status),
    INDEX idx_inss_deadline (deadline_status),
    INDEX idx_inss_owner (owner),
    INDEX idx_inss_priority (priority),
    INDEX idx_inss_sent_date (sent_to_inss_date),
    CONSTRAINT fk_inss_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_inss_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
    }
}
