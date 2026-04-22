<?php

declare(strict_types=1);

namespace App\Services;

class DashboardService
{
    public function metrics(array $user): array
    {
        $scope = \can_manage($user) ? '' : ' WHERE created_by = ' . (int) $user['id'];

        return [
            'total' => $this->count("SELECT COUNT(*) FROM processes{$scope}"),
            'open' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto'"),
            'done' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " response_status = 'Concluido'"),
            'archived' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status LIKE 'Arquivado%'"),
            'late' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) < CURDATE()"),
            'due_soon' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " status = 'Aberto' AND COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'review_pending' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " andrea_review_status IN ('A iniciar', 'Em andamento')"),
            'signature_pending' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " signed_status IN ('A iniciar', 'Em andamento')"),
            'gab_pending' => $this->count("SELECT COUNT(*) FROM processes{$scope}" . ($scope ? ' AND' : ' WHERE') . " sent_gab_status IN ('A iniciar', 'Em andamento')"),
            'by_status' => $this->group('status', $scope),
            'by_response_owner' => $this->group('response_owner', $scope),
            'by_agency' => $this->group('requesting_agency', $scope),
            'by_creator' => $this->groupCreator($scope),
        ];
    }

    public function personalQueue(array $user): array
    {
        $stmt = \db()->prepare("SELECT * FROM processes WHERE created_by = ? AND status = 'Aberto' ORDER BY COALESCE(external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab) ASC LIMIT 8");
        $stmt->execute([$user['id']]);

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
}

