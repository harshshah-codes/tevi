<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\CatalogService;
use App\Domain\Enums\Badge;
use App\Domain\Models\Category;
use App\Domain\Models\Product;

final class CatalogController
{
    public function __construct(
        private readonly CatalogService $catalog,
    ) {
    }

    public function renderCategorySlider(): string
    {
        try {
            $categories = $this->catalog->getCategories();
        } catch (\Throwable) {
            return '';
        }

        if ($categories === []) {
            return '';
        }

        $html = '';
        foreach ($categories as $category) {
            $html .= $this->renderCategoryItem($category) . "\n";
        }

        return $html;
    }

    public function renderFeatured(): string
    {
        try {
            $products = $this->catalog->getFeaturedProducts();
        } catch (\Throwable) {
            return '';
        }

        $html = '';
        foreach ($products as $product) {
            $html .= $this->renderProductCard($product);
        }

        return $html;
    }

    public function renderNewArrivals(): string
    {
        try {
            $products = $this->catalog->getNewArrivals(8);
        } catch (\Throwable) {
            return '';
        }

        $html = '';
        foreach ($products as $product) {
            $html .= $this->renderProductCard($product);
        }

        return $html;
    }

    public function renderBadgeFilters(): string
    {
        $html = '<button class="catalog-tab active" data-filter="all">ALL</button>' . "\n";

        foreach (Badge::cases() as $badge) {
            $label = htmlspecialchars($badge->value, ENT_QUOTES);
            $html .= '<button class="catalog-tab" data-filter="' . $label . '">' . strtoupper($label) . '</button>' . "\n";
        }

        return rtrim($html, "\n");
    }

    /**
     * @return string
     */
    public function renderTestimonials(): string
    {
        try {
            $reviews = $this->catalog->getVisibleReviews();
        } catch (\Throwable) {
            $reviews = [];
        }

        if ($reviews === []) {
            return $this->fallbackTestimonials();
        }

        $items = '';
        $dots = '';
        foreach ($reviews as $index => $review) {
            $active = $index === 0 ? ' active' : '';
            $activeDot = $index === 0 ? ' active-dot' : '';
            $stars = $this->stars((float) $review->rating());
            $text = htmlspecialchars($review->text(), ENT_QUOTES);
            $author = htmlspecialchars($review->author(), ENT_QUOTES);
            $context = htmlspecialchars($review->context(), ENT_QUOTES);
            $suffix = $context !== '' ? ' <span>· ' . $context . '</span>' : '';

            $items .= '<div class="carousel-item' . $active . '"><div class="testimonial-card text-center"><div class="stars">' . $stars . '</div><blockquote>"' . $text . '"</blockquote><div class="testimonial-author"><strong>— ' . $author . '</strong>' . $suffix . '</div></div></div>' . "\n";
            $dots .= '<button type="button" class="dot' . $activeDot . '" data-bs-target="#testimonialCarousel" data-bs-slide-to="' . $index . '"></button>' . "\n";
        }

        return '<div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">' . "\n"
            . '          <div class="carousel-inner">' . "\n"
            . $items
            . '          </div>' . "\n"
            . '          <a class="carousel-control-prev" href="#testimonialCarousel" role="button" data-bs-slide="prev"><span class="testimonial-arrow">←</span></a>' . "\n"
            . '          <a class="carousel-control-next" href="#testimonialCarousel" role="button" data-bs-slide="next"><span class="testimonial-arrow">→</span></a>' . "\n"
            . '        </div>' . "\n"
            . '        <div class="testimonial-dots text-center mt-4">' . "\n"
            . $dots
            . '        </div>';
    }

