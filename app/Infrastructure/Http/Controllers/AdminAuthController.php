<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminAuthService;
use App\Core\StaticPage;

final class AdminAuthController
{
    public function __construct(
        private readonly AdminAuthService $authService,
    ) {
    }

    public function showLogin(): void
    {
        if ($this->authService->isLoggedIn()) {
            header('Location: /admin/');
            exit;
        }

        $flash = $this->getFlash();
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($this->authService->login($username, $password)) {
                $this->flash('Welcome back!');
                header('Location: /admin/');
                exit;
            } else {
                $error = '<div class="alert alert-danger">Invalid username or password</div>';
            }
        }

        $html = str_replace(
            ['<!--FLASH-->', '<!--ERROR-->'],
            [
                $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
                $error,
            ],
            StaticPage::render(dirname(__DIR__, 4) . '/public/admin/login.php')
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    public function logout(): void
    {
        $this->authService->logout();
        header('Location: /admin/login');
        exit;
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    private function getFlash(): ?array
    {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}