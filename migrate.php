#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Core/Autoload.php';
\App\Core\Autoload::register();

require_once __DIR__ . '/app/bootstrap.php';

$config = require __DIR__ . '/app/Config/config.php';
$pdo = \App\Core\Database::connect($config['db']);

$runner = new \App\Core\MigrationRunner($pdo);
$status = $runner->status();

echo "Migration Status:\n";
echo str_repeat('-', 60) . "\n";
foreach ($status as $migration) {
    $state = $migration['applied'] ? '[APPLIED]' : '[PENDING]';
    echo "$state {$migration['name']}\n";
}
echo str_repeat('-', 60) . "\n";

$pending = array_filter($status, fn($m) => !$m['applied']);
if ($pending) {
    echo "Running " . count($pending) . " pending migration(s)...\n";
    $runner->run();
    echo "Done.\n";
} else {
    echo "All migrations are up to date.\n";
}