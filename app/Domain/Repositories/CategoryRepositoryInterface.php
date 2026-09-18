<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\Category;

interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function findAll(): array;
}