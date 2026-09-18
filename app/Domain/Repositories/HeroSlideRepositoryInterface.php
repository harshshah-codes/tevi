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
}