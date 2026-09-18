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
}