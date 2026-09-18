<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\Category;
use App\Domain\Repositories\CategoryRepositoryInterface;
use PDO;

final class MySqlCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return Category[]
     */
    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, name, slug, image, tagline FROM categories ORDER BY id ASC')
            ->fetchAll();

        return array_map(
            static fn (array $row): Category => new Category(
                (int) $row['id'],
                $row['name'],
                $row['slug'],
                $row['image'],
                $row['tagline'],
            ),
            $rows
        );
    }
}