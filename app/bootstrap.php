<?php
declare(strict_types=1);

use App\Application\Services\HeroCarouselService;
use App\Core\Database;
use App\Infrastructure\Http\Controllers\HeroCarouselController;
use App\Infrastructure\Persistence\MySqlHeroSlideRepository;

$config = require __DIR__ . '/Config/config.php';

$pdo = Database::connect($config['db']);

$heroRepository = new MySqlHeroSlideRepository($pdo);
$heroService = new HeroCarouselService($heroRepository);
$heroController = new HeroCarouselController($heroService);

return [
    'heroCarousel' => $heroController,
];