<?php
declare(strict_types=1);

$dotenvPath = dirname(__DIR__, 2) . '/.env';
if (is_file($dotenvPath)) {
    foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'name' => $_ENV['DB_NAME'] ?? 'house_of_virasat',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    'admin' => [
        'username' => $_ENV['ADMIN_USERNAME'] ?? 'admin',
        'password_hash' => isset($_ENV['ADMIN_PASSWORD'])
            ? password_hash($_ENV['ADMIN_PASSWORD'], PASSWORD_DEFAULT)
            : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
    ],

    // Razorpay. The defaults below are placeholders: with them
    // RAZORPAY_SIMULATE stays true and the payment step runs in a clearly
    // labelled sandbox mode that never touches Razorpay's servers.
    // Set real rzp_test_* keys and RAZORPAY_SIMULATE=false to go live.
    'razorpay' => [
        'key_id'     => $_ENV['RAZORPAY_KEY_ID'] ?? 'rzp_test_DUMMY_KEY_ID',
        'key_secret' => $_ENV['RAZORPAY_KEY_SECRET'] ?? 'DUMMY_KEY_SECRET_REPLACE_ME',
        'simulate'   => filter_var($_ENV['RAZORPAY_SIMULATE'] ?? 'true', FILTER_VALIDATE_BOOL),
        'name'       => $_ENV['RAZORPAY_NAME'] ?? 'House of Viraasat',
    ],

    // Shiprocket courier API. Same story as Razorpay: the placeholder
    // credentials below keep SHIPROCKET_SIMULATE on, so "Request Delivery"
    // issues a fake waybill locally and never calls Shiprocket.
    'shiprocket' => [
        // API-user credentials from Settings -> API -> Create an API User.
        // Shiprocket authenticates with a bearer token from /auth/login, not
        // with these credentials directly, and the email must be an address
        // that is not already registered on the account.
        'email'         => $_ENV['SHIPROCKET_EMAIL'] ?? 'shiprocket@example.test',
        'api_password'  => $_ENV['SHIPROCKET_API_PASSWORD'] ?? 'DUMMY_SHIPROCKET_PASSWORD',
        'simulate'      => filter_var($_ENV['SHIPROCKET_SIMULATE'] ?? 'true', FILTER_VALIDATE_BOOL),
        // Their documented base host. The older api.shiprocket.in answers with
        // a 503 gateway page.
        'api_base'      => $_ENV['SHIPROCKET_API_BASE'] ?? 'https://apiv2.shiprocket.in/v1/external',
        // Where the bearer token is cached between requests; it lasts 240 hours.
        // dirname(__DIR__, 2) is the project root: config.php lives in app/Config,
        // and a cache file inside app/ would sit in the deployable tree.
        'token_cache'   => $_ENV['SHIPROCKET_TOKEN_CACHE']
            ?? dirname(__DIR__, 2) . '/storage/shiprocket-token.json',
        // Shiprocket does not sign its webhooks, so this secret is the only
        // thing authenticating the callback. Send it as an x-api-key header
        // rather than ?token=, which would end up in access logs and Referer
        // headers. Query tokens stay off unless you knowingly enable them.
        'webhook_secret' => $_ENV['SHIPROCKET_WEBHOOK_SECRET'] ?? 'DUMMY_WEBHOOK_SECRET',
        'webhook_allow_query_token' => filter_var($_ENV['SHIPROCKET_WEBHOOK_ALLOW_QUERY_TOKEN'] ?? 'false', FILTER_VALIDATE_BOOL),
    ],
];