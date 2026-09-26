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

    public function findById(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, image, tagline FROM categories WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return new Category(
            (int) $row['id'],
            $row['name'],
            $row['slug'],
            $row['image'],
            $row['tagline'],
        );
    }

    public function findBySlug(string $slug): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, image, tagline FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return new Category(
            (int) $row['id'],
            $row['name'],
            $row['slug'],
            $row['image'],
            $row['tagline'],
        );
    }

    public function create(Category $category): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, slug, image, tagline) VALUES (:name, :slug, :image, :tagline)'
        );
        $stmt->execute([
            ':name'   => $category->name(),
            ':slug'   => $category->slug(),
            ':image'  => $category->image(),
            ':tagline' => $category->tagline(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Category $category): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE categories SET name = :name, slug = :slug, image = :image, tagline = :tagline WHERE id = :id'
        );
        $result = $stmt->execute([
            ':id'      => $category->id(),
            ':name'    => $category->name(),
            ':slug'    => $category->slug(),
            ':image'   => $category->image(),
            ':tagline' => $category->tagline(),
        ]);

        return $result && $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}