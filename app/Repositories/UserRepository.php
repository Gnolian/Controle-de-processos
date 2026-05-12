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
        if ($this->hasAuditOnlyColumn()) {
            $select .= ', audit_only';
        } else {
            $select .= ', 0 AS audit_only';
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

    public function findById(int $id): ?array
    {
        $select = 'SELECT id, name, email, role, active, created_at';
        if ($this->hasAuditAccessColumn()) {
            $select .= ', audit_access';
        } else {
            $select .= ', 0 AS audit_access';
        }
        if ($this->hasAuditOnlyColumn()) {
            $select .= ', audit_only';
        } else {
            $select .= ', 0 AS audit_only';
        }

        $stmt = \db()->prepare($select . ' FROM users WHERE id = ?');
        $stmt->execute([$id]);
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
        if ($this->hasAuditOnlyColumn()) {
            $select .= ', audit_only';
        } else {
            $select .= ', 0 AS audit_only';
        }

        return \db()->query($select . ' FROM users ORDER BY name')->fetchAll();
    }

    public function create(array $data): void
    {
        if ($this->hasAuditAccessColumn() && $this->hasAuditOnlyColumn()) {
            $stmt = \db()->prepare('INSERT INTO users (name, email, password_hash, role, audit_access, audit_only) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                trim((string) $data['name']),
                trim((string) $data['email']),
                password_hash((string) $data['password'], PASSWORD_DEFAULT),
                (string) $data['role'],
                !empty($data['audit_access']) ? 1 : 0,
                !empty($data['audit_only']) ? 1 : 0,
            ]);
            return;
        }

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

    public function updatePassword(int $id, string $password): void
    {
        $stmt = \db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public function updateAccess(int $id, array $data): void
    {
        $fields = ['role = ?', 'active = ?'];
        $params = [
            (string) $data['role'],
            !empty($data['active']) ? 1 : 0,
        ];

        if ($this->hasAuditAccessColumn()) {
            $fields[] = 'audit_access = ?';
            $params[] = !empty($data['audit_access']) ? 1 : 0;
        }

        if ($this->hasAuditOnlyColumn()) {
            $fields[] = 'audit_only = ?';
            $params[] = !empty($data['audit_only']) ? 1 : 0;
        }

        $params[] = $id;
        $stmt = \db()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
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

    private function hasAuditOnlyColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $stmt = \db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['users', 'audit_only']);
        $hasColumn = (int) $stmt->fetchColumn() > 0;

        return $hasColumn;
    }
}
