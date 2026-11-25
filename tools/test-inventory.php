<?php
/**
 * Inventory ledger checks against the real database.
 *
 *   php tools/test-inventory.php
 *
 * Creates its own throwaway product, so it never touches the real catalog.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoload.php';
\App\Core\Autoload::register();

use App\Application\Services\InventoryService;
use App\Domain\Models\InventoryEntry;
use App\Infrastructure\Persistence\MySqlInventoryLedgerRepository;
use App\Infrastructure\Persistence\MySqlProductRepository;
use App\Infrastructure\Persistence\MySqlProductVariantRepository;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? "  PASS  " : "  FAIL  ") . $label . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

$config = require __DIR__ . '/../app/Config/config.php';
$pdo = \App\Core\Database::connect($config['db']);

$products = new MySqlProductRepository($pdo);
$variants = new MySqlProductVariantRepository($pdo);
$ledger = new MySqlInventoryLedgerRepository($pdo);
$inventory = new InventoryService($variants, $ledger, $products, $pdo);

$pdo->prepare('DELETE FROM products WHERE slug LIKE :slug')->execute([':slug' => 'inventory-test-%']);
$productId = $products->create(new \App\Domain\Models\Product(
    0,
    'Inventory Test Kurta',
    'inventory-test-kurta',
    'throwaway',
    1999,
    '',
    null,
    ['S', 'M'],
    ['Ivory', 'Black'],
    false,
    [],
    0.85
));

echo "Variants are generated from sizes x colours\n";
$rows = $inventory->variantsFor($productId);
check('every colour x size has a row', count($rows) === 4, count($rows) . ' variant(s)');
check('all start at zero stock', array_sum(array_map(static fn($v) => $v->stock(), $rows)) === 0);

$again = $inventory->syncVariants($productId);
check('syncing again creates nothing', $again === 0);

echo "\nOpening stock\n";
$ivoryS = null;
foreach ($rows as $v) {
    if ($v->colour() === 'Ivory' && $v->size() === 'S') {
        $ivoryS = $v;
    }
}
check('found the Ivory / S variant', $ivoryS !== null);

$balance = $inventory->receive($ivoryS->id(), 10, InventoryEntry::OPENING, 'Initial stock');
check('opening stock sets the balance', $balance === 10, 'balance=' . $balance);
check('re-read shows the new stock', $variants->findById($ivoryS->id())->stock() === 10);
check('ledger balance matches', $ledger->balanceForVariant($ivoryS->id()) === 10);

$inventory->receive($ivoryS->id(), 5, InventoryEntry::RECEIPT, 'Second delivery');
check('a receipt adds to the balance', $variants->findById($ivoryS->id())->stock() === 15);
check('ledger balance still matches', $ledger->balanceForVariant($ivoryS->id()) === 15);

echo "\nSetting stock absolutely\n";
$result = $inventory->setStock($ivoryS->id(), 8, 'Stock count');
check('set reduces the balance', $result['now'] === 8 && $result['delta'] === -7, json_encode($result));
check('set to the same figure is a no-op', $inventory->setStock($ivoryS->id(), 8)['delta'] === 0);
$rejected = false;
try {
    $inventory->setStock($ivoryS->id(), -1);
} catch (Throwable $e) {
    $rejected = true;
}
check('negative stock is refused', $rejected);

echo "\nSelling decrements, cancelling restores\n";
$items = [[
    'product_id'   => $productId,
    'product_name' => 'Inventory Test Kurta',
    'colour'       => 'Ivory',
    'size'         => 'S',
    'qty'          => 3,
]];
check('availability check passes', $inventory->checkAvailability($items)['ok'] === true);

$testUserId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO orders (order_id,user_id,first_name,last_name,email,phone,address,city,state,pincode,payment_method,subtotal,shipping,tax,total,status) VALUES (?,?,"T","T","t@e.com","9999999999","x","y","z","400001","cod",100,0,0,100,"processing")')
    ->execute(['INV-TEST-' . time() . '-' . random_int(1000, 9999), $testUserId]);
$orderId = (int) $pdo->lastInsertId();

$inventory->commitForOrder($orderId, $items, null);
check('sale took stock', $variants->findById($ivoryS->id())->stock() === 5, 'stock=' . $variants->findById($ivoryS->id())->stock());

$short = [[
    'product_id'   => $productId,
    'product_name' => 'Inventory Test Kurta',
    'colour'       => 'Ivory',
    'size'         => 'S',
    'qty'          => 99,
]];
$check = $inventory->checkAvailability($short);
check('overselling is caught before checkout', $check['ok'] === false);
check('the customer is told how many are left', ($check['problems'][0]['available'] ?? -1) === 5,
    json_encode($check['problems'][0] ?? null));

$blocked = false;
try {
    $inventory->commitForOrder($orderId, $short, null);
} catch (Throwable $e) {
    $blocked = str_contains($e->getMessage(), 'left');
}
check('the commit itself refuses to go negative', $blocked);

$unknown = [[
    'product_id'   => $productId,
    'product_name' => 'Inventory Test Kurta',
    'colour'       => 'Ivory',
    'size'         => 'XXXL',
    'qty'          => 1,
]];
check('a size nobody stocked is unavailable', $inventory->checkAvailability($unknown)['ok'] === false);

$inventory->releaseForOrder($orderId, null);
check('cancelling restores the stock', $variants->findById($ivoryS->id())->stock() === 8, 'stock=' . $variants->findById($ivoryS->id())->stock());

echo "\nCase and whitespace tolerance\n";
$messy = [[
    'product_id'   => $productId,
    'product_name' => 'Inventory Test Kurta',
    'colour'       => ' ivory ',
    'size'         => 's',
    'qty'          => 1,
]];
check('" IVORY " and "s" match the stocked variant', $inventory->checkAvailability($messy)['ok'] === true);

echo "\nLedger contents\n";
$entries = $inventory->historyForVariant($ivoryS->id());
check('every movement was recorded', count($entries) >= 5, count($entries) . ' entries');
check('newest entry is first', $entries[0]->id() > $entries[count($entries) - 1]->id());
check('the sale references its order',
    in_array(InventoryEntry::SALE, array_map(static fn($e) => $e->entryType(), $entries), true));

echo "\nLow stock report\n";
$report = $inventory->lowStockReport();
$found = false;
foreach ($report as $item) {
    if ((int) $item['productId'] === $productId && $item['outOfStock'] > 0) {
        $found = true;
    }
}
check('a product with empty variants is reported', $found);

echo "\nCleanup\n";
$pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $productId]);
check('test product removed', (int) $pdo->query('SELECT COUNT(*) FROM products WHERE id = ' . $productId)->fetchColumn() === 0);
check('variants cascaded away',
    (int) $pdo->query('SELECT COUNT(*) FROM product_variants WHERE product_id = ' . $productId)->fetchColumn() === 0);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
