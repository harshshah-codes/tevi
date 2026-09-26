<?php
declare(strict_types=1);

use App\Application\Services\CatalogService;
use App\Application\Services\HeroCarouselService;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Infrastructure\Http\Controllers\CatalogController;
use App\Infrastructure\Http\Controllers\HeroCarouselController;
use App\Infrastructure\Http\Controllers\HomeController;
use App\Infrastructure\Http\Controllers\ProductController;
use App\Infrastructure\Persistence\MySqlCategoryRepository;
use App\Infrastructure\Persistence\MySqlHeroSlideRepository;
use App\Infrastructure\Persistence\MySqlProductRepository;
use App\Infrastructure\Persistence\MySqlReviewRepository;

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

return [
    'heroCarousel' => $heroController,
    'catalog'      => $catalogController,
    'product'      => $productController,
    'home'         => $homeController,
];