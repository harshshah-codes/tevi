<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\WishlistItem;
use App\Domain\Repositories\WishlistRepositoryInterface;
use PDO;

final class MySqlWishlistRepository implements WishlistRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return WishlistItem[]
     */
    public function findForUser(int $userId): array
    {
        // Category comes from a scalar subquery rather than a join: products can
        // sit in several categories, and joining would repeat one wishlist row
        // once per category (so the count would overstate the list).
        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.user_id, w.product_id, w.size, w.color,
                    p.name, p.price, p.image,
                    (SELECT c.name
                       FROM product_category pc
                       JOIN categories c ON c.id = pc.category_id
                      WHERE pc.product_id = p.id
                      ORDER BY c.name ASC
                      LIMIT 1) AS category
               FROM wishlist_items w
               JOIN products p ON p.id = w.product_id
              WHERE w.user_id = :user_id
           ORDER BY w.created_at DESC, w.id DESC'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = new WishlistItem(
                (int) $row['id'],
                (int) $row['user_id'],
                (int) $row['product_id'],
                (string) $row['name'],
                (int) $row['price'],
                (string) $row['image'],
                (string) $row['category'],
                (string) $row['size'],
                (string) $row['color'],
            );
        }

        return $items;
    }

    public function contains(int $userId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id LIMIT 1'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function add(int $userId, int $productId, string $size = '', string $color = ''): int
    {
        // INSERT IGNORE + the unique key makes a repeat add a no-op instead of
        // a duplicate error, so the heart button can be double-clicked safely.
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO wishlist_items (user_id, product_id, size, color)
             VALUES (:user_id, :product_id, :size, :color)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $productId,
            ':size'       => $size,
            ':color'      => $color,
        ]);

        if ($stmt->rowCount() > 0) {
            return (int) $this->pdo->lastInsertId();
        }

        $find = $this->pdo->prepare(
            'SELECT id FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id LIMIT 1'
        );
        $find->execute([':user_id' => $userId, ':product_id' => $productId]);

        return (int) $find->fetchColumn();
    }

    public function updateOptions(int $userId, int $productId, string $size = '', string $color = ''): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wishlist_items SET size = :size, color = :color
              WHERE user_id = :user_id AND product_id = :product_id'
        );
        $stmt->execute([
            ':size'       => $size,
            ':color'      => $color,
            ':user_id'    => $userId,
            ':product_id' => $productId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function remove(int $userId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id'
        );
        $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);

        return $stmt->rowCount() > 0;
    }

    public function clear(int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM wishlist_items WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @param array<int, int> $productIds
     */
    public function addMissing(int $userId, array $productIds): int
    {
        $added = 0;

        foreach ($productIds as $productId) {
            if ($this->contains($userId, $productId)) {
                continue;
            }

            $this->add($userId, $productId);
            $added++;
        }

        return $added;
    }
}