<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\InventoryService;

final class AdminInventoryController extends AdminController
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {
    }

    /**
     * GET|POST /admin/inventory — stock levels, low-stock report and ledger.
     */
    public function index(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $productId = (int) ($_GET['product'] ?? 0);
        $view = $productId > 0 ? $this->productView($productId) : null;

        $rows = '';
        foreach (($view['variants'] ?? []) as $variant) {
            $badge = $variant['stock'] <= 0
                ? '<span class="badge bg-danger">Out</span>'
                : ($variant['isLow'] ? '<span class="badge bg-warning text-dark">Low</span>' : '<span class="badge bg-success">In stock</span>');

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($variant['label'], ENT_QUOTES) . '</td>'
                . '<td class="text-center">' . (int) $variant['stock'] . '</td>'
                . '<td class="text-center">' . (int) $variant['lowStockThreshold'] . '</td>'
                . '<td>' . $badge . '</td>'
                . '<td class="text-end">'
                . '<form action="/admin/inventory" method="post" class="d-inline-flex gap-1">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="variant_id" value="' . (int) $variant['id'] . '">'
                . '<input type="hidden" name="product_id" value="' . $productId . '">'
                . '<input type="number" name="quantity" value="' . (int) $variant['stock'] . '" min="0" class="form-control form-control-sm" style="width:90px" aria-label="New stock level">'
                . '<button name="action" value="set" class="btn btn-sm btn-primary">Set</button>'
                . '<button name="action" value="add" class="btn btn-sm btn-outline-primary">Add</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '' && $view !== null) {
            $rows = '<tr><td colspan="5" class="text-center py-4 text-muted">No variants yet</td></tr>';
        }

        $content = $this->render('inventory/index.php', [
            'TITLE'          => 'Inventory',
            'FLASH'          => $this->flashHtml(),
            'PRODUCTS'       => $this->productOptions(),
            'PRODUCT_SELECT' => $view['product']['id'] ?? 0,
            'PRODUCT_NAME'   => htmlspecialchars($view['product']['name'] ?? '', ENT_QUOTES),
            'ROWS'           => $rows,
            'LEDGER'         => $this->ledgerHtml($view['entries'] ?? []),
            'LOW_STOCK'      => $this->lowStockHtml(),
            'CSRF_TOKEN'     => $this->csToken(),
        ]);

        $layout = $this->render('layout.php', [
            'TITLE'             => 'Inventory',
            'CONTENT'           => $content,
            'ACTIVE_ORDERS'     => '',
            'ACTIVE_DASHBOARD'  => '',
            'ACTIVE_HERO'       => '',
            'ACTIVE_PRODUCTS'   => '',
            'ACTIVE_CATEGORIES' => '',
            'ACTIVE_REVIEWS'    => '',
            'ACTIVE_INVENTORY'  => 'active',
        ]);

        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }

    private function handlePost(): void
    {
        $this->validateCsrf();

        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'set');
        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            if ($action === 'add') {
                if ($quantity === 0) {
                    throw new \RuntimeException('Enter how many to add.');
                }
                $this->inventory->receive(
                    $variantId,
                    $quantity,
                    \App\Domain\Models\InventoryEntry::RECEIPT,
                    $note !== '' ? $note : 'Stock received',
                    null
                );
                $this->flash('Stock updated.');
            } else {
                $result = $this->inventory->setStock(
                    $variantId,
                    $quantity,
                    $note !== '' ? $note : null,
                    null
                );
                $this->flash($result['delta'] === 0
                    ? 'Stock was already ' . $result['now'] . '.'
                    : 'Stock set to ' . $result['now'] . ' (' . ($result['delta'] > 0 ? '+' : '') . $result['delta'] . ').');
            }
        } catch (\Throwable $e) {
            $this->flash($e->getMessage(), 'danger');
        }

        $this->redirect('/admin/inventory' . ($productId > 0 ? '?product=' . $productId : ''));
    }

    /**
     * @return array{product: array<string,mixed>, variants: array<int,array<string,mixed>>, entries: array<int,\App\Domain\Models\InventoryEntry>}
     */
    private function productView(int $productId): array
    {
        $config = require __DIR__ . '/../../../Config/config.php';
        $pdo = \App\Core\Database::connect($config['db']);
        $statement = $pdo->prepare('SELECT id, name FROM products WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $productId]);
        $row = $statement->fetch();

        return [
            'product'  => is_array($row) ? $row : ['id' => 0, 'name' => ''],
            'variants' => array_map(static fn($v) => $v->toArray(), $this->inventory->variantsFor($productId)),
            'entries'  => $this->inventory->historyForProduct($productId, 40),
        ];
    }

    private function productOptions(): string
    {
        $config = require __DIR__ . '/../../../Config/config.php';
        $pdo = \App\Core\Database::connect($config['db']);

        $options = '';
        foreach ($pdo->query('SELECT id, name FROM products ORDER BY name') as $row) {
            $options .= '<option value="' . (int) $row['id'] . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES) . '</option>';
        }

        return $options;
    }

    /**
     * @param array<int,\App\Domain\Models\InventoryEntry> $entries
     */
    private function ledgerHtml(array $entries): string
    {
        if ($entries === []) {
            return '<tr><td colspan="6" class="text-center py-4 text-muted">No movements recorded yet</td></tr>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $delta = $entry->quantityDelta();
            $class = $delta > 0 ? 'text-success' : 'text-danger';
            $when = date('d M Y, H:i', strtotime($entry->createdAt())) ?: $entry->createdAt();

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($when, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($entry->variantLabel() ?? '', ENT_QUOTES) . '</td>'
                . '<td><span class="badge bg-secondary">' . htmlspecialchars($entry->entryType(), ENT_QUOTES) . '</span></td>'
                . '<td class="text-end ' . $class . '">' . ($delta > 0 ? '+' : '') . $delta . '</td>'
                . '<td class="text-center">' . $entry->balanceAfter() . '</td>'
                . '<td class="small text-muted">'
                . htmlspecialchars($entry->note() ?? '', ENT_QUOTES)
                . ($entry->orderId() ? ' <span class="text-muted">(order #' . (int) $entry->orderId() . ')</span>' : '')
                . '</td>'
                . '</tr>';
        }

        return $rows;
    }

    private function lowStockHtml(): string
    {
        $rows = '';
        foreach ($this->inventory->lowStockReport() as $item) {
            $rows .= '<tr>'
                . '<td><a href="/admin/inventory?product=' . (int) $item['productId'] . '">' . htmlspecialchars($item['name'], ENT_QUOTES) . '</a></td>'
                . '<td class="text-center">' . (int) $item['outOfStock'] . '</td>'
                . '<td class="text-center">' . (int) $item['low'] . '</td>'
                . '<td class="text-center">' . (int) $item['total'] . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '<tr><td colspan="4" class="text-center py-4 text-muted">Everything is well stocked</td></tr>';
        }

        return $rows;
    }

    private function flashHtml(): string
    {
        $flash = $this->getFlash();

        return $flash
            ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>'
            : '';
    }
}
