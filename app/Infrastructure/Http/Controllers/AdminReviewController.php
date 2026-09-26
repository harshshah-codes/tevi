<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminReviewService;
use App\Domain\Models\Review;

final class AdminReviewController extends AdminController
{
    public function __construct(
        private readonly AdminReviewService $service,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $reviews = $this->service->getAllReviews();
        $flash = $this->getFlash();

        $rows = '';
        foreach ($reviews as $review) {
            $stars = str_repeat('★', $review->rating()) . str_repeat('☆', 5 - $review->rating());

            $rows .= '<tr>'
                . '<td>' . $review->id() . '</td>'
                . '<td>Product #' . $review->productId() . '</td>'
                . '<td>' . htmlspecialchars($review->author(), ENT_QUOTES) . '</td>'
                . '<td>' . $stars . '</td>'
                . '<td>' . htmlspecialchars(substr($review->text(), 0, 100), ENT_QUOTES) . ($review->text() > 100 ? '...' : '') . '</td>'
                . '<td>' . htmlspecialchars($review->date(), ENT_QUOTES) . '</td>'
                . '<td>'
                . '<form action="reviews/visibility" method="post" style="display:inline">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $review->id() . '">'
                . '<input type="hidden" name="visible" value="1">'
                . '<button type="submit" class="btn btn-sm btn-outline-success">Show</button>'
                . '</form> '
                . '<form action="reviews/visibility" method="post" style="display:inline">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $review->id() . '">'
                . '<input type="hidden" name="visible" value="0">'
                . '<button type="submit" class="btn btn-sm btn-outline-secondary">Hide</button>'
                . '</form>'
                . '</td>'
                . '<td class="text-end">'
                . '<a href="reviews/edit?id=' . $review->id() . '" class="btn btn-sm btn-outline-primary">Edit</a> '
                . '<form action="reviews/delete" method="post" style="display:inline" onsubmit="return confirm(\'Delete this review?\')">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $review->id() . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $content = $this->render('reviews/index.php', [
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
            $review = new Review(
                0,
                (int) ($_POST['product_id'] ?? 0),
                trim($_POST['author'] ?? ''),
                (int) ($_POST['rating'] ?? 5),
                trim($_POST['review_text'] ?? ''),
                trim($_POST['created_at'] ?? date('Y-m-d')),
                trim($_POST['context'] ?? ''),
            );
            $this->service->createReview($review);
            $this->flash('Review created successfully');
            $this->redirect('reviews');
        }

        $content = $this->render('reviews/form.php', [
            'TITLE'      => 'Create Review',
            'ACTION'     => 'reviews/create',
            'CSRF_TOKEN' => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $review = $this->service->getReview($id);

        if (!$review) {
            $this->flash('Review not found', 'danger');
            $this->redirect('reviews');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $review = new Review(
                $id,
                (int) ($_POST['product_id'] ?? 0),
                trim($_POST['author'] ?? ''),
                (int) ($_POST['rating'] ?? 5),
                trim($_POST['review_text'] ?? ''),
                trim($_POST['created_at'] ?? date('Y-m-d')),
                trim($_POST['context'] ?? ''),
            );
            $this->service->updateReview($review);
            $this->flash('Review updated successfully');
            $this->redirect('reviews');
        }

        $ratingOptions = '';
        for ($i = 5; $i >= 1; $i--) {
            $selected = $review->rating() === $i ? 'selected' : '';
            $ratingOptions .= '<option value="' . $i . '" ' . $selected . '>' . $i . ' Stars</option>';
        }

        $content = $this->render('reviews/form.php', [
            'TITLE'             => 'Edit Review',
            'ACTION'            => 'reviews/edit?id=' . $id,
            'REVIEW_PRODUCT_ID' => $review->productId(),
            'REVIEW_AUTHOR'     => htmlspecialchars($review->author(), ENT_QUOTES),
            'REVIEW_RATING_5'   => $review->rating() === 5,
            'REVIEW_RATING_4'   => $review->rating() === 4,
            'REVIEW_RATING_3'   => $review->rating() === 3,
            'REVIEW_RATING_2'   => $review->rating() === 2,
            'REVIEW_RATING_1'   => $review->rating() === 1,
            'REVIEW_TEXT'       => htmlspecialchars($review->text(), ENT_QUOTES),
            'REVIEW_DATE'       => $review->date(),
            'REVIEW_CONTEXT'    => htmlspecialchars($review->context(), ENT_QUOTES),
            'CSRF_TOKEN'        => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function visibility(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('reviews');
        }
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $visible = (bool) ($_POST['visible'] ?? false);
        $this->service->setVisibility($id, $visible);
        $this->flash($visible ? 'Review is now visible' : 'Review is now hidden');
        $this->redirect('reviews');
    }

    public function delete(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('reviews');
        }
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $this->service->deleteReview($id);
        $this->flash('Review deleted successfully');
        $this->redirect('reviews');
    }

    private function output(string $content): void
    {
        $layout = $this->render('layout.php', [
            'TITLE'             => 'Reviews',
            'CONTENT'           => $content,
            'ACTIVE_REVIEWS'    => 'active',
            'ACTIVE_DASHBOARD'  => '',
            'ACTIVE_HERO'       => '',
            'ACTIVE_PRODUCTS'   => '',
            'ACTIVE_CATEGORIES' => '',
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}