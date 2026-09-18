<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\HeroSlide;
use App\Domain\Repositories\HeroSlideRepositoryInterface;

final class HeroCarouselService
{
    public function __construct(
        private readonly HeroSlideRepositoryInterface $repository,
    ) {
    }

    /**
     * Slides shown on the home page hero carousel, in display order.
     *
     * @return HeroSlide[]
     */
    public function getHomeSlides(): array
    {
        return $this->repository->findAll();
    }
}