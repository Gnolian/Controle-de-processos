<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProcessRepository
{
    public const COLUMNS = [
        'process_number',
        'updated_at',
        'response_owner',
        'deadline_days',
        'deadline_type',
        'general_description',
        'detailed_description',
        'notes',
        'requesting_agency',
        'gab_signature_date',
        'internal_deadline_gab',
        'adjusted_internal_deadline',
        'external_deadline_mds',
        'review_owner',
        'response_status',
        'andrea_review_status',
        'signed_status',
        'sent_gab_status',
        'gab_sent_date',
        'status',
        'internal_block',
    ];

    public const EXPORT_HEADERS = [
        'Numero do Processo',
        'DATA DA ATUALIZACAO',
        'Responsavel pela Resposta',
        'Prazo (em dias)',
        'Tipo de prazo',
        'Descricao Geral',
        'Descricao Detalhada',
        'Comentarios/anotacoes',
        'Orgao Solicitante',
        'Data de assinatura (Oficio GAB)',
        'Prazo Interno (OFICIO GAB/SNBA)',
        'Prazo Interno AJUSTADO',
        'Prazo Externo/MDS',
        'Responsavel pela Revisao',
        'Resposta',
        'Revisao Andrea',
        'Assinado',
        'Enviado Gab',
        'Data envio GAB',
        'STATUS',
        'Bloco interno',
    ];

    private ?bool $hasDeadlineType = null;

    public function search(array $filters, array $user, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $selectDeadlineType = $this->hasDeadlineType() ? '' : ", 'data' AS deadline_type";
        $sql = 'SELECT p.*, u.name AS created_by_name' . $selectDeadlineType . '
                FROM processes p
                LEFT JOIN users u ON u.id = p.created_by';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(p.external_deadline_mds, p.adjusted_internal_deadline, p.internal_deadline_gab) IS NULL,
                         COALESCE(p.external_deadline_mds, p.adjusted_internal_deadline, p.internal_deadline_gab) ASC,
                         p.updated_at DESC, p.id DESC
                  LIMIT ? OFFSET ?';

        $params[] = $limit;
        $params[] = $offset;
        $stmt = \db()->prepare($sql);
        foreach ($params as $index => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($index + 1, $value, $type);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters, array $user): int
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $sql = 'SELECT COUNT(*) FROM processes p';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = \db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function allForExport(array $filters, array $user): array
    {
        return $this->search($filters, $user, 5000, 0);
    }

    public function find(int $id, array $user): ?array
    {
        $selectDeadlineType = $this->hasDeadlineType() ? '' : ", 'data' AS deadline_type";
        $stmt = \db()->prepare('SELECT p.*, u.name AS created_by_name' . $selectDeadlineType . '
            FROM processes p
            LEFT JOIN users u ON u.id = p.created_by
            WHERE p.id = ?');
        $stmt->execute([$id]);
        $process = $stmt->fetch();

        if (!$process) {
            return null;
        }

        if (!\can_manage($user) && !$this->isAssignedToUser($process, $user)) {
            return null;
        }

        return $process;
    }

    public function findByNumber(string $processNumber): ?array
    {
        $stmt = \db()->prepare('SELECT * FROM processes WHERE process_number = ? LIMIT 1');
        $stmt->execute([$processNumber]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    public function create(array $payload, array $user): int
    {
        $columns = array_merge($this->databaseColumns(), ['created_by']);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO processes (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $values = array_map(static fn (string $column) => $payload[$column] ?? null, $this->databaseColumns());
        $values[] = $user['id'];
        \db()->prepare($sql)->execute($values);

        return (int) \db()->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sets = implode(', ', array_map(static fn (string $column) => $column . ' = ?', $this->databaseColumns()));
        $values = array_map(static fn (string $column) => $payload[$column] ?? null, $this->databaseColumns());
        $values[] = $id;
        \db()->prepare('UPDATE processes SET ' . $sets . ' WHERE id = ?')->execute($values);
    }

    public function delete(int $id): void
    {
        \db()->prepare('DELETE FROM processes WHERE id = ?')->execute([$id]);
    }

    public function buildPayload(array $data): array
    {
        $payload = [];
        foreach (self::COLUMNS as $column) {
            $payload[$column] = trim((string) ($data[$column] ?? ''));
        }

        foreach (['updated_at', 'gab_signature_date', 'internal_deadline_gab', 'adjusted_internal_deadline', 'external_deadline_mds', 'gab_sent_date'] as $dateColumn) {
            $payload[$dateColumn] = $payload[$dateColumn] !== '' ? $payload[$dateColumn] : null;
        }

        $payload['deadline_days'] = $payload['deadline_days'] !== '' ? (int) $payload['deadline_days'] : null;
        $hasAnyDeadline = $payload['internal_deadline_gab'] || $payload['adjusted_internal_deadline'] || $payload['external_deadline_mds'];
        $payload['deadline_type'] = $payload['deadline_type'] ?: ($hasAnyDeadline ? 'data' : 'tempo_habil');
        if (!in_array($payload['deadline_type'], ['data', 'tempo_habil'], true)) {
            $payload['deadline_type'] = 'data';
        }
        $payload['updated_at'] = $payload['updated_at'] ?: date('Y-m-d');
        $payload['response_status'] = $payload['response_status'] ?: 'A iniciar';
        $payload['andrea_review_status'] = $payload['andrea_review_status'] ?: 'N/A';
        $payload['signed_status'] = $payload['signed_status'] ?: 'N/A';
        $payload['sent_gab_status'] = $payload['sent_gab_status'] ?: 'N/A';
        $payload['status'] = $payload['status'] ?: 'Aberto';
        foreach (['response_status', 'andrea_review_status', 'signed_status', 'sent_gab_status'] as $field) {
            if (!in_array($payload[$field], \config('dropdowns.workflow'), true)) {
                $payload[$field] = 'N/A';
            }
        }
        if (!in_array($payload['status'], \config('dropdowns.status'), true)) {
            $payload['status'] = 'Aberto';
        }

        return $payload;
    }

    public function scopeWhere(array $user, string $alias = 'p'): string
    {
        if (\can_manage($user)) {
            return '';
        }

        return " WHERE ({$alias}.created_by = " . (int) $user['id'] . " OR {$alias}.response_owner LIKE " . \db()->quote('%' . $user['name'] . '%') . ')';
    }

    private function buildFilters(array $filters, array $user): array
    {
        $where = [];
        $params = [];

        if (!\can_manage($user)) {
            $where[] = '(p.created_by = ? OR p.response_owner LIKE ?)';
            $params[] = $user['id'];
            $params[] = '%' . $user['name'] . '%';
        }

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.process_number LIKE ? OR p.general_description LIKE ? OR p.detailed_description LIKE ? OR p.notes LIKE ? OR p.requesting_agency LIKE ? OR p.internal_block LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        foreach (['status' => 'p.status', 'response_status' => 'p.response_status', 'andrea_review_status' => 'p.andrea_review_status'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $params[] = $filters[$filter];
            }
        }

        if (($filters['requesting_agency'] ?? '') !== '') {
            $where[] = 'p.requesting_agency LIKE ?';
            $params[] = '%' . $filters['requesting_agency'] . '%';
        }

        if (($filters['owner'] ?? '') !== '') {
            $where[] = 'p.response_owner LIKE ?';
            $params[] = '%' . $filters['owner'] . '%';
        }

        if (($filters['deadline'] ?? '') === 'late') {
            $where[] = "p.status = 'Aberto' AND " . $this->dateDeadlineCondition('p') . " AND COALESCE(p.external_deadline_mds, p.adjusted_internal_deadline, p.internal_deadline_gab) < CURDATE()";
        }

        if (($filters['deadline'] ?? '') === 'tomorrow') {
            $where[] = "p.status = 'Aberto' AND " . $this->dateDeadlineCondition('p') . " AND COALESCE(p.external_deadline_mds, p.adjusted_internal_deadline, p.internal_deadline_gab) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        }

        if (($filters['deadline'] ?? '') === 'three_days') {
            $where[] = "p.status = 'Aberto' AND " . $this->dateDeadlineCondition('p') . " AND COALESCE(p.external_deadline_mds, p.adjusted_internal_deadline, p.internal_deadline_gab) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
        }

        if (($filters['deadline'] ?? '') === 'tempo_habil' && $this->hasDeadlineType()) {
            $where[] = "p.deadline_type = 'tempo_habil'";
        }

        return [$where, $params];
    }

    private function isAssignedToUser(array $process, array $user): bool
    {
        if ((int) $process['created_by'] === (int) $user['id']) {
            return true;
        }

        $owner = strtolower((string) ($process['response_owner'] ?? ''));
        $name = strtolower((string) ($user['name'] ?? ''));

        return $name !== '' && strpos($owner, $name) !== false;
    }

    private function databaseColumns(): array
    {
        if ($this->hasDeadlineType()) {
            return self::COLUMNS;
        }

        return array_values(array_filter(self::COLUMNS, static fn (string $column) => $column !== 'deadline_type'));
    }

    private function dateDeadlineCondition(string $alias): string
    {
        return $this->hasDeadlineType() ? "{$alias}.deadline_type = 'data'" : '1 = 1';
    }

    private function hasDeadlineType(): bool
    {
        if ($this->hasDeadlineType !== null) {
            return $this->hasDeadlineType;
        }

        $stmt = \db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['processes', 'deadline_type']);
        $this->hasDeadlineType = (int) $stmt->fetchColumn() > 0;

        return $this->hasDeadlineType;
    }
}
