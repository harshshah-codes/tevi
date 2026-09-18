<?php
declare(strict_types=1);

namespace App\Domain\Models;

final class HeroSlide
{
    public function __construct(
        private readonly int $id,
        private readonly string $tagline,
        private readonly string $headline,
        private readonly string $paragraph,
        private readonly string $button,
        private readonly string $ctaLink,
        private readonly string $imageUrl,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function tagline(): string
    {
        return $this->tagline;
    }

    public function headline(): string
    {
        return $this->headline;
    }

    public function paragraph(): string
    {
        return $this->paragraph;
    }

    public function button(): string
    {
        return $this->button;
    }

    public function ctaLink(): string
    {
        return $this->ctaLink;
    }

    public function imageUrl(): string
    {
        return $this->imageUrl;
    }
}