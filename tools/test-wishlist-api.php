<?php
/**
 * Wishlist API checks against a running app.
 *
 *   php tools/test-wishlist-api.php http://127.0.0.1:8922
 *
 * Two session ids are used to prove the list is per user, not global.
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8922', '/') . '/api/wishlist';

require_once __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

/**
 * Give each fake session a different real user id, by writing session files
 * the running app will read (both use /tmp for session.save_path).
 *
 * @return bool false when the app or database is not reachable
 */
function setupSessions(string $alice, string $bob): bool
{
    $config = require __DIR__ . '/../app/Config/config.php';

    try {
        $pdo = \App\Core\Database::connect($config['db']);
        $ids = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 2')->fetchAll(\PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }

    if (count($ids) < 2) {
        return false;
    }

    foreach ([[$alice, (int) $ids[0]], [$bob, (int) $ids[1]]] as [$sid, $userId]) {
        ini_set('session.save_path', sys_get_temp_dir());
        @session_id($sid);
        @session_start();
        $_SESSION['user_id'] = $userId;
        session_write_close();
    }

    return true;
}

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

function call(string $method, string $url, ?string $sid, ?array $body = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);

    if ($sid !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . $sid);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => $raw]];
}

// Two *different* accounts: same-user sessions would prove nothing about
// isolation, which is exactly the bug this caught the first time round.
$alice = 'wl-test-alice';
$bob = 'wl-test-bob';

// Written before the first echo on purpose: session.save_path cannot be changed
// once headers are sent, which would silently reuse the first fake session.
$sessionsReady = setupSessions($alice, $bob);

echo "Authentication\n";
check('anonymous list is rejected', call('GET', $base, null)['status'] === 401);
check('anonymous add is rejected', call('POST', $base, null, ['productId' => 1])['status'] === 401);

echo "\nPer-user isolation\n";
if (!$sessionsReady) {
    echo "  FAIL  could not create sessions — is the app running at " . $base . "?\n";
    exit(1);
}
call('DELETE', $base, $alice);
call('DELETE', $base, $bob);

$a = call('POST', $base, $alice, ['productId' => 1, 'size' => 'M', 'color' => 'Ivory']);
check('alice can add', ($a['status'] ?? 0) === 200 && ($a['body']['success'] ?? false), json_encode($a['body']));

$aList = call('GET', $base, $alice);
check('alice sees her item', ($aList['body']['count'] ?? 0) === 1, 'count=' . ($aList['body']['count'] ?? '?'));

$bList = call('GET', $base, $bob);
check('bob does not see it', ($bList['body']['count'] ?? -1) === 0, 'count=' . ($bList['body']['count'] ?? '?'));

echo "\nDuplicates and validation\n";
$again = call('POST', $base, $alice, ['productId' => 1]);
check('re-adding the same product does not duplicate', ($again['body']['count'] ?? -1) === 1);
check('missing productId is rejected', call('POST', $base, $alice, [])['status'] === 422);
check('unknown product is rejected', call('POST', $base, $alice, ['productId' => 999999])['status'] === 404);

echo "\nMulti-category products do not inflate the count\n";
// Product 4 sits in three categories; a naive join would report it three times.
$multi = call('POST', $base, $alice, ['productId' => 4]);
$afterMulti = call('GET', $base, $alice);
check('list length matches the row count', ($afterMulti['body']['count'] ?? -1) === count($afterMulti['body']['items'] ?? []),
    'count=' . ($afterMulti['body']['count'] ?? '?') . ' items=' . count($afterMulti['body']['items'] ?? []));
unset($multi);

echo "\nMerge a guest list\n";
call('DELETE', $base, $alice);
$merge = call('POST', $base . '/merge', $alice, ['productIds' => [1, 2, 3, 999999, 0, 1]]);
check('merge adds valid ids once', ($merge['body']['added'] ?? -1) === 3, 'added=' . ($merge['body']['added'] ?? '?'));
check('merge reports skips', ($merge['body']['skipped'] ?? -1) === 2, 'skipped=' . ($merge['body']['skipped'] ?? '?'));

echo "\nOptions\n";
$patch = call('PATCH', $base . '/1', $alice, ['size' => 'XL', 'color' => 'Gold']);
check('size can be changed', ($patch['body']['item']['size'] ?? '') === 'XL', json_encode($patch['body']['item'] ?? null));
check('patching an unsaved product 404s', call('PATCH', $base . '/999999', $alice, ['size' => 'S'])['status'] === 404);

echo "\nRemoval and clearing\n";
check('delete returns the remaining count', (call('DELETE', $base . '/2', $alice)['body']['count'] ?? -1) === 2);
check('deleting twice 404s', call('DELETE', $base . '/2', $alice)['status'] === 404);
$clear = call('DELETE', $base, $alice);
check('clear removes everything', ($clear['body']['removed'] ?? -1) === 2 && (call('GET', $base, $alice)['body']['count'] ?? -1) === 0);

call('DELETE', $base, $bob);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);