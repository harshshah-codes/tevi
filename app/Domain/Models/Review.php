<?php
declare(strict_types=1);

namespace App\Domain\Models;

final class Review
{
    public function __construct(
        private readonly int $id,
        private readonly int $productId,
        private readonly string $author,
        private readonly int $rating,
        private readonly string $text,
        private readonly string $date,
        private readonly string $context = '',
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function rating(): int
    {
        return $this->rating;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function context(): string
    {
        return $this->context;
    }
}