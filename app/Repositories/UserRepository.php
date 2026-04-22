<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository
{
    public function findActive(int $id): ?array
    {
        $stmt = \db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND active = 1');
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
        return \db()->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY name')->fetchAll();
    }

    public function create(array $data): void
    {
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
}