    private function fallbackTestimonials(): string
    {
        $items = [
            ['★', 'Elegant, comfortable and beautifully finished. House of Viraasat has become my go-to for celebrations.', 'Vivaan Patel', 'Occasionwear'],
            ['★', 'The craftsmanship is absolutely beautiful. Every outfit feels premium and perfectly designed.', 'Aarav Shah', 'Wedding Collection'],
            ['★', 'Amazing collection, excellent fitting and a truly premium shopping experience every time.', 'Rohan Mehta', 'Ethnicwear'],
        ];

        $htmlItems = '';
        $htmlDots = '';
        foreach ($items as $i => [$starsText, $text, $author, $ctx]) {
            $active = $i === 0 ? ' active' : '';
            $activeDot = $i === 0 ? ' active-dot' : '';
            $htmlItems .= '<div class="carousel-item' . $active . '"><div class="testimonial-card text-center"><div class="stars">' . str_repeat('★', 5) . '</div><blockquote>"' . htmlspecialchars($text, ENT_QUOTES) . '"</blockquote><div class="testimonial-author"><strong>— ' . htmlspecialchars($author, ENT_QUOTES) . '</strong> <span>· ' . htmlspecialchars($ctx, ENT_QUOTES) . '</span></div></div></div>' . "\n";
            $htmlDots .= '<button type="button" class="dot' . $activeDot . '" data-bs-target="#testimonialCarousel" data-bs-slide-to="' . $i . '"></button>' . "\n";
        }

        return '<div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">' . "\n"
            . '          <div class="carousel-inner">' . "\n"
            . $htmlItems
            . '          </div>' . "\n"
            . '          <a class="carousel-control-prev" href="#testimonialCarousel" role="button" data-bs-slide="prev"><span class="testimonial-arrow">←</span></a>' . "\n"
            . '          <a class="carousel-control-next" href="#testimonialCarousel" role="button" data-bs-slide="next"><span class="testimonial-arrow">→</span></a>' . "\n"
            . '        </div>' . "\n"
            . '        <div class="testimonial-dots text-center mt-4">' . "\n"
            . $htmlDots
            . '        </div>';
    }

    private function stars(float $avg): string
    {
        $filled = max(0, min(5, (int) round($avg)));

        return str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
    }

    private function renderCategoryItem(Category $category): string
    {
        $name = htmlspecialchars($category->name(), ENT_QUOTES);
        $image = htmlspecialchars($category->image(), ENT_QUOTES);
        $tagline = htmlspecialchars($category->tagline(), ENT_QUOTES);

        return '<a href="#featured" class="category-item">'
            . '<div class="category-image"><img src="' . $image . '" alt="' . $name . '"></div>'
            . '<h3>' . $name . '</h3>'
            . '<span>' . $tagline . '</span>'
            . '</a>';
    }

    private function renderProductCard(Product $product): string
    {
        $name = htmlspecialchars($product->name(), ENT_QUOTES);
        $image = htmlspecialchars($product->image(), ENT_QUOTES);
        $badgeValue = $product->badge()?->value ?? '';
        $badge = htmlspecialchars($badgeValue, ENT_QUOTES);
        $slug = htmlspecialchars($product->slug(), ENT_QUOTES);
        $price = '₹' . number_format($product->price());
        $categorySlug = $product->categories() !== []
            ? $product->categories()[0]->slug()
            : '';
        $categoryName = $product->categories() !== []
            ? $product->categories()[0]->name()
            : '';
        $payload = json_encode([
            'id'       => $product->id(),
            'name'     => $product->name(),
            'category' => $categoryName,
            'price'    => $product->price(),
            'image'    => $product->image(),
            'badge'    => $badgeValue,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadAttr = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

        $sizesHtml = '';
        foreach ($product->sizes() as $size) {
            $sizesHtml .= '<button type="button" class="size-btn">'
                . htmlspecialchars($size, ENT_QUOTES)
                . '</button>';
        }

        return '<div class="col-6 col-lg-3 product-item" data-category="' . htmlspecialchars($categorySlug, ENT_QUOTES) . '" data-best="true" data-badge="' . $badge . '">' . "\n"
            . '  <article class="catalog-card" data-product-url="product?slug=' . $slug . '">' . "\n"
            . '    <div class="catalog-image-wrap">' . "\n"
            . '      <a class="catalog-image-link" href="product?slug=' . $slug . '"><img src="' . $image . '" alt="' . $name . '" loading="lazy"></a>' . "\n"
            . '      <span class="catalog-badge">' . $badge . '</span>' . "\n"
            . '      <button class="catalog-heart wishlist-btn" data-product=\'' . $payloadAttr . '\' aria-label="Wishlist"><i class="bi bi-heart"></i></button>' . "\n"
            . '      <button class="catalog-bag quick-add" aria-label="Add to bag" data-product=\'' . $payloadAttr . '\'><i class="bi bi-bag-plus"></i></button>' . "\n"
            . '    </div>' . "\n"
            . '    <div class="catalog-info">' . "\n"
            . '      <div class="catalog-product-name"><a href="product?slug=' . $slug . '">' . $name . '</a></div>' . "\n"
            . '      <div class="catalog-price">' . $price . '</div>' . "\n"
            . '      <div class="size-list">' . $sizesHtml . '</div>' . "\n"
            . '    </div>' . "\n"
            . '  </article>' . "\n"
            . '</div>';
    }
}