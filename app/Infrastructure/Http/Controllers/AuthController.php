<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AuthService;
use Exception;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function login(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (!is_array($data)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Email and password are required']);
            exit;
        }

        try {
            $user = $this->authService->login($email, $password);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Login failed: ' . $e->getMessage()]);
            exit;
        }

        if ($user === null) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            exit;
        }

        // Start session if not already started (bootstrap already started it)
        $_SESSION['user_id'] = $user->id();
        // Optionally store user info in session
        $_SESSION['user_email'] = $user->email();
        $_SESSION['user_first_name'] = $user->firstName();
        $_SESSION['user_last_name'] = $user->lastName();
        $_SESSION['user_phone'] = $user->phone() ?? null;

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user->id(),
                'email' => $user->email(),
                'firstName' => $user->firstName(),
                'lastName' => $user->lastName(),
            ]
        ]);
        exit;
    }

    public function me(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        if (empty($_SESSION['user_id'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'] ?? '',
                'firstName' => $_SESSION['user_first_name'] ?? '',
                'lastName' => $_SESSION['user_last_name'] ?? '',
                'phone' => $_SESSION['user_phone'] ?? null,
            ]
        ]);
        exit;
    }

    public function register(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (!is_array($data)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $firstName = trim($data['firstName'] ?? '');
        $lastName = trim($data['lastName'] ?? '');
        $phone = trim($data['phone'] ?? null);

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Email, password, first name, and last name are required']);
            exit;
        }

        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            exit;
        }

        // Password length validation (optional)
        if (strlen($password) < 6) {
            ob_end_clean();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            exit;
        }

        try {
            $id = $this->authService->register([
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phone' => $phone === '' ? null : $phone,
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'userId' => $id
        ]);
        exit;
    }

    public function logout(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
    }
}