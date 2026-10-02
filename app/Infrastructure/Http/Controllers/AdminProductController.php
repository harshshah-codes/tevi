<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminProductService;
use App\Domain\Models\Product;
use App\Domain\Enums\Badge;

final class AdminProductController extends AdminController
{
    public function __construct(
        private readonly AdminProductService $service,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $products = $this->service->getAllProducts();
        $flash = $this->getFlash();

        $rows = '';
        foreach ($products as $product) {
            $badge = $product->badge()?->value ?? '';
            $categoryNames = array_map(fn ($c) => $c->name(), $product->categories());
            $rows .= '<tr>'
                . '<td>' . $product->id() . '</td>'
                . '<td><img src="' . htmlspecialchars($product->image(), ENT_QUOTES) . '" alt="" style="width: 60px; height: 60px; object-fit: cover;"></td>'
                . '<td>' . htmlspecialchars($product->name(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($product->slug(), ENT_QUOTES) . '</td>'
                . '<td>₹' . number_format($product->price()) . '</td>'
                . '<td>' . rtrim(rtrim(number_format($product->weight(), 3, '.', ''), '0'), '.') . ' kg</td>'
                . '<td><span class="badge bg-secondary">' . htmlspecialchars($badge, ENT_QUOTES) . '</span></td>'
                . '<td>' . htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES) . '</td>'
                . '<td>' . ($product->isFeatured() ? 'Yes' : 'No') . '</td>'
                . '<td class="text-end">'
                . '<a href="products/edit?id=' . $product->id() . '" class="btn btn-sm btn-outline-primary">Edit</a> '
                . '<form action="products/delete" method="post" style="display:inline" onsubmit="return confirm(\'Delete this product?\')">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $product->id() . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $content = $this->render('products/index.php', [
            'FLASH' => $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
            'ROWS'  => $rows,
        ]);

        $this->output($content);
    }

    public function create(): void
    {
        $this->requireAuth();
        $categories = $this->service->getAllCategories();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $product = $this->productFromPost(0);
            $this->service->createProduct($product);
            $this->flash('Product created successfully');
            $this->redirect('products');
        }

        $badgeOptions = '';
        foreach (Badge::cases() as $badge) {
            $badgeOptions .= '<option value="' . $badge->value . '">' . $badge->value . '</option>';
        }

        $categoryOptions = '';
        foreach ($categories as $category) {
            $categoryOptions .= '<div class="form-check">'
                . '<input type="checkbox" name="categories[]" value="' . $category->id() . '" class="form-check-input" id="cat_' . $category->id() . '">'
                . '<label class="form-check-label" for="cat_' . $category->id() . '">' . htmlspecialchars($category->name(), ENT_QUOTES) . '</label>'
                . '</div>';
        }

        $content = $this->render('products/form.php', [
            'TITLE'        => 'Create Product',
            'ACTION'       => 'products/create',
            'BADGES'       => $badgeOptions,
            'CATEGORIES'   => $categoryOptions,
            'CSRF_TOKEN'   => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $product = $this->service->getProduct($id);
        $categories = $this->service->getAllCategories();

        if (!$product) {
            $this->flash('Product not found', 'danger');
            $this->redirect('products');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $product = $this->productFromPost($id);
            $this->service->updateProduct($product);
            $this->flash('Product updated successfully');
            $this->redirect('products');
        }

        $selectedCategoryIds = array_map(fn ($c) => $c->id(), $product->categories());

        $badgeOptions = '';
        foreach (Badge::cases() as $badge) {
            $selected = $product->badge()?->value === $badge->value ? 'selected' : '';
            $badgeOptions .= '<option value="' . $badge->value . '" ' . $selected . '>' . $badge->value . '</option>';
        }

        $categoryOptions = '';
        foreach ($categories as $category) {
            $checked = in_array($category->id(), $selectedCategoryIds) ? 'checked' : '';
            $categoryOptions .= '<div class="form-check">'
                . '<input type="checkbox" name="categories[]" value="' . $category->id() . '" class="form-check-input" id="cat_' . $category->id() . '" ' . $checked . '>'
                . '<label class="form-check-label" for="cat_' . $category->id() . '">' . htmlspecialchars($category->name(), ENT_QUOTES) . '</label>'
                . '</div>';
        }

        $content = $this->render('products/form.php', [
            'TITLE'            => 'Edit Product',
            'ACTION'           => 'products/edit?id=' . $id,
            'PRODUCT'          => true,
            'PRODUCT_NAME'     => htmlspecialchars($product->name(), ENT_QUOTES),
            'PRODUCT_SLUG'     => htmlspecialchars($product->slug(), ENT_QUOTES),
            'PRODUCT_DESCRIPTION' => htmlspecialchars($product->description(), ENT_QUOTES),
            'PRODUCT_PRICE'    => $product->price(),
            'PRODUCT_IMAGE'    => htmlspecialchars($product->image(), ENT_QUOTES),
            'PRODUCT_SIZES'    => htmlspecialchars(implode(', ', $product->sizes()), ENT_QUOTES),
            'PRODUCT_COLORS'   => htmlspecialchars(implode(', ', $product->colors()), ENT_QUOTES),
            'PRODUCT_FEATURED' => $product->isFeatured(),
            'PRODUCT_WEIGHT'  => rtrim(rtrim(number_format($product->weight(), 3, '.', ''), '0'), '.'),
            'BADGES'           => $badgeOptions,
            'CATEGORIES'       => $categoryOptions,
            'CSRF_TOKEN'       => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function delete(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('products');
        }
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $this->service->deleteProduct($id);
        $this->flash('Product deleted successfully');
        $this->redirect('products');
    }

    private function productFromPost(int $id): Product
    {
        $name        = trim($_POST['name'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = (int) ($_POST['price'] ?? 0);
        $image       = trim($_POST['image'] ?? '');
        $badgeVal    = trim($_POST['badge'] ?? '');
        $sizes       = array_filter(array_map('trim', explode(',', $_POST['sizes'] ?? '')));
        $colors      = array_filter(array_map('trim', explode(',', $_POST['colors'] ?? '')));
        $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
        $weight      = $this->weightFromPost();
        $categoryIds = array_map('intval', $_POST['categories'] ?? []);

        $badge = $badgeVal !== '' ? Badge::tryFrom($badgeVal) : null;

        return new Product(
            $id,
            $name,
            $slug,
            $description,
            $price,
            $image,
            $badge,
            array_values($sizes),
            array_values($colors),
            (bool) $isFeatured,
            [],
            $weight,
        );
    }

    /**
     * Weight in kilograms, as entered by the admin.
     *
     * Anything unparseable or non-positive falls back to the 0.5kg default:
     * Shiprocket rejects zero-weight parcels, and a wrong-but-plausible number
     * is safer than a rejected shipment.
     */
    private function weightFromPost(): float
    {
        $raw = str_replace(',', '.', trim((string) ($_POST['weight'] ?? '')));

        if ($raw === '' || !is_numeric($raw)) {
            return 0.5;
        }

        $weight = (float) $raw;

        return ($weight > 0 && $weight <= 100) ? round($weight, 3) : 0.5;
    }

    private function output(string $content): void
    {
        $layout = $this->render('layout.php', [
            'TITLE'              => 'Products',
            'CONTENT'            => $content,
            'ACTIVE_PRODUCTS'    => 'active',
            'ACTIVE_DASHBOARD'   => '',
            'ACTIVE_HERO'        => '',
            'ACTIVE_CATEGORIES'  => '',
            'ACTIVE_REVIEWS'     => '',
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}