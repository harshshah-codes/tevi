<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Repositories\ProductRepositoryInterface;

/**
 * Single source of truth for order money.
 *
 * The browser sends what it *thinks* it is paying; nothing is charged or stored
 * until it has been recomputed here from catalog prices.
 */
final class PricingService
{
    /** Orders at or above this subtotal ship free. */
    public const FREE_SHIPPING_THRESHOLD = 2999;

    public const SHIPPING_FEE = 199;

    /** GST, as a fraction. */
    public const TAX_RATE = 0.05;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * Recompute an order's money from the requested items.
     *
     * Client-supplied prices are ignored entirely: each item is priced from
     * `products.price` (falling back to 0 when the product no longer exists,
     * which the caller should treat as a hard error for a paid order).
     *
     * @param array<int, array{product_id?: int|null, qty: int}> $items
     * @return array{
     *     items: array<int, array{product_id: int|null, product_name: string, size: string, color: string, price: int, qty: int, image: string, weight: float}>,
     *     subtotal: int,
     *     shipping: int,
     *     tax: int,
     *     total: int,
     *     unknownProducts: int[]
     * }
     */
    public function quote(array $items, string $paymentMethod = 'cod'): array
    {
        $pricedItems = [];
        $subtotal = 0;
        $unknownProducts = [];

        foreach ($items as $item) {
            $productId = isset($item['product_id']) && $item['product_id'] !== null ? (int) $item['product_id'] : null;
            $qty = max(1, (int) ($item['qty'] ?? 1));

            $product = $productId !== null ? $this->productRepository->findById($productId) : null;
            if ($product === null) {
                // Unknown/absent product: charge nothing for it rather than
                // trusting whatever the client claimed it cost.
                $unknownProducts[] = $productId;
                continue;
            }

            // Cap quantity so a tampered payload cannot ask for 1,000,000 units.
            $qty = min($qty, 20);

            $price = (int) $product->price();
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            $pricedItems[] = [
                'product_id'   => $product->id(),
                'product_name' => $product->name(),
                'size'         => (string) ($item['size'] ?? ''),
                'color'        => (string) ($item['color'] ?? ''),
                'price'        => $price,
                'qty'          => $qty,
                'image'        => (string) $product->image(),
                // Snapshotted so the declared parcel weight survives later
                // edits to the product.
                'weight'       => (float) $product->weight(),
            ];
        }

        $shipping = $this->shippingFor($subtotal, $paymentMethod);
        $tax = (int) round($subtotal * self::TAX_RATE);

        return [
            'items'           => $pricedItems,
            'subtotal'        => $subtotal,
            'shipping'        => $shipping,
            'tax'             => $tax,
            'total'           => $subtotal + $shipping + $tax,
            'unknownProducts' => $unknownProducts,
        ];
    }

    /**
     * Free at or above the threshold.
     */
    public function shippingFor(int $subtotal, string $paymentMethod = 'cod'): int
    {
        return $subtotal >= self::FREE_SHIPPING_THRESHOLD ? 0 : self::SHIPPING_FEE;
    }
}
