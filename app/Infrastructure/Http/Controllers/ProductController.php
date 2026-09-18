<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\CatalogService;
use App\Core\StaticPage;
use App\Domain\Models\Category;
use App\Domain\Models\Product;
use App\Domain\Models\Review;

final class ProductController
{
    private const TEMPLATE = '/public/static/product.html';

    private const COLOR_HEX = [
        'ivory'         => '#eee5d7',
        'gold'          => '#c9a24b',
        'midnight blue' => '#1c2440',
        'black'         => '#1e1c1b',
        'sage'          => '#9aa28c',
        'beige'         => '#d8c7a8',
        'charcoal'      => '#3a3a3a',
        'navy'          => '#1f2a44',
        'maroon'        => '#651d1b',
        'rust'          => '#8a3b21',
        'sandstone'     => '#cbb79a',
        'olive'         => '#6b6f3f',
    ];

    public function __construct(
        private readonly CatalogService $catalog,
    ) {
    }

    public function show(): void
    {
        $slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $product = null;
        try {
            $product = $this->catalog->getProductBySlug($slug);
            if ($product === null) {
                $product = $this->catalog->getProductById($id);
            }
        } catch (\Throwable) {
            $product = null;
        }

        if (!$product instanceof Product) {
            $this->notFound();
        }

        try {
            $related = $this->catalog->getRelatedProducts($product, 4);
        } catch (\Throwable) {
            $related = [];
        }

        try {
            $reviewSummary = $this->catalog->getReviewSummary($product);
            $reviews = $this->catalog->getReviewsForProduct($product);
        } catch (\Throwable) {
            $reviewSummary = ['avg' => 0.0, 'count' => 0];
            $reviews = [];
        }

        $html = str_replace(
            [
                '<!--PRODUCT_BREADCRUMB-->',
                '<!--PRODUCT_GALLERY-->',
                '<!--PRODUCT_EYEBROW-->',
                '<!--PRODUCT_TITLE-->',
                '<!--PRODUCT_PRICE-->',
                '<!--PRODUCT_STARS-->',
                '<!--PRODUCT_RATING-->',
                '<!--PRODUCT_DESCRIPTION-->',
                '<!--PRODUCT_SELECTED_COLOR-->',
                '<!--PRODUCT_COLORS-->',
                '<!--PRODUCT_SIZES-->',
                '<!--PRODUCT_SIZE_TITLE-->',
                '<!--PRODUCT_SPECS-->',
                '<!--PRODUCT_REVIEWS-->',
                '<!--PRODUCT_RELATED-->',
                '/*PRODUCT_DATA*/',
            ],
            [
                $this->renderBreadcrumb($product),
                $this->renderGallery($product),
                $this->renderEyebrow($product),
                htmlspecialchars($product->name(), ENT_QUOTES),
                '₹' . number_format($product->price()),
                $this->renderStars((float) $reviewSummary['avg']),
                $this->renderRatingText((float) $reviewSummary['avg'], (int) $reviewSummary['count']),
                htmlspecialchars($product->description(), ENT_QUOTES),
                htmlspecialchars($this->primaryColor($product), ENT_QUOTES),
                $this->renderColors($product),
                $this->renderSizes($product),
                htmlspecialchars($this->primaryCategoryName($product) . ' Size Guide', ENT_QUOTES),
                $this->renderSpecs($product),
                $this->renderReviews($reviews),
                $this->renderRelated($related),
                $this->renderProductJson($product),
            ],
            StaticPage::render(dirname(__DIR__, 4) . self::TEMPLATE)
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    private function renderBreadcrumb(Product $product): string
    {
        $home = '<li class="breadcrumb-item"><a href="./">Home</a></li>';
        $categoryName = $this->primaryCategoryName($product);
        $category = $categoryName !== ''
            ? '<li class="breadcrumb-item"><a href="index#featured">' . htmlspecialchars($categoryName, ENT_QUOTES) . '</a></li>'
            : '';

        return $home . "\n"
            . '            ' . $category . "\n"
            . '            <li class="breadcrumb-item active">' . htmlspecialchars($product->name(), ENT_QUOTES) . '</li>';
    }

    private function renderGallery(Product $product): string
    {
        $images = [$product->image()];
        foreach ($product->categories() as $category) {
            if ($category->image() !== '' && !in_array($category->image(), $images, true)) {
                $images[] = $category->image();
            }
        }
        $images = array_slice($images, 0, 4);

        $count = max(count($images), 1);
        $labels = ['Front', 'Detail', 'Back', 'Styling'];

        $thumbs = '';
        foreach ($images as $index => $image) {
            $src = htmlspecialchars($image, ENT_QUOTES);
            $alt = $labels[$index] ?? 'View ' . ($index + 1);
            $active = $index === 0 ? ' active' : '';
            $thumbs .= '                  <button class="thumbnail' . $active . '" data-image="' . $src . '"><img src="' . $src . '" alt="' . $alt . '"></button>' . "\n";
        }

        $main = htmlspecialchars($images[0] ?? '', ENT_QUOTES);
        $name = htmlspecialchars($product->name(), ENT_QUOTES);

        return '<div class="gallery-layout">' . "\n"
            . '                <div class="thumbnail-list">' . "\n"
            . $thumbs
            . '                </div>' . "\n"
            . '                <div class="main-image-wrapper">' . "\n"
            . '                  <img id="mainProductImage" class="main-product-image" src="' . $main . '" alt="' . $name . '">' . "\n"
            . '                  <span class="zoom-label"><i class="bi bi-zoom-in me-1"></i>Zoom</span>' . "\n"
            . '                  <span class="image-counter" id="imageCounter">01 / ' . str_pad((string) $count, 2, '0', STR_PAD_LEFT) . '</span>' . "\n"
            . '                </div>' . "\n"
            . '              </div>';
    }

    private function renderEyebrow(Product $product): string
    {
        $category = $this->primaryCategoryName($product);
        $label = $category !== '' ? 'Premium ' . $category : 'House of Viraasat';

        return htmlspecialchars($label . ' · Festive Edit', ENT_QUOTES);
    }

    private function renderColors(Product $product): string
    {
        $colors = $product->colors();
        if ($colors === []) {
            return '';
        }

        $html = '';
        foreach ($colors as $index => $color) {
            $hex = self::COLOR_HEX[strtolower(trim($color))] ?? '#d9d0c7';
            $name = htmlspecialchars($color, ENT_QUOTES);
            $active = $index === 0 ? ' active' : '';
            $html .= '                <button type="button" class="color-option' . $active . '" style="background:' . $hex . '" data-color="' . $name . '" title="' . $name . '"></button>' . "\n";
        }

        return rtrim($html, "\n");
    }

    private function renderSizes(Product $product): string
    {
        $sizes = $product->sizes();
        if ($sizes === []) {
            $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        }

        $default = in_array('M', $sizes, true) ? 'M' : $sizes[0];

        $html = '';
        foreach ($sizes as $size) {
            $active = $size === $default ? ' active' : '';
            $html .= '                <button class="size-option' . $active . '">' . htmlspecialchars($size, ENT_QUOTES) . '</button>' . "\n";
        }

        return rtrim($html, "\n");
    }

    private function renderSpecs(Product $product): string
    {
        $category = $this->primaryCategoryName($product);
        $rows = [
            'Product Type'      => $category !== '' ? $category : 'Ethnic Wear',
            'Fit'               => 'Regular Fit',
            'Pattern'           => 'Handwork',
            'Occasion'          => 'Festive / Wedding',
            'SKU'               => 'HOV-' . str_pad((string) $product->id(), 4, '0', STR_PAD_LEFT),
            'Country of Origin' => 'India',
        ];

        $html = '';
        foreach ($rows as $label => $value) {
            $html .= '                <div class="spec-row"><span>' . htmlspecialchars($label, ENT_QUOTES) . '</span><span>' . htmlspecialchars($value, ENT_QUOTES) . '</span></div>' . "\n";
        }

        return rtrim($html, "\n");
    }

    private function renderStars(float $avg): string
    {
        $filled = max(0, min(5, (int) round($avg)));

        return str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
    }

    private function renderRatingText(float $avg, int $count): string
    {
        if ($count === 0) {
            return 'No reviews yet';
        }

        return sprintf('%.1f', $avg) . ' · ' . $count . ($count === 1 ? ' Review' : ' Reviews');
    }

    /**
     * @param Review[] $reviews
     */
    private function renderReviews(array $reviews): string
    {
        if ($reviews === []) {
            return '<div class="review-card"><p class="review-text">No reviews yet. Be the first to review this product.</p></div>';
        }

        $html = '';
        foreach ($reviews as $review) {
            $author = htmlspecialchars($review->author(), ENT_QUOTES);
            $date = date('j M Y', strtotime($review->date())) ?: $review->date();
            $date = htmlspecialchars($date, ENT_QUOTES);
            $stars = $this->renderStars((float) $review->rating());
            $text = htmlspecialchars($review->text(), ENT_QUOTES);

            $html .= '<div class="review-card"><div class="review-header"><span class="review-author">' . $author . '</span><span class="review-date">' . $date . '</span></div><div class="review-stars">' . $stars . '</div><p class="review-text">' . $text . '</p></div>' . "\n";
        }

        return rtrim($html, "\n");
    }

    /**
     * @param Product[] $related
     */
    private function renderRelated(array $related): string
    {
        $html = '';
        foreach ($related as $product) {
            $categoryName = $this->primaryCategoryName($product);
            $slug = htmlspecialchars($product->slug(), ENT_QUOTES);
            $name = htmlspecialchars($product->name(), ENT_QUOTES);
            $image = htmlspecialchars($product->image(), ENT_QUOTES);

            $html .= '          <div class="col-6 col-lg-3"><a href="product?slug=' . $slug . '" class="related-card"><div class="related-image"><img src="' . $image . '" alt="' . $name . '"></div><div class="related-info"><div class="related-category">' . htmlspecialchars($categoryName, ENT_QUOTES) . '</div><div class="related-name">' . $name . '</div><div class="related-price">₹' . number_format($product->price()) . '</div></div></a></div>' . "\n";
        }

        return rtrim($html, "\n");
    }

    private function renderProductJson(Product $product): string
    {
        $payload = [
            'id'       => $product->id(),
            'name'     => $product->name(),
            'slug'     => $product->slug(),
            'price'    => $product->price(),
            'category' => $this->primaryCategoryName($product),
            'image'    => $product->image(),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function primaryColor(Product $product): string
    {
        return $product->colors()[0] ?? '';
    }

    private function primaryCategoryName(Product $product): string
    {
        $category = $product->categories()[0] ?? null;

        return $category instanceof Category ? $category->name() : '';
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');

        $page = dirname(__DIR__, 4) . '/public/static/404.html';
        if (is_file($page)) {
            readfile($page);
        } else {
            echo '<h1>404 Not Found</h1><p>The product you requested does not exist.</p>';
        }

        exit;
    }
}