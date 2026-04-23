<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository
{
    public function findActive(int $id): ?array
    {
        $select = 'SELECT id, name, email, role';
        if ($this->hasAuditAccessColumn()) {
            $select .= ', audit_access';
        } else {
            $select .= ', 0 AS audit_access';
        }
        $stmt = \db()->prepare($select . ' FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = \db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function all(): array
    {
        $select = 'SELECT id, name, email, role, active, created_at';
        if ($this->hasAuditAccessColumn()) {
            $select .= ', audit_access';
        } else {
            $select .= ', 0 AS audit_access';
        }

        return \db()->query($select . ' FROM users ORDER BY name')->fetchAll();
    }

    public function create(array $data): void
    {
        if ($this->hasAuditAccessColumn()) {
            $stmt = \db()->prepare('INSERT INTO users (name, email, password_hash, role, audit_access) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                trim((string) $data['name']),
                trim((string) $data['email']),
                password_hash((string) $data['password'], PASSWORD_DEFAULT),
                (string) $data['role'],
                !empty($data['audit_access']) ? 1 : 0,
            ]);
            return;
        }

        $stmt = \db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            trim((string) $data['name']),
            trim((string) $data['email']),
            password_hash((string) $data['password'], PASSWORD_DEFAULT),
            (string) $data['role'],
        ]);
    }

    public function rehashPassword(int $id, string $password): void
    {
        $stmt = \db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    private function hasAuditAccessColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $stmt = \db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['users', 'audit_access']);
        $hasColumn = (int) $stmt->fetchColumn() > 0;

        return $hasColumn;
    }
}
