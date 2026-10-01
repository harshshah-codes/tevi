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
];