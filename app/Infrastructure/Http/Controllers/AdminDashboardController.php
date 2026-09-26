<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminHeroService;
use App\Application\Services\AdminProductService;
use App\Application\Services\AdminCategoryService;
use App\Application\Services\AdminReviewService;

final class AdminDashboardController extends AdminController
{
    public function __construct(
        private readonly AdminHeroService $heroService,
        private readonly AdminProductService $productService,
        private readonly AdminCategoryService $categoryService,
        private readonly AdminReviewService $reviewService,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $heroCount = count($this->heroService->getAllSlides());
        $productCount = count($this->productService->getAllProducts());
        $categoryCount = count($this->categoryService->getAllCategories());
        $reviewCount = count($this->reviewService->getAllReviews());

        $content = $this->render('dashboard.php', [
            'HERO_COUNT'      => $heroCount,
            'PRODUCT_COUNT'   => $productCount,
            'CATEGORY_COUNT'  => $categoryCount,
            'REVIEW_COUNT'    => $reviewCount,
        ]);

        $layout = $this->render('layout.php', [
            'TITLE'              => 'Dashboard',
            'CONTENT'            => $content,
            'ACTIVE_DASHBOARD'   => 'active',
            'ACTIVE_HERO'        => '',
            'ACTIVE_PRODUCTS'    => '',
            'ACTIVE_CATEGORIES'  => '',
            'ACTIVE_REVIEWS'     => '',
        ]);

        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}