<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Models\HeroSlide;
use App\Domain\Repositories\HeroSlideRepositoryInterface;
use PDO;

final class MySqlHeroSlideRepository implements HeroSlideRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return HeroSlide[]
     */
    public function findAll(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, tagline, headline, paragraph, button, cta_link, image_url FROM hero_slides ORDER BY id ASC')
            ->fetchAll();

        return array_map(
            static fn (array $row): HeroSlide => new HeroSlide(
                (int) $row['id'],
                $row['tagline'],
                $row['headline'],
                $row['paragraph'],
                $row['button'],
                $row['cta_link'],
                $row['image_url'],
            ),
            $rows
        );
    }

    public function findById(int $id): ?HeroSlide
    {
        $stmt = $this->pdo->prepare('SELECT id, tagline, headline, paragraph, button, cta_link, image_url FROM hero_slides WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return new HeroSlide(
            (int) $row['id'],
            $row['tagline'],
            $row['headline'],
            $row['paragraph'],
            $row['button'],
            $row['cta_link'],
            $row['image_url'],
        );
    }

    public function create(HeroSlide $slide): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO hero_slides (tagline, headline, paragraph, button, cta_link, image_url) VALUES (:tagline, :headline, :paragraph, :button, :cta_link, :image_url)'
        );
        $stmt->execute([
            ':tagline'   => $slide->tagline(),
            ':headline'  => $slide->headline(),
            ':paragraph' => $slide->paragraph(),
            ':button'    => $slide->button(),
            ':cta_link'  => $slide->ctaLink(),
            ':image_url' => $slide->imageUrl(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(HeroSlide $slide): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE hero_slides SET tagline = :tagline, headline = :headline, paragraph = :paragraph, button = :button, cta_link = :cta_link, image_url = :image_url WHERE id = :id'
        );
        $result = $stmt->execute([
            ':id'        => $slide->id(),
            ':tagline'   => $slide->tagline(),
            ':headline'  => $slide->headline(),
            ':paragraph' => $slide->paragraph(),
            ':button'    => $slide->button(),
            ':cta_link'  => $slide->ctaLink(),
            ':image_url' => $slide->imageUrl(),
        ]);

        return $result && $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM hero_slides WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}