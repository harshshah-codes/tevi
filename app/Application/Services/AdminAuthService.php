<?php
declare(strict_types=1);

namespace App\Application\Services;

final class AdminAuthService
{
    private string $username = 'admin';
    private string $passwordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password

    public function __construct(
        private readonly array $config = [],
    ) {
        if (isset($config['username'])) {
            $this->username = $config['username'];
        }
        if (isset($config['password_hash'])) {
            $this->passwordHash = $config['password_hash'];
        }
    }

    public function verify(string $username, string $password): bool
    {
        if ($username !== $this->username) {
            return false;
        }

        return password_verify($password, $this->passwordHash);
    }

    public function login(string $username, string $password): bool
    {
        if ($this->verify($username, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            session_regenerate_id(true);
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}