<?php
declare(strict_types=1);

namespace App\Domain\Models;

/**
 * A saved product. Product details come from `products`, so a price change is
 * reflected immediately rather than frozen at save time.
 */
final class WishlistItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly int $productId,
        private readonly string $name,
        private readonly int $price,
        private readonly string $image,
        private readonly string $category,
        private readonly string $size,
        private readonly string $color,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function image(): string
    {
        return $this->image;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function size(): string
    {
        return $this->size;
    }

    public function color(): string
    {
        return $this->color;
    }

    /**
     * Shape kept identical to the old localStorage objects, so the existing
     * renderers keep working after switching to the API.
     *
     * @return array{id:int, name:string, price:int, image:string, category:string, size:string, color:string}
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->productId,
            'name'     => $this->name,
            'price'    => $this->price,
            'image'    => $this->image,
            'category' => $this->category,
            'size'     => $this->size,
            'color'    => $this->color,
        ];
    }
}