<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

// Normalise the request URI to a path, stripping the app's base directory
// (e.g. "/house of virasat/public") so the app works in any folder or at root.
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

// 1. Serve real static files directly (css, js, images, html)
$staticFile = __DIR__ . '/static' . $uri;
if ($uri !== '/' && file_exists($staticFile) && !is_dir($staticFile)) {
    return false; // let PHP's built-in server handle it, or readfile() in production
}

// 2. Route to your PHP app
require __DIR__ . '/../app/routes.php';