<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class MigrationRunner
{
    private const MIGRATIONS_DIR = __DIR__ . '/../database/migrations';
    private const MIGRATION_TABLE = 'migrations';

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureMigrationTable();
    }

    public function run(): void
    {
        $migrations = $this->getMigrationFiles();
        $applied = $this->getAppliedMigrations();

        foreach ($migrations as $migration) {
            $name = basename($migration, '.sql');
            if (in_array($name, $applied, true)) {
                continue;
            }

            echo "Applying migration: $name\n";
            $sql = file_get_contents($migration);
            $this->pdo->exec($sql);
            $this->recordMigration($name);
            echo "Applied: $name\n";
        }
    }

    public function status(): array
    {
        $migrations = $this->getMigrationFiles();
        $applied = $this->getAppliedMigrations();

        $status = [];
        foreach ($migrations as $migration) {
            $name = basename($migration, '.sql');
            $status[] = [
                'name' => $name,
                'applied' => in_array($name, $applied, true),
            ];
        }
        return $status;
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `" . self::MIGRATION_TABLE . "` (
                `name` VARCHAR(255) NOT NULL PRIMARY KEY,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function getMigrationFiles(): array
    {
        $dir = self::MIGRATIONS_DIR;
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.sql');
        sort($files);
        return $files;
    }

    private function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT `name` FROM `" . self::MIGRATION_TABLE . "`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function recordMigration(string $name): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO `" . self::MIGRATION_TABLE . "` (`name`) VALUES (:name)");
        $stmt->execute([':name' => $name]);
    }
}