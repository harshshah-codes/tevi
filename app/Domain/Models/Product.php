<?php
declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Enums\Badge;

final class Product
{
    /**
     * A product may be featured, may belong to several categories, or to none.
     *
     * @param string[] $sizes
     * @param string[] $colors
     * @param Category[] $categories
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $slug,
        private readonly string $description,
        private readonly int $price,
        private readonly string $image,
        private readonly ?Badge $badge,
        private readonly array $sizes,
        private readonly array $colors,
        private readonly bool $isFeatured,
        private readonly array $categories = [],
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

    public function description(): string
    {
        return $this->description;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function image(): string
    {
        return $this->image;
    }

    public function badge(): ?Badge
    {
        return $this->badge;
    }

    /**
     * @return string[]
     */
    public function sizes(): array
    {
        return $this->sizes;
    }

    /**
     * @return string[]
     */
    public function colors(): array
    {
        return $this->colors;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    /**
     * @return Category[]
     */
    public function categories(): array
    {
        return $this->categories;
    }
}