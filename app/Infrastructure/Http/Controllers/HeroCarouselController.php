<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\HeroCarouselService;
use App\Core\StaticPage;
use App\Domain\Models\HeroSlide;

final class HeroCarouselController
{
    private const HOME_TEMPLATE = '/public/static/index.html';

    public function __construct(
        private readonly HeroCarouselService $carousel,
    ) {
    }

    public function show(): void
    {
        try {
            $slides = $this->carousel->getHomeSlides();
        } catch (\Throwable) {
            $slides = [];
        }

        if ($slides === []) {
            $slides = [$this->fallbackSlide()];
        }

        $indicators = '';
        $items = '';

        foreach ($slides as $index => $slide) {
            $active = $index === 0 ? ' active' : '';
            $indicators .= '<button type="button" data-bs-target="#heroSlider" data-bs-slide-to="' . $index . '"'
                . ($active !== '' ? ' class="active"' : '') . '></button>' . "\n";
            $items .= $this->renderSlide($slide, $active) . "\n";
        }

        $hero = '<div class="carousel-indicators">' . "\n"
            . $indicators
            . '</div>' . "\n"
            . '<div class="carousel-inner">' . "\n"
            . $items
            . '</div>' . "\n"
            . '<button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>' . "\n"
            . '<button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>';

        $html = str_replace(
            '<!--HERO_CAROUSEL-->',
            $hero,
            StaticPage::render(dirname(__DIR__, 4) . self::HOME_TEMPLATE)
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    private function renderSlide(HeroSlide $slide, string $active): string
    {
        $image = htmlspecialchars($slide->imageUrl(), ENT_QUOTES);
        $cta = htmlspecialchars($slide->ctaLink(), ENT_QUOTES);
        $tagline = htmlspecialchars($slide->tagline(), ENT_QUOTES);
        $paragraph = htmlspecialchars($slide->paragraph(), ENT_QUOTES);
        $button = htmlspecialchars($slide->button(), ENT_QUOTES);

        return '<div class="carousel-item' . $active . ' hero-slide" style="background-image:url(\'' . $image . '\')">' . "\n"
            . '  <div class="hero-overlay"></div>' . "\n"
            . '  <div class="container hero-content">' . "\n"
            . '    <div class="hero-copy">' . "\n"
            . '      <span class="eyebrow">' . $tagline . '</span>' . "\n"
            . '      <h1>' . $slide->headline() . '</h1>' . "\n"
            . '      <p>' . $paragraph . '</p>' . "\n"
            . '      <a href="' . $cta . '" class="btn btn-viraasat">' . $button . ' <i class="bi bi-arrow-right"></i></a>' . "\n"
            . '    </div>' . "\n"
            . '  </div>' . "\n"
            . '</div>';
    }

    private function fallbackSlide(): HeroSlide
    {
        return new HeroSlide(
            0,
            'THE FESTIVE EDIT · 2026',
            'Tradition,<br><em>Tailored</em> Beautifully.',
            'Timeless Indian silhouettes, refined fabrics and details made for your most memorable occasions.',
            'Explore Collection',
            '#featured',
            'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=2200&q=90',
        );
    }
}