<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminCategoryService;
use App\Domain\Models\Category;

final class AdminCategoryController extends AdminController
{
    public function __construct(
        private readonly AdminCategoryService $service,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $categories = $this->service->getAllCategories();
        $flash = $this->getFlash();

        $rows = '';
        foreach ($categories as $category) {
            $rows .= '<tr>'
                . '<td>' . $category->id() . '</td>'
                . '<td><img src="' . htmlspecialchars($category->image(), ENT_QUOTES) . '" alt="" style="width: 60px; height: 40px; object-fit: cover;"></td>'
                . '<td>' . htmlspecialchars($category->name(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($category->slug(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($category->tagline(), ENT_QUOTES) . '</td>'
                . '<td class="text-end">'
                . '<a href="categories/edit?id=' . $category->id() . '" class="btn btn-sm btn-outline-primary">Edit</a> '
                . '<form action="categories/delete" method="post" style="display:inline" onsubmit="return confirm(\'Delete this category?\')">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $category->id() . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $content = $this->render('categories/index.php', [
            'FLASH' => $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
            'ROWS'  => $rows,
        ]);

        $this->output($content);
    }

    public function create(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $category = new Category(
                0,
                trim($_POST['name'] ?? ''),
                trim($_POST['slug'] ?? ''),
                trim($_POST['image'] ?? ''),
                trim($_POST['tagline'] ?? ''),
            );
            $this->service->createCategory($category);
            $this->flash('Category created successfully');
            $this->redirect('categories');
        }

        $content = $this->render('categories/form.php', [
            'TITLE'      => 'Create Category',
            'ACTION'     => 'categories/create',
            'CSRF_TOKEN' => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $category = $this->service->getCategory($id);

        if (!$category) {
            $this->flash('Category not found', 'danger');
            $this->redirect('categories');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $category = new Category(
                $id,
                trim($_POST['name'] ?? ''),
                trim($_POST['slug'] ?? ''),
                trim($_POST['image'] ?? ''),
                trim($_POST['tagline'] ?? ''),
            );
            $this->service->updateCategory($category);
            $this->flash('Category updated successfully');
            $this->redirect('categories');
        }

        $content = $this->render('categories/form.php', [
            'TITLE'             => 'Edit Category',
            'ACTION'            => 'categories/edit?id=' . $id,
            'CATEGORY_NAME'     => htmlspecialchars($category->name(), ENT_QUOTES),
            'CATEGORY_SLUG'     => htmlspecialchars($category->slug(), ENT_QUOTES),
            'CATEGORY_IMAGE'    => htmlspecialchars($category->image(), ENT_QUOTES),
            'CATEGORY_TAGLINE'  => htmlspecialchars($category->tagline(), ENT_QUOTES),
            'CSRF_TOKEN'        => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function delete(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('categories');
        }
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $this->service->deleteCategory($id);
        $this->flash('Category deleted successfully');
        $this->redirect('categories');
    }

    private function output(string $content): void
    {
        $layout = $this->render('layout.php', [
            'TITLE'              => 'Categories',
            'CONTENT'            => $content,
            'ACTIVE_CATEGORIES'  => 'active',
            'ACTIVE_DASHBOARD'   => '',
            'ACTIVE_HERO'        => '',
            'ACTIVE_PRODUCTS'    => '',
            'ACTIVE_REVIEWS'     => '',
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}