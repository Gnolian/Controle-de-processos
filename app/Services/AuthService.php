<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    public function attempt(string $email, string $password): bool
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if (!$user) {
            return false;
        }

        $valid = $this->passwordMatches($user, $password);
        if (!$valid) {
            return false;
        }

        $stored = (string) $user['password_hash'];
        if (strlen($stored) === 64) {
            $repo->rehashPassword((int) $user['id'], $password);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        return true;
    }

    public function changePassword(string $email, string $currentPassword, string $newPassword): void
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if (!$user || !$this->passwordMatches($user, $currentPassword)) {
            throw new \RuntimeException('Email ou senha atual inválidos.');
        }

        $repo->updatePassword((int) $user['id'], $newPassword);
    }

    private function passwordMatches(array $user, string $password): bool
    {
        $stored = (string) ($user['password_hash'] ?? '');
        return password_verify($password, $stored) || hash_equals(strtolower($stored), hash('sha256', $password));
    }
}
