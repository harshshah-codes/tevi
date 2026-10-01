<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public static function connect(array $config): PDO
    {
        // Every table is created utf8mb4_unicode_ci, but a plain `charset=utf8mb4`
        // connection reports utf8mb4_general_ci. With ATTR_EMULATE_PREPARES off
        // a bound string is sent with that general_ci tag, so comparing it to a
        // unicode_ci column fails with "Illegal mix of collations"
        // (SQLSTATE HY000 / error 1267). Pinning the connection collation
        // (SET NAMES) makes parameters and columns comparable again.
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['name']
        );

        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return $pdo;
    }
}