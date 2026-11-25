<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Application\Services\CatalogService;
use App\Application\Services\HeroCarouselService;
use App\Application\Services\OrderService;
use App\Application\Services\PricingService;
use App\Application\Services\PaymentService;
use App\Infrastructure\Payment\RazorpayClient;
use App\Infrastructure\Shipping\ShiprocketClient;
use App\Application\Services\ShipmentService;
use App\Application\Services\WishlistService;
use App\Application\Services\InventoryService;
use App\Infrastructure\Persistence\MySqlWishlistRepository;
use App\Infrastructure\Persistence\MySqlProductVariantRepository;
use App\Infrastructure\Persistence\MySqlInventoryLedgerRepository;
use App\Infrastructure\Http\Controllers\WishlistController;
use App\Infrastructure\Http\Controllers\InventoryController;
use App\Infrastructure\Http\Controllers\PaymentController;
use App\Infrastructure\Http\Controllers\WebhookController;
use App\Application\Services\AuthService;
use App\Application\Services\AddressService;
use App\Core\Database;
use App\Core\MigrationRunner;
use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\AddressController;
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
use App\Infrastructure\Persistence\MySqlAddressRepository;
use App\Domain\Repositories\AddressRepositoryInterface;

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

$variantRepository = new MySqlProductVariantRepository($pdo);
$ledgerRepository = new MySqlInventoryLedgerRepository($pdo);
$inventoryService = new InventoryService($variantRepository, $ledgerRepository, $productRepository, $pdo);

$orderRepository = new MySqlOrderRepository($pdo);
$pricingService = new PricingService($productRepository);
$razorpayClient = new RazorpayClient(
    $config['razorpay']['key_id'],
    $config['razorpay']['key_secret'],
    (bool) $config['razorpay']['simulate']
);
$orderService = new OrderService($orderRepository, $razorpayClient);
$orderController = new OrderController($orderService, $pricingService, $inventoryService);
$paymentService = new PaymentService($razorpayClient);

$shiprocketClient = new ShiprocketClient(
    $config['shiprocket']['email'],
    $config['shiprocket']['api_password'],
    (bool) $config['shiprocket']['simulate'],
    (string) $config['shiprocket']['api_base'],
    3,
    (string) $config['shiprocket']['token_cache']
);
$shipmentService = new ShipmentService($shiprocketClient);
$wishlistRepository = new MySqlWishlistRepository($pdo);
$wishlistService = new WishlistService($wishlistRepository, $productRepository);
$wishlistController = new WishlistController($wishlistService);
$inventoryController = new InventoryController($inventoryService);

$webhookController = new WebhookController(
    $orderService,
    $shipmentService,
    (string) $config['shiprocket']['webhook_secret'],
    (bool) $config['shiprocket']['webhook_allow_query_token']
);
$paymentController = new PaymentController($paymentService, $orderService);

$userRepository = new MySqlUserRepository($pdo);
$authService = new AuthService($userRepository);
$authController = new AuthController($authService);

$addressRepository = new MySqlAddressRepository($pdo);
$addressService = new AddressService($addressRepository);
$addressController = new AddressController($addressService);

return [
    'heroCarousel' => $heroController,
    'catalog'      => $catalogController,
    'product'      => $productController,
    'home'         => $homeController,
    'order'        => $orderController,
    'auth'         => $authController,
    'address'      => $addressController,
    'payment'      => $paymentController,
    'shipment'     => $shipmentService,
    'webhook'      => $webhookController,
    'wishlist'     => $wishlistController,
    'inventory'    => $inventoryService,
    'inventoryPublic' => $inventoryController,
];