<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\HeroSlide;

interface HeroSlideRepositoryInterface
{
    /**
     * @return HeroSlide[]
     */
    public function findAll(): array;

    public function findById(int $id): ?HeroSlide;

    public function create(HeroSlide $slide): int;

    public function update(HeroSlide $slide): bool;

    public function delete(int $id): bool;
}