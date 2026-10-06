<?php
/**
 * Catalog search checks against a running app.
 *
 *   php tools/test-search.php http://127.0.0.1:8000
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/') . '/api/products';

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

function search(string $url, string $term): array
{
    $ch = curl_init($url . '?search=' . rawurlencode($term));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => $raw]];
}

require_once __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

echo "Validation\n";
check('GET works', search($base, 'kurta')['status'] === 200);

// No search term now means "the whole catalog" rather than an error, because
// the collection page loads through the same endpoint.
$listing = search($base, '');
check('empty term lists the catalog instead of erroring',
    ($listing['status'] ?? 0) === 200 && ($listing['body']['success'] ?? false) === true,
    'count=' . ($listing['body']['count'] ?? '?'));
check('every product in the listing has a slug',
    count(array_filter($listing['body']['results'] ?? [], static fn($p) => ($p['slug'] ?? '') !== ''))
    === count($listing['body']['results'] ?? []));

echo "\nResults come from the catalog\n";

$config = require __DIR__ . '/../app/Config/config.php';
$pdo = \App\Core\Database::connect($config['db']);
$product = $pdo->query('SELECT name FROM products ORDER BY id LIMIT 1')->fetch(\PDO::FETCH_ASSOC);

if ($product === false) {
    echo "  SKIP  no products in the catalog\n";
    exit(1);
}

$name = (string) $product['name'];
$firstWord = strtok($name, ' ');

$byName = search($base, $firstWord);
check('finds a product by a word from its name', ($byName['body']['count'] ?? 0) > 0,
    $firstWord . ' -> ' . ($byName['body']['count'] ?? '?') . ' result(s)');
check('results are real product rows',
    isset($byName['body']['results'][0]['id'], $byName['body']['results'][0]['price']),
    json_encode($byName['body']['results'][0] ?? null));

echo "\nNames that cannot match\n";
$missing = search($base, 'zzzznotathing');
check('no matches returns an empty list, not an error',
    ($missing['body']['success'] ?? false) === true && ($missing['body']['count'] ?? -1) === 0);
check('the query is echoed back', ($missing['body']['query'] ?? '') === 'zzzznotathing');

echo "\nPayload shape\n";
$one = $byName['body']['results'][0] ?? [];
check('id is an int', is_int($one['id'] ?? null));
check('price is an int (rupees, not paise)', is_int($one['price'] ?? null));
check('category is a string', is_string($one['category'] ?? null));
check('badge may be absent but never leaks an object', !is_array($one['badge'] ?? ''));

echo "\nInjection and encoding\n";
check('SQL metacharacters are inert', search($base, "' OR 1=1 --")['status'] === 200);
check('wildcards do not match everything', (search($base, '%')['body']['count'] ?? -1) === 0);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);