<?php
declare(strict_types=1);

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri === false || $uri === null) {
    $uri = '/';
}
$uri = rawurldecode($uri);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$basePath = rawurldecode($basePath);

if ($basePath !== '') {
    if ($uri === $basePath || $uri === $basePath . '/') {
        $uri = '/';
    } elseif (strpos($uri, $basePath . '/') === 0) {
        $uri = substr($uri, strlen($basePath));
    }
}
if ($uri === '') {
    $uri = '/';
}
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

$config = require __DIR__ . '/../Config/config.php';
$pdo = \App\Core\Database::connect($config['db']);

(new \App\Core\MigrationRunner($pdo))->run();

$heroRepository = new \App\Infrastructure\Persistence\MySqlHeroSlideRepository($pdo);
$adminHeroService = new \App\Application\Services\AdminHeroService($heroRepository);

$categoryRepository = new \App\Infrastructure\Persistence\MySqlCategoryRepository($pdo);
$productRepository = new \App\Infrastructure\Persistence\MySqlProductRepository($pdo);
$reviewRepository = new \App\Infrastructure\Persistence\MySqlReviewRepository($pdo);

$adminProductService = new \App\Application\Services\AdminProductService($productRepository, $categoryRepository);
$adminCategoryService = new \App\Application\Services\AdminCategoryService($categoryRepository);
$adminReviewService = new \App\Application\Services\AdminReviewService($reviewRepository);

$authConfig = $config['admin'] ?? [];
$authService = new \App\Application\Services\AdminAuthService($authConfig);
$authController = new \App\Infrastructure\Http\Controllers\AdminAuthController($authService);

$dashboardController = new \App\Infrastructure\Http\Controllers\AdminDashboardController($adminHeroService, $adminProductService, $adminCategoryService, $adminReviewService);
$heroController = new \App\Infrastructure\Http\Controllers\AdminHeroController($adminHeroService);
$productController = new \App\Infrastructure\Http\Controllers\AdminProductController($adminProductService);
$categoryController = new \App\Infrastructure\Http\Controllers\AdminCategoryController($adminCategoryService);
$reviewController = new \App\Infrastructure\Http\Controllers\AdminReviewController($adminReviewService);

$action = 'dashboard';

$parts = explode('/', ltrim($uri, '/'));
if ($parts[0] === 'admin') {
    array_shift($parts);
}

if ($parts !== ['']) {
    $action = $parts[0] ?? 'dashboard';
}

// Public routes (no auth required)
if ($action === 'login') {
    $authController->showLogin();
    exit;
}

if ($action === 'logout') {
    $authController->logout();
    exit;
}

// All other routes require auth
if (!$authService->isLoggedIn()) {
    header('Location: /admin/login');
    exit;
}

switch ($action) {
    case '':
    case 'dashboard':
        $dashboardController->index();
        break;

    case 'hero':
        $subAction = $parts[1] ?? 'index';
        switch ($subAction) {
            case 'create':
                $heroController->create();
                break;
            case 'edit':
                $heroController->edit();
                break;
            case 'delete':
                $heroController->delete();
                break;
            default:
                $heroController->index();
                break;
        }
        break;

    case 'products':
        $subAction = $parts[1] ?? 'index';
        switch ($subAction) {
            case 'create':
                $productController->create();
                break;
            case 'edit':
                $productController->edit();
                break;
            case 'delete':
                $productController->delete();
                break;
            default:
                $productController->index();
                break;
        }
        break;

    case 'categories':
        $subAction = $parts[1] ?? 'index';
        switch ($subAction) {
            case 'create':
                $categoryController->create();
                break;
            case 'edit':
                $categoryController->edit();
                break;
            case 'delete':
                $categoryController->delete();
                break;
            default:
                $categoryController->index();
                break;
        }
        break;

    case 'reviews':
        $subAction = $parts[1] ?? 'index';
        switch ($subAction) {
            case 'create':
                $reviewController->create();
                break;
            case 'edit':
                $reviewController->edit();
                break;
            case 'delete':
                $reviewController->delete();
                break;
            case 'visibility':
                $reviewController->visibility();
                break;
            default:
                $reviewController->index();
                break;
        }
        break;

    default:
        http_response_code(404);
        echo 'Admin page not found';
        break;
}