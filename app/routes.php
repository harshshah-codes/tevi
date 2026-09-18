<?php
declare(strict_types=1);

// Normalise the request URI and strip the app's base directory
// (e.g. "/house of virasat/public") so routes always match from any folder.
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

// Remove trailing slash for consistency (except for root)
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

/**
 * Route Map
 * URL => Static File Path
 */
$routes = [
    // --- Core Pages ---
    '/'                  => 'index.html',
    '/index'             => 'index.html',
    '/about'             => 'about.html',
    '/contact'           => 'contact.html',
    '/faq'               => 'faq.html',
    '/search'            => 'search.html',

    // --- Authentication ---
    '/login'             => 'login.html',
    '/register'          => 'registration.html',
    '/registration'      => 'registration.html',
    '/my-account'        => 'myaccount.html',
    '/myaccount'         => 'myaccount.html',
    '/myorder'           => 'myorder.html',

    // --- E-commerce Flow ---
    '/product'           => 'product.html',
    '/cart'              => 'cart.html',
    '/checkout'          => 'checkout.html',
    '/order-confirmation' => 'order-confirmation.html',
    '/order-detail'      => 'order-detail.html',
    '/my-orders'         => 'myorder.html',
    '/track-order'       => 'track-order.html',
    '/wishlist'          => 'wishlist.html',

    // --- Policies ---
    '/privacy-policy'    => 'privacy-policy.html',
    '/return-policy'     => 'return-policy.html',
    '/shipping-policy'   => 'shipping-policy.html',
    '/terms'             => 'terms.html',
];

// Check if the route exists in our map
if (array_key_exists($uri, $routes)) {
    // Home is rendered by the Home controller (live hero + catalog from the DB backend)
    if ($uri === '/' || $uri === '/index') {
        $app = require __DIR__ . '/bootstrap.php';
        $app['home']->show();
        exit;
    }

    // Product details are rendered by the Product controller (live data from the DB backend)
    if ($uri === '/product') {
        $app = require __DIR__ . '/bootstrap.php';
        $app['product']->show();
        exit;
    }

    $file = __DIR__ . '/../public/static/' . $routes[$uri];

    if (file_exists($file)) {
        header('Content-Type: text/html; charset=UTF-8');
        echo \App\Core\StaticPage::render($file);
        exit;
    }
}

// --- 404 Not Found ---
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
// You can create a custom 404.html in your static folder later
if (file_exists(__DIR__ . '/../public/static/404.html')) {
    readfile(__DIR__ . '/../public/static/404.html');
} else {
    echo "<h1>404 Not Found</h1><p>The page you requested does not exist.</p>";
}