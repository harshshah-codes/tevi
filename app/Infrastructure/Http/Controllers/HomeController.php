<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Core\StaticPage;

final class HomeController
{
    public function __construct(
        private readonly HeroCarouselController $hero,
        private readonly CatalogController $catalog,
    ) {
    }

    public function show(): void
    {
        $html = StaticPage::render(dirname(__DIR__, 4) . '/public/static/index.html');

        $html = str_replace('<!--HERO_CAROUSEL-->', $this->hero->renderCarousel(), $html);

        $html = str_replace(
            ['<!--CATEGORY_SLIDER-->', '<!--NEW_ARRIVAL_FILTERS-->', '<!--FEATURED_PRODUCTS-->', '<!--NEW_ARRIVALS-->', '<!--HOME_REVIEWS-->'],
            [
                $this->catalog->renderCategorySlider(),
                $this->catalog->renderBadgeFilters(),
                $this->catalog->renderFeatured(),
                $this->catalog->renderNewArrivals(),
                $this->catalog->renderTestimonials(),
            ],
            $html
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }
}