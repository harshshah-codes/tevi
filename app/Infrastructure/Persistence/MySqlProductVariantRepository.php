<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\ProductVariant;
use App\Domain\Repositories\ProductVariantRepositoryInterface;
use PDO;

final class MySqlProductVariantRepository implements ProductVariantRepositoryInterface
{
    private const SELECT = 'SELECT v.id, v.product_id, v.colour, v.size, v.stock, v.low_stock_threshold, v.is_active,
                                   p.name AS product_name
                              FROM product_variants v
                              JOIN products p ON p.id = v.product_id';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findForProduct(int $productId, bool $includeInactive = true): array
    {
        $sql = self::SELECT . ' WHERE v.product_id = :product_id'
            . ($includeInactive ? '' : ' AND v.is_active = 1')
            . ' ORDER BY v.colour ASC, v.size ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(int $variantId): ?ProductVariant
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE v.id = :id LIMIT 1');
        $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductColourSize(int $productId, string $colour, string $size): ?ProductVariant
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . '
              WHERE v.product_id = :product_id
                AND LOWER(TRIM(v.colour)) = LOWER(TRIM(:colour))
                AND LOWER(TRIM(v.size)) = LOWER(TRIM(:size))
              LIMIT 1'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':colour', $colour);
        $stmt->bindValue(':size', $size);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function availabilityForProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT colour, size, stock, is_active
               FROM product_variants
              WHERE product_id = :product_id'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = self::key((string) $row['colour'], (string) $row['size']);
            $out[$key] = [
                'stock'   => (int) $row['stock'],
                'inStock' => (int) $row['is_active'] === 1 && (int) $row['stock'] > 0,
                'label'   => trim(implode(' / ', array_filter([$row['colour'], $row['size']]))),
            ];
        }

        return $out;
    }

    public function stockForKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = [];
        foreach (array_chunk($keys, 400) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $i => $key) {
                [$productId, $colour, $size] = self::splitKey($key);
                $placeholders[] = sprintf(
                    '(v.product_id = :p%d AND LOWER(TRIM(v.colour)) = :c%d AND LOWER(TRIM(v.size)) = :s%d)',
                    $i, $i, $i
                );
                $params[":p{$i}"] = $productId;
                $params[":c{$i}"] = $colour;
                $params[":s{$i}"] = $size;
            }

            $stmt = $this->pdo->prepare(
                'SELECT v.product_id, v.colour, v.size, v.stock
                   FROM product_variants v
                  WHERE ' . implode(' OR ', $placeholders)
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll() as $row) {
                $rows[self::key((string) $row['colour'], (string) $row['size'], (int) $row['product_id'])] = (int) $row['stock'];
            }
        }

        return $rows;
    }

    public function syncFromProductOptions(int $productId, array $sizes, array $colours): int
    {
        $sizes = array_values(array_filter(array_map('trim', $sizes)));
        $colours = array_values(array_filter(array_map('trim', $colours)));

        // A product with no colours still needs one row so its stock is
        // countable — otherwise "how many plain kurtas do I have?" has no home.
        if ($colours === []) {
            $colours = [''];
        }
        if ($sizes === []) {
            $sizes = [''];
        }

        $created = 0;
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO product_variants (product_id, colour, size, stock)
             VALUES (:product_id, :colour, :size, 0)'
        );

        foreach ($colours as $colour) {
            foreach ($sizes as $size) {
                $stmt->execute([
                    ':product_id' => $productId,
                    ':colour'     => $colour,
                    ':size'       => $size,
                ]);
                $created += $stmt->rowCount();
            }
        }

        return $created;
    }

    public function setLowStockThreshold(int $variantId, int $threshold): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE product_variants SET low_stock_threshold = :threshold WHERE id = :id'
        );
        $stmt->execute([':threshold' => max(0, $threshold), ':id' => $variantId]);

        return $stmt->rowCount();
    }

    public function setActive(int $variantId, bool $active): int
    {
        $stmt = $this->pdo->prepare('UPDATE product_variants SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $variantId]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ProductVariant
    {
        return new ProductVariant(
            (int) $row['id'],
            (int) $row['product_id'],
            (string) $row['product_name'],
            (string) $row['colour'],
            (string) $row['size'],
            (int) $row['stock'],
            (int) $row['low_stock_threshold'],
            (int) $row['is_active'] === 1,
        );
    }

    public static function key(string $colour, string $size, ?int $productId = null): string
    {
        return ($productId !== null ? $productId . '|' : '') . mb_strtolower(trim($colour)) . '|' . mb_strtolower(trim($size));
    }

    /**
     * @return array{0:int,1:string,2:string}
     */
    private static function splitKey(string $key): array
    {
        $parts = explode('|', $key, 3);

        return [(int) ($parts[0] ?? 0), $parts[1] ?? '', $parts[2] ?? ''];
    }
}
