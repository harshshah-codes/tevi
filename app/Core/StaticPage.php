<?php
declare(strict_types=1);

namespace App\Core;

final class StaticPage
{
    public static function render(string $file): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
        $baseUrl = $scheme . '://' . $host . $basePath;

        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        return str_replace(
            [
                '<head>',
                'href="/"',
                'src="/images/',
                'href="fav.png"',
                'src="image.png"',
            ],
            [
                '<head>' . "\n" . '    <base href="' . $baseUrl . '/">',
                'href="' . $baseUrl . '/"',
                'src="' . $baseUrl . '/images/',
                'href="' . $baseUrl . '/images/fav.png"',
                'src="' . $baseUrl . '/images/image.png"',
            ],
            $content
        );
    }
}