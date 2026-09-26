<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Core\StaticPage;

abstract class AdminController
{
    protected function render(string $template, array $placeholders = []): string
    {
        $path = dirname(__DIR__, 4) . '/public/admin/' . $template;
        $html = StaticPage::render($path);

        foreach ($placeholders as $key => $value) {
            $html = str_replace('<!--' . $key . '-->', $value, $html);
        }

        return $html;
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    protected function getFlash(): ?array
    {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    protected function csToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($this->csToken(), $token)) {
            http_response_code(403);
            exit('CSRF token validation failed');
        }
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
    }
}