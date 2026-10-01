<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Application\Services\CatalogService;
use App\Application\Services\HeroCarouselService;
use App\Application\Services\OrderService;
use App\Application\Services\AuthService;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\CatalogController;
use App\Infrastructure\Http\Controllers\HeroCarouselController;
use App\Infrastructure\Http\Controllers\HomeController;
use App\Infrastructure\Http\Controllers\OrderController;
use App\Infrastructure\Http\Controllers\ProductController;
use App\Infrastructure\Persistence\MySqlCategoryRepository;
use App\Infrastructure\Persistence\MySqlHeroSlideRepository;
use App\Infrastructure\Persistence\MySqlOrderRepository;
use App\Infrastructure\Persistence\MySqlProductRepository;
use App\Infrastructure\Persistence\MySqlReviewRepository;
use App\Infrastructure\Persistence\MySqlUserRepository;

$config = require __DIR__ . '/Config/config.php';

$pdo = Database::connect($config['db']);

(new MigrationRunner($pdo))->run();

$heroRepository = new MySqlHeroSlideRepository($pdo);
$heroService = new HeroCarouselService($heroRepository);
$heroController = new HeroCarouselController($heroService);

$categoryRepository = new MySqlCategoryRepository($pdo);
$productRepository = new MySqlProductRepository($pdo);
$reviewRepository = new MySqlReviewRepository($pdo);
$catalogService = new CatalogService($productRepository, $categoryRepository, $reviewRepository);
$catalogController = new CatalogController($catalogService);

$productController = new ProductController($catalogService);

$homeController = new HomeController($heroController, $catalogController);

$orderRepository = new MySqlOrderRepository($pdo);
$orderService = new OrderService($orderRepository);
$orderController = new OrderController($orderService);

$userRepository = new MySqlUserRepository($pdo);
$authService = new AuthService($userRepository);
$authController = new AuthController($authService);

return [
    'heroCarousel' => $heroController,
    'catalog'      => $catalogController,
    'product'      => $productController,
    'home'         => $homeController,
    'order'        => $orderController,
    'auth'         => $authController,
];