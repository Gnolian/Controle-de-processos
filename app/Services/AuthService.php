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

        $stored = (string) $user['password_hash'];
        $valid = password_verify($password, $stored) || hash_equals(strtolower($stored), hash('sha256', $password));
        if (!$valid) {
            return false;
        }

        if (strlen($stored) === 64) {
            $repo->rehashPassword((int) $user['id'], $password);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        return true;
    }
}

