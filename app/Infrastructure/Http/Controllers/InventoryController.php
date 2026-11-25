<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\InventoryService;

/**
 * Public stock lookup, so the storefront can disable an option before a
 * customer reaches checkout. Advisory only: the order endpoint re-checks
 * inside a transaction, because this can always be stale by the time the
 * customer presses pay.
 */
final class InventoryController
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {
    }

    /**
     * GET /api/inventory?productId=1
     */
    public function show(): void
    {
        $this->header();

        $productId = (int) ($_GET['productId'] ?? 0);
        if ($productId <= 0) {
            $this->respond(['success' => false, 'error' => 'productId is required'], 422);
        }

        $variants = array_map(
            static fn($v) => $v->toArray(),
            $this->inventory->variantsFor($productId)
        );

        $this->respond([
            'success'  => true,
            'variants' => $variants,
            'anyInStock' => count(array_filter($variants, static fn(array $v) => $v['isInStock'])) > 0,
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
}
