<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\Review;
use App\Domain\Repositories\ReviewRepositoryInterface;
use PDO;

final class MySqlReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return Review[]
     */
    public function findByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, author, rating, review_text, created_at
               FROM reviews
              WHERE product_id = :productId AND is_visible = 1
              ORDER BY created_at DESC, id DESC'
        );
        $stmt->bindValue(':productId', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $reviews = [];
        foreach ($stmt->fetchAll() as $row) {
            $reviews[] = new Review(
                (int) $row['id'],
                (int) $row['product_id'],
                $row['author'],
                (int) $row['rating'],
                $row['review_text'] ?? '',
                $row['created_at'],
            );
        }

        return $reviews;
    }

    /**
     * @return array{avg: float, count: int}
     */
public function summaryByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count
               FROM reviews
              WHERE product_id = :productId AND is_visible = 1'
        );
        $stmt->bindValue(':productId', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'avg'   => (float) ($row['avg_rating'] ?? 0),
            'count' => (int) ($row['review_count'] ?? 0),
        ];
    }

    /**
     * @return Review[]
     */
    public function findVisible(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.product_id, r.author, r.rating, r.review_text, r.created_at,
                    (SELECT c.name
                       FROM product_category pc
                       JOIN categories c ON c.id = pc.category_id
                      WHERE pc.product_id = r.product_id
                      ORDER BY c.id ASC
                      LIMIT 1) AS category_name
               FROM reviews r
              WHERE r.is_visible = 1
              ORDER BY r.rating DESC, r.created_at DESC, r.id DESC'
        );
        $stmt->execute();

        $reviews = [];
        foreach ($stmt->fetchAll() as $row) {
            $reviews[] = new Review(
                (int) $row['id'],
                (int) $row['product_id'],
                $row['author'],
                (int) $row['rating'],
                $row['review_text'] ?? '',
                $row['created_at'],
                $row['category_name'] ?? '',
            );
        }

        return $reviews;
    }
}