<?php

declare(strict_types=1);

namespace App\Services;

class DashboardService
{
    public function metrics(array $user): array
    {
        $scope = $this->scope($user);

        return [
            'total' => $this->count("SELECT COUNT(*) FROM processes{$scope}"),
            'open' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto'"),
            'done' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " response_status = 'Concluido'"),
            'archived' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status LIKE 'Arquivado%'"),
            'late' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto' AND deadline_type = 'data' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) < CURDATE()"),
            'due_tomorrow' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto' AND deadline_type = 'data' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)"),
            'due_soon' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto' AND deadline_type = 'data' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"),
            'review_pending' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " andrea_review_status IN ('A iniciar', 'Em andamento')"),
            'signature_pending' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " signed_status IN ('A iniciar', 'Em andamento')"),
            'by_status' => $this->group('status', $scope),
            'by_response_owner' => $this->group('response_owner', $scope),
            'by_agency' => $this->group('requesting_agency', $scope),
            'by_creator' => $this->groupCreator($scope),
            'open_by_owner' => $this->openByOwner(),
            'open_flow_by_owner' => $this->openFlowByOwner(),
            'review_by_owner' => $this->reviewByOwner(),
        ];
    }

    public function personalQueue(array $user): array
    {
        $stmt = \db()->prepare("SELECT * FROM processes WHERE (created_by = ? OR response_owner LIKE ?) AND status = 'Aberto' ORDER BY deadline_type = 'tempo_habil', COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) ASC LIMIT 8");
        $stmt->execute([$user['id'], '%' . $user['name'] . '%']);

        return $stmt->fetchAll();
    }

    private function count(string $sql): int
    {
        return (int) \db()->query($sql)->fetchColumn();
    }

    private function group(string $column, string $scope): array
    {
        $sql = "SELECT COALESCE(NULLIF({$column}, ''), 'Nao informado') AS label, COUNT(*) AS total FROM processes{$scope} GROUP BY label ORDER BY total DESC, label ASC LIMIT 10";
        return \db()->query($sql)->fetchAll();
    }

    private function groupCreator(string $scope): array
    {
        $sql = 'SELECT COALESCE(u.name, "Nao informado") AS label, COUNT(*) AS total FROM processes p LEFT JOIN users u ON u.id = p.created_by';
        if ($scope) {
            $sql .= str_replace('created_by', 'p.created_by', $scope);
        }
        $sql .= ' GROUP BY label ORDER BY total DESC, label ASC LIMIT 10';

        return \db()->query($sql)->fetchAll();
    }

    private function scope(array $user): string
    {
        if (\can_manage($user)) {
            return '';
        }

        return ' WHERE (created_by = ' . (int) $user['id'] . ' OR response_owner LIKE ' . \db()->quote('%' . $user['name'] . '%') . ')';
    }

    private function openByOwner(): array
    {
        return \db()->query("SELECT COALESCE(NULLIF(response_owner, ''), 'Nao informado') AS label, COUNT(*) AS total
            FROM processes
            WHERE status = 'Aberto'
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 12")->fetchAll();
    }

    private function openFlowByOwner(): array
    {
        return \db()->query("SELECT
                COALESCE(NULLIF(response_owner, ''), 'Nao informado') AS owner,
                SUM(response_status = 'A iniciar') AS a_iniciar,
                SUM(response_status = 'Em andamento') AS em_andamento,
                SUM(response_status = 'Concluido') AS concluido,
                SUM(andrea_review_status IN ('A iniciar', 'Em andamento')) AS revisao_pendente,
                COUNT(*) AS total
            FROM processes
            WHERE status = 'Aberto'
            GROUP BY owner
            ORDER BY total DESC, owner ASC
            LIMIT 12")->fetchAll();
    }

    private function reviewByOwner(): array
    {
        return \db()->query("SELECT COALESCE(NULLIF(review_owner, ''), 'Nao informado') AS label, COUNT(*) AS total
            FROM processes
            WHERE status = 'Aberto' AND andrea_review_status IN ('A iniciar', 'Em andamento')
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 12")->fetchAll();
    }
}
