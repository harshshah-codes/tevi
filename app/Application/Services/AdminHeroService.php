<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Models\HeroSlide;
use App\Domain\Repositories\HeroSlideRepositoryInterface;

final class AdminHeroService
{
    public function __construct(
        private readonly HeroSlideRepositoryInterface $repository,
    ) {
    }

    /**
     * @return HeroSlide[]
     */
    public function getAllSlides(): array
    {
        return $this->repository->findAll();
    }

    public function getSlide(int $id): ?HeroSlide
    {
        return $this->repository->findById($id);
    }

    public function createSlide(HeroSlide $slide): int
    {
        return $this->repository->create($slide);
    }

    public function updateSlide(HeroSlide $slide): bool
    {
        return $this->repository->update($slide);
    }

    public function deleteSlide(int $id): bool
    {
        return $this->repository->delete($id);
    }
}