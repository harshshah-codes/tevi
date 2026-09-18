<?php
declare(strict_types=1);

namespace App\Core;

final class Autoload
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = dirname(__DIR__) . '/' . $relative . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}