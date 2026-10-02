<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Enums\Badge;
use App\Domain\Models\Category;
use App\Domain\Models\Product;
use App\Domain\Repositories\ProductRepositoryInterface;
use PDO;

final class MySqlProductRepository implements ProductRepositoryInterface
{
    private const COLUMNS = 'id, name, slug, description, price, image, badge, sizes, colors, is_featured';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return Product[]
     */
    public function findFeatured(): array
    {
        $rows = $this->pdo
            ->query('SELECT ' . self::COLUMNS . ' FROM products WHERE is_featured = 1 ORDER BY id ASC')
            ->fetchAll();

        return $this->hydrate($rows);
    }

    /**
     * @return Product[]
     */
    public function findNewArrivals(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM products WHERE is_featured = 0 ORDER BY id ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrate($stmt->fetchAll());
    }

    public function findById(int $id): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrate($stmt->fetchAll())[0] ?? null;
    }

    public function findBySlug(string $slug): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM products WHERE slug = :slug LIMIT 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();

        return $this->hydrate($stmt->fetchAll())[0] ?? null;
    }

    /**
     * @param int[] $categoryIds
     * @return Product[]
     */
    public function findRelated(array $categoryIds, int $excludeProductId, int $limit = 4): array
    {
        $ids = array_values(array_filter(array_map('intval', $categoryIds)));
        if ($ids === []) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT ' . self::COLUMNS
            . ' FROM products p JOIN product_category pc ON pc.product_id = p.id'
            . ' WHERE pc.category_id IN (' . implode(',', $ids) . ') AND p.id <> :exclude'
            . ' ORDER BY p.id ASC LIMIT :limit'
        );
        $stmt->bindValue(':exclude', $excludeProductId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->hydrate($stmt->fetchAll());
    }

    /**
     * @return Product[]
     */
    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT ' . self::COLUMNS . ' FROM products ORDER BY id DESC')
            ->fetchAll();

        return $this->hydrate($rows);
    }

    public function create(Product $product): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (name, slug, description, price, weight, image, badge, sizes, colors, is_featured)
                 VALUES (:name, :slug, :description, :price, :weight, :image, :badge, :sizes, :colors, :is_featured)'
            );
            $stmt->execute([
                ':name'        => $product->name(),
                ':slug'        => $product->slug(),
                ':description' => $product->description(),
                ':price'       => $product->price(),
                ':weight'      => $product->weight(),
                ':image'       => $product->image(),
                ':badge'       => $product->badge()?->value ?? '',
                ':sizes'       => json_encode($product->sizes()),
                ':colors'      => json_encode($product->colors()),
                ':is_featured' => $product->isFeatured() ? 1 : 0,
            ]);

            $productId = (int) $this->pdo->lastInsertId();

            if ($product->categories() !== []) {
                $this->attachCategories($productId, array_map(fn (Category $c) => $c->id(), $product->categories()));
            }

            $this->pdo->commit();
            return $productId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(Product $product): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE products SET name = :name, slug = :slug, description = :description, price = :price,
             weight = :weight, image = :image, badge = :badge, sizes = :sizes, colors = :colors,
             is_featured = :is_featured
             WHERE id = :id'
        );
        $result = $stmt->execute([
            ':id'          => $product->id(),
            ':name'        => $product->name(),
            ':slug'        => $product->slug(),
            ':description' => $product->description(),
            ':price'       => $product->price(),
            ':weight'      => $product->weight(),
            ':image'       => $product->image(),
            ':badge'       => $product->badge()?->value ?? '',
            ':sizes'       => json_encode($product->sizes()),
            ':colors'      => json_encode($product->colors()),
            ':is_featured' => $product->isFeatured() ? 1 : 0,
        ]);

        if ($result) {
            $this->detachCategories($product->id());
            if ($product->categories() !== []) {
                $this->attachCategories($product->id(), array_map(fn (Category $c) => $c->id(), $product->categories()));
            }
        }

        return $result && $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function attachCategories(int $productId, array $categoryIds): bool
    {
        if ($categoryIds === []) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO product_category (product_id, category_id) VALUES (:product_id, :category_id)'
        );

        foreach ($categoryIds as $categoryId) {
            $stmt->execute([
                ':product_id'  => $productId,
                ':category_id' => $categoryId,
            ]);
        }

        return true;
    }

    public function detachCategories(int $productId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM product_category WHERE product_id = :product_id');
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return Product[]
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $categoriesByProduct = $this->categoriesByProduct(array_column($rows, 'id'));

        $products = [];
        foreach ($rows as $row) {
            $products[] = new Product(
                (int) $row['id'],
                $row['name'],
                $row['slug'],
                $row['description'],
                (int) $row['price'],
                $row['image'],
                Badge::tryFrom($row['badge']) ?: null,
                $this->decodeList($row['sizes']),
                $this->decodeList($row['colors']),
                (bool) $row['is_featured'],
                $categoriesByProduct[(int) $row['id']] ?? [],
                isset($row['weight']) ? (float) $row['weight'] : 0.5,
            );
        }

        return $products;
    }

    /**
     * Loads and groups categories for a list of product ids.
     *
     * @param array<int, string|int> $productIds
     * @return array<int, Category[]>
     */
    private function categoriesByProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $ids = implode(',', array_map('intval', $productIds));

        $rows = $this->pdo
            ->query(
                'SELECT pc.product_id, c.id, c.name, c.slug, c.image, c.tagline
                   FROM product_category pc
                   JOIN categories c ON c.id = pc.category_id
                  WHERE pc.product_id IN (' . $ids . ')
                  ORDER BY c.id ASC'
            )
            ->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $grouped[$productId][] = new Category(
                (int) $row['id'],
                $row['name'],
                $row['slug'],
                $row['image'],
                $row['tagline'],
            );
        }

        return $grouped;
    }

    /**
     * @return string[]
     */
    private function decodeList(mixed $json): array
    {
        if ($json === null) {
            return [];
        }

        $values = json_decode((string) $json, true);
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $values)));
    }
}