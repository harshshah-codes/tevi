<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\WishlistService;

final class WishlistController
{
    public function __construct(
        private readonly WishlistService $wishlist,
    ) {
    }

    /**
     * GET /api/wishlist
     */
    public function index(): void
    {
        $this->header();
        $userId = $this->requireUser();

        $items = array_map(static fn($i) => $i->toArray(), $this->wishlist->listForUser($userId));

        $this->respond(['success' => true, 'items' => $items, 'count' => count($items)]);
    }

    /**
     * POST /api/wishlist  { productId, size?, color? }
     */
    public function store(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $data = $this->body();

        $productId = (int) ($data['productId'] ?? 0);
        if ($productId <= 0) {
            $this->respond(['success' => false, 'error' => 'productId is required'], 422);
        }

        $item = $this->wishlist->add(
            $userId,
            $productId,
            trim((string) ($data['size'] ?? '')),
            trim((string) ($data['color'] ?? '')),
        );

        if ($item === null) {
            $this->respond(['success' => false, 'error' => 'That product is no longer available'], 404);
        }

        $this->respond([
            'success' => true,
            'item'    => $item->toArray(),
            'count'   => count($this->wishlist->listForUser($userId)),
        ]);
    }

    /**
     * PATCH /api/wishlist/{productId}  { size?, color? }
     */
    public function update(int $productId): void
    {
        $this->header();

        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $data = $this->body();

        $existing = $this->wishlist->contains($userId, $productId);
        if (!$existing) {
            $this->respond(['success' => false, 'error' => 'That item is not on your wishlist'], 404);
        }

        $this->wishlist->updateOptions(
            $userId,
            $productId,
            trim((string) ($data['size'] ?? '')),
            trim((string) ($data['color'] ?? '')),
        );

        // Re-read rather than re-inserting, so the response reflects the saved row.
        $updated = null;
        foreach ($this->wishlist->listForUser($userId) as $item) {
            if ($item->productId() === $productId) {
                $updated = $item;
                break;
            }
        }

        $this->respond(['success' => true, 'item' => $updated?->toArray()]);
    }

    /**
     * DELETE /api/wishlist/{productId}
     */
    public function destroy(int $productId): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();

        $removed = $this->wishlist->remove($userId, $productId);
        $count = count($this->wishlist->listForUser($userId));

        if (!$removed) {
            $this->respond(['success' => false, 'error' => 'That item is not on your wishlist'], 404);
        }

        $this->respond(['success' => true, 'removed' => $productId, 'count' => $count]);
    }

    /**
     * DELETE /api/wishlist — clear the whole list.
     */
    public function clear(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $removed = $this->wishlist->clear($userId);

        $this->respond(['success' => true, 'removed' => $removed]);
    }

    /**
     * POST /api/wishlist/merge  { productIds: [...] }
     *
     * Folds a guest's localStorage list into their account the first time an
     * authenticated page loads.
     */
    public function merge(): void
    {
        $this->header();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $userId = $this->requireUser();
        $data = $this->body();

        $ids = $data['productIds'] ?? [];
        if (!is_array($ids)) {
            $this->respond(['success' => false, 'error' => 'productIds must be an array'], 422);
        }

        $result = $this->wishlist->merge($userId, array_map('intval', $ids));

        $this->respond([
            'success' => true,
            'added'   => $result['added'],
            'skipped' => $result['skipped'],
            'items'   => array_map(static fn($i) => $i->toArray(), $this->wishlist->listForUser($userId)),
        ]);
    }

    private function header(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    private function respond(array $payload, int $status = 200): void
    {
        ob_end_clean();
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private function requireUser(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->respond(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        return (int) $_SESSION['user_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($data)) {
            $this->respond(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        return $data;
    }
}