<?php
declare(strict_types=1);

namespace App\Domain\Models;

final class Category
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $slug,
        private readonly string $image,
        private readonly string $tagline = '',
        private readonly array $products = [],
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function image(): string
    {
        return $this->image;
    }

    public function tagline(): string
    {
        return $this->tagline;
    }

    /**
     * @return Product[]
     */
    public function products(): array
    {
        return $this->products;
    }
}