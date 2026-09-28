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
            'SELECT id, product_id, author, rating, review_text, created_at, is_visible, context
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
                $row['context'] ?? '',
                (bool) $row['is_visible'],
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
            'SELECT r.id, r.product_id, r.author, r.rating, r.review_text, r.created_at, r.context,
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
                $row['context'] ?? '',
                true,
            );
        }

        return $reviews;
    }

    /**
     * @return Review[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.product_id, r.author, r.rating, r.review_text, r.created_at, r.is_visible, r.context,
                    p.name AS product_name
               FROM reviews r
               JOIN products p ON p.id = r.product_id
              ORDER BY r.created_at DESC, r.id DESC'
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
                $row['context'] ?? '',
                (bool) $row['is_visible'],
            );
        }

        return $reviews;
    }

    public function findById(int $id): ?Review
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, author, rating, review_text, created_at, is_visible, context
               FROM reviews
              WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return new Review(
            (int) $row['id'],
            (int) $row['product_id'],
            $row['author'],
            (int) $row['rating'],
            $row['review_text'] ?? '',
            $row['created_at'],
            $row['context'] ?? '',
            (bool) $row['is_visible'],
        );
    }

    public function create(Review $review): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (product_id, author, rating, review_text, created_at, is_visible, context)
             VALUES (:product_id, :author, :rating, :review_text, :created_at, :is_visible, :context)'
        );
        $stmt->execute([
            ':product_id'  => $review->productId(),
            ':author'      => $review->author(),
            ':rating'      => $review->rating(),
            ':review_text' => $review->text(),
            ':created_at'  => $review->date(),
            ':is_visible'  => 1,
            ':context'     => $review->context(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Review $review): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE reviews SET product_id = :product_id, author = :author, rating = :rating,
             review_text = :review_text, created_at = :created_at, context = :context
             WHERE id = :id'
        );
        $result = $stmt->execute([
            ':id'           => $review->id(),
            ':product_id'   => $review->productId(),
            ':author'       => $review->author(),
            ':rating'       => $review->rating(),
            ':review_text'  => $review->text(),
            ':created_at'   => $review->date(),
            ':context'      => $review->context(),
        ]);

        return $result && $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reviews WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function setVisibility(int $id, bool $visible): bool
    {
        $stmt = $this->pdo->prepare('UPDATE reviews SET is_visible = :visible WHERE id = :id');
        $result = $stmt->execute([
            ':id'      => $id,
            ':visible' => $visible ? 1 : 0,
        ]);

        return $result && $stmt->rowCount() > 0;
    }
}