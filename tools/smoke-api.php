<?php
/**
 * Offline smoke test for the JSON API.
 *
 *   php tools/smoke-api.php
 *
 * Boots the real container and calls each endpoint with a fabricated session,
 * with no web server involved. Each case runs in its own child process because
 * the controllers call ob_end_clean() and exit.
 *
 * This exists because a wrong property name inside a service or controller is a
 * fatal that only shows up as a 500 in production: /api/products shipped broken
 * that way for a whole release.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

/** Endpoint => [container key, method, query] */
const CASES = [
    'products_listing'  => ['catalog',  'products', ['limit' => '200']],
    'products_search'   => ['catalog',  'products', ['search' => 'a']],
    'products_category' => ['catalog',  'products', ['category' => 'kurta']],
    'wishlist'          => ['wishlist', 'index',    []],
    'addresses'         => ['address',  'index',    []],
    'orders'            => ['order',    'index',    []],
    'payments_config'   => ['payment',  'config',   []],
    'me'                => ['auth',     'me',       []],
];

$selected = getenv('SMOKE_CASE');

if ($selected !== false && $selected !== '') {
    runCase($selected);
    exit(0);
}

$failures = 0;

echo "API smoke test (offline — no web server)\n\n";

foreach (array_keys(CASES) as $name) {
    $out = [];
    $code = 0;
    exec(
        'SMOKE_CASE=' . escapeshellarg($name) . ' ' . PHP_BINARY . ' ' . escapeshellarg(__FILE__) . ' 2>&1',
        $out,
        $code
    );

    $raw = implode("\n", $out);
    $json = json_decode($raw, true);
    $ok = $code === 0 && is_array($json) && ($json['success'] ?? false) === true;

    if (!$ok) {
        $failures++;
    }

    $detail = $ok
        ? trim((string) ($json['count'] ?? count($json['items'] ?? $json['orders'] ?? []))) . ' item(s)'
        : substr(str_replace("\n", ' ', $raw), 0, 160);

    echo ($ok ? "  PASS  " : "  FAIL  ") . str_pad($name, 20) . $detail . "\n";
}

echo "\n" . count(CASES) . " endpoints, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);

/**
 * Execute one case and print its JSON (or the error that stopped it).
 */
function runCase(string $name): void
{
    if (!isset(CASES[$name])) {
        fwrite(STDERR, "unknown case: {$name}\n");
        exit(1);
    }

    [$service, $method, $query] = CASES[$name];

    $config = require __DIR__ . '/../app/Config/config.php';
    $pdo = \App\Core\Database::connect($config['db']);
    $userId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/' . $name;
    $_GET = $query;

    ob_start();

    $app = require __DIR__ . '/../app/bootstrap.php';

    // After bootstrap: its session_start() replaces $_SESSION with whatever is
    // on disk, so a value set beforehand would be discarded.
    $_SESSION['user_id'] = $userId;

    $app[$service]->{$method}();
}