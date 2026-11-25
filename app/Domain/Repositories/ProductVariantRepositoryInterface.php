<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Models\ProductVariant;

interface ProductVariantRepositoryInterface
{
    /**
     * @return ProductVariant[]
     */
    public function findForProduct(int $productId, bool $includeInactive = true): array;

    public function findById(int $variantId): ?ProductVariant;

    /**
     * Exact colour + size match. Colours and sizes are matched case- and
     * whitespace-insensitively because they arrive free-text from the cart.
     */
    public function findByProductColourSize(int $productId, string $colour, string $size): ?ProductVariant;

    /**
     * @return array<string, array{stock:int, inStock:bool, label:string}> keyed "colour|size"
     */
    public function availabilityForProduct(int $productId): array;

    /**
     * @return array<string, int> stock keyed "productId|colour|size"
     */
    public function stockForKeys(array $keys): array;

    /**
     * Create any variants the product is missing from its sizes x colours.
     *
     * @param string[] $sizes
     * @param string[] $colours
     * @return int number created
     */
    public function syncFromProductOptions(int $productId, array $sizes, array $colours): int;

    /**
     * @return int rows updated
     */
    public function setLowStockThreshold(int $variantId, int $threshold): int;

    public function setActive(int $variantId, bool $active): int;
}
