<?php

declare(strict_types=1);

class ProcessRepository
{
    public const COLUMNS = [
        'process_number',
        'updated_at',
        'response_owner',
        'deadline_days',
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

    public function search(array $filters, array $user): array
    {
        $where = [];
        $params = [];

        if (!can_manage($user)) {
            $where[] = 'created_by = ?';
            $params[] = $user['id'];
        }

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(process_number LIKE ? OR general_description LIKE ? OR detailed_description LIKE ? OR notes LIKE ? OR requesting_agency LIKE ? OR internal_block LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (($filters['owner'] ?? '') !== '') {
            $where[] = 'response_owner LIKE ?';
            $params[] = '%' . $filters['owner'] . '%';
        }

        $sql = 'SELECT p.*, u.name AS created_by_name FROM processes p LEFT JOIN users u ON u.id = p.created_by';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) IS NULL, COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) ASC, updated_at DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id, array $user): ?array
    {
        $stmt = db()->prepare('SELECT * FROM processes WHERE id = ?');
        $stmt->execute([$id]);
        $process = $stmt->fetch();

        if (!$process) {
            return null;
        }

        if (!can_manage($user) && (int) $process['created_by'] !== (int) $user['id']) {
            return null;
        }

        return $process;
    }

    public function save(array $data, array $user, ?int $id = null): void
    {
        $payload = $this->sanitize($data);

        if ($id === null) {
            $columns = array_merge(self::COLUMNS, ['created_by']);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO processes (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $values = array_map(static fn (string $column) => $payload[$column], self::COLUMNS);
            $values[] = $user['id'];
            db()->prepare($sql)->execute($values);
            return;
        }

        $process = $this->find($id, $user);
        if (!$process) {
            throw new RuntimeException('Processo nao encontrado.');
        }

        $sets = implode(', ', array_map(static fn (string $column) => $column . ' = ?', self::COLUMNS));
        $values = array_map(static fn (string $column) => $payload[$column], self::COLUMNS);
        $values[] = $id;
        db()->prepare('UPDATE processes SET ' . $sets . ' WHERE id = ?')->execute($values);
    }

    public function delete(int $id, array $user): void
    {
        $process = $this->find($id, $user);
        if (!$process) {
            throw new RuntimeException('Processo nao encontrado.');
        }

        db()->prepare('DELETE FROM processes WHERE id = ?')->execute([$id]);
    }

    public function metrics(array $user): array
    {
        $scope = can_manage($user) ? '' : ' WHERE created_by = ' . (int) $user['id'];

        return [
            'total' => (int) db()->query('SELECT COUNT(*) FROM processes' . $scope)->fetchColumn(),
            'open' => (int) db()->query("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? " AND" : " WHERE") . " status = 'Aberto'")->fetchColumn(),
            'late' => (int) db()->query("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? " AND" : " WHERE") . " status = 'Aberto' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) < CURDATE()")->fetchColumn(),
            'due_soon' => (int) db()->query("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? " AND" : " WHERE") . " status = 'Aberto' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
            'by_status' => $this->groupBy('status', $scope),
            'by_response_owner' => $this->groupBy('response_owner', $scope),
            'by_creator' => $this->groupBy('u.name', $scope, true),
        ];
    }

    private function groupBy(string $column, string $scope, bool $joinUser = false): array
    {
        $from = $joinUser ? 'processes p LEFT JOIN users u ON u.id = p.created_by' : 'processes';
        $prefix = $joinUser ? 'p.' : '';
        $sql = "SELECT COALESCE(NULLIF({$column}, ''), 'Nao informado') AS label, COUNT(*) AS total FROM {$from}";
        if ($scope) {
            $sql .= str_replace('created_by', $prefix . 'created_by', $scope);
        }
        $sql .= " GROUP BY label ORDER BY total DESC, label ASC";

        return db()->query($sql)->fetchAll();
    }

    private function sanitize(array $data): array
    {
        $payload = [];
        foreach (self::COLUMNS as $column) {
            $payload[$column] = trim((string) ($data[$column] ?? ''));
        }

        foreach (['updated_at', 'gab_signature_date', 'internal_deadline_gab', 'adjusted_internal_deadline', 'external_deadline_mds', 'gab_sent_date'] as $dateColumn) {
            $payload[$dateColumn] = $payload[$dateColumn] !== '' ? $payload[$dateColumn] : null;
        }

        $payload['deadline_days'] = $payload['deadline_days'] !== '' ? (int) $payload['deadline_days'] : null;

        return $payload;
    }
}

