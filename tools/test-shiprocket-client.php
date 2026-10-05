<?php
/**
 * Offline checks for ShiprocketClient's response handling and the config that
 * feeds it. No network and no Shiprocket account needed.
 *
 *   php tools/test-shiprocket-client.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

use App\Infrastructure\Shipping\ShiprocketClient;
use App\Infrastructure\Shipping\ShiprocketUnavailableException;

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

/** Invoke the private parseResponse() without touching the network. */
function parse(ShiprocketClient $client, int $status, string $body): array
{
    $method = new ReflectionMethod($client, 'parseResponse');
    $method->setAccessible(true);

    return $method->invoke($client, $status, $body);
}

function catchMessage(callable $fn): string
{
    try {
        $fn();
        return '(no exception thrown)';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

echo "Shiprocket API base\n";

$client = new ShiprocketClient('a@b.c', 'token', false);
check('defaults to the documented host', $client->apiBase() === 'https://api.shiprocket.in/v1', $client->apiBase());
check('simulate flag is honoured', (new ShiprocketClient('a', 'b', true))->isSimulated() === true);

$alt = new ShiprocketClient('a', 'b', false, 'https://apiv2.shiprocket.in/v1/external');
check('base is overridable', str_contains($alt->apiBase(), 'apiv2.shiprocket.in'), $alt->apiBase());

echo "\nSuccessful responses\n";

$created = parse($client, 200, json_encode([
    'shipment_id' => 'Shp_1', 'awb' => '123456789012',
    'label_url' => 'https://x/l.pdf', 'pickup_token' => 'tok', 'shipment_status' => 'processing',
]));
check('reads shipment_id', ($created['shipment_id'] ?? null) === 'Shp_1');
check('reads awb as waybill', ($created['awb'] ?? null) === '123456789012');
check('202 Accepted parses', ($created = parse($client, 202, '{"shipment_id":"S"}'))['shipment_id'] === 'S');

echo "\nThe reported failure: 503 with an HTML body\n";

$html = "<html>\n<head><title>503 Service Temporarily Unavailable</title></head>\n<body>\n"
      . "<center><h1>503 Service Temporarily Unavailable</h1></center>\n</body>\n</html>";

$msg = catchMessage(static fn() => parse($client, 503, $html));
check('raises the retryable exception', str_contains($msg, 'non-JSON body'), $msg);
check('names the status', str_contains($msg, '503'));
check('does not dump raw HTML', !str_contains($msg, '<html>') && !str_contains($msg, '<title>'));
check('points at the base URL setting', str_contains($msg, 'SHIPROCKET_API_BASE'));

$altMsg = catchMessage(static fn() => parse($alt, 503, $html));
check('names the base actually in use', str_contains($altMsg, 'apiv2.shiprocket.in'));

echo "\nStructured errors\n";

$auth = catchMessage(static fn() => parse($client, 401, '{"message":"Failed to authenticate because of bad credentials.","status_code":401}'));
check('surfaces their message verbatim', str_contains($auth, 'bad credentials'), $auth);
check('401 is not retryable', !str_contains($auth, 'non-JSON'));

$list = catchMessage(static fn() => parse($client, 422, '{"message":["order_id is required","pincode invalid"]}'));
check('joins message arrays', str_contains($list, 'pincode invalid') && str_contains($list, 'order_id is required'), $list);

$unavailable = catchMessage(static fn() => parse($client, 500, '{"message":"internal"}'));
check('5xx JSON is retryable', str_contains($unavailable, 'internal'));

$empty = catchMessage(static fn() => parse($client, 502, ''));
check('empty body is described', str_contains($empty, 'empty response'), $empty);

echo "\nConfig wiring\n";

$config = require __DIR__ . '/../app/Config/config.php';
check('config exposes api_base', isset($config['shiprocket']['api_base']), $config['shiprocket']['api_base'] ?? '(missing)');
check(
    'config base matches the client default',
    ($config['shiprocket']['api_base'] ?? '') === 'https://api.shiprocket.in/v1',
    (string) ($config['shiprocket']['api_base'] ?? '')
);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);