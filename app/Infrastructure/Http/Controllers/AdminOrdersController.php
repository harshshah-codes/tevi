<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;

final class AdminOrdersController extends AdminController
{
    private const STATUSES = ['processing', 'shipped', 'delivered', 'cancelled'];

    public function __construct(
        private readonly OrderService $service,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $orders = $this->service->getAllOrders(200);
        $flash = $this->getFlash();

        $rows = '';
        foreach ($orders as $order) {
            $itemsCount = count($order->items());
            $status = $order->status();
            $date = date('d M Y, H:i', strtotime($order->createdAt())) ?: $order->createdAt();

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($order->orderId(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($order->firstName() . ' ' . $order->lastName(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($order->phone(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($order->city() . ', ' . $order->pincode(), ENT_QUOTES) . '</td>'
                . '<td>' . $itemsCount . '</td>'
                . '<td>₹' . number_format($order->total()) . '</td>'
                . '<td><span class="badge bg-' . $this->statusColor($status) . '">' . htmlspecialchars($status, ENT_QUOTES) . '</span></td>'
                . '<td>' . htmlspecialchars($date, ENT_QUOTES) . '</td>'
                . '<td class="text-end">'
                . '<a href="/admin/orders/detail?id=' . $order->id() . '" class="btn btn-sm btn-outline-primary">View</a>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="text-center py-4 text-muted">No orders yet</td></tr>';
        }

        $content = $this->render('orders/index.php', [
            'FLASH' => $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
            'ROWS'  => $rows,
        ]);

        $this->output($content);
    }

    public function detail(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $order = $this->service->getOrder($id);

        if (!$order) {
            $this->flash('Order not found', 'danger');
            $this->redirect('/admin/orders');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $status = trim($_POST['status'] ?? '');
            if (!in_array($status, self::STATUSES, true)) {
                $this->flash('Invalid status', 'danger');
                $this->redirect('/admin/orders/detail?id=' . $id);
            }
            $this->service->updateStatus($id, $status);
            $this->flash('Order status updated to ' . $status);
            $this->redirect('/admin/orders/detail?id=' . $id);
        }

        $flash = $this->getFlash();

        $itemRows = '';
        foreach ($order->items() as $item) {
            $itemRows .= '<tr>'
                . '<td>' . ($item['product_id'] !== null ? '#' . $item['product_id'] : '—') . '</td>'
                . '<td><img src="' . htmlspecialchars($item['image'], ENT_QUOTES) . '" alt="" style="width:44px;height:56px;object-fit:cover;" onerror="this.style.display=\'none\'"></td>'
                . '<td>' . htmlspecialchars($item['product_name'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($item['size'] ?: '—', ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($item['color'] ?: '—', ENT_QUOTES) . '</td>'
                . '<td>₹' . number_format($item['price']) . '</td>'
                . '<td>' . $item['qty'] . '</td>'
                . '<td>₹' . number_format($item['price'] * $item['qty']) . '</td>'
                . '</tr>';
        }

        $statusOptions = '';
        foreach (self::STATUSES as $s) {
            $selected = $order->status() === $s ? 'selected' : '';
            $statusOptions .= '<option value="' . $s . '" ' . $selected . '>' . ucfirst($s) . '</option>';
        }

        $content = $this->render('orders/detail.php', [
            'FLASH'            => $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
            'ORDER_DB_ID'      => $order->id(),
            'ORDER_ID'         => htmlspecialchars($order->orderId(), ENT_QUOTES),
            'CUSTOMER_NAME'    => htmlspecialchars($order->firstName() . ' ' . $order->lastName(), ENT_QUOTES),
            'CUSTOMER_EMAIL'   => htmlspecialchars($order->email(), ENT_QUOTES),
            'CUSTOMER_PHONE'   => htmlspecialchars($order->phone(), ENT_QUOTES),
            'ADDRESS'          => htmlspecialchars($order->address(), ENT_QUOTES),
            'CITY_STATE'       => htmlspecialchars($order->city() . ', ' . $order->state() . ' — ' . $order->pincode(), ENT_QUOTES),
            'PAYMENT'          => htmlspecialchars($order->paymentMethod(), ENT_QUOTES),
            'STATUS'           => htmlspecialchars($order->status(), ENT_QUOTES),
            'STATUS_COLOR'     => $this->statusColor($order->status()),
            'DATE'             => htmlspecialchars(date('d M Y, H:i', strtotime($order->createdAt())) ?: $order->createdAt(), ENT_QUOTES),
            'ITEM_ROWS'        => $itemRows,
            'SUBTOTAL'         => number_format($order->subtotal()),
            'SHIPPING'         => $order->shipping() === 0 ? 'FREE' : number_format($order->shipping()),
            'TAX'              => number_format($order->tax()),
            'TOTAL'            => number_format($order->total()),
            'STATUS_OPTIONS'   => $statusOptions,
            'CSRF_TOKEN'       => $this->csToken(),
        ]);

        $this->output($content);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'processing' => 'warning',
            'shipped'    => 'info',
            'delivered'  => 'success',
            'cancelled'  => 'danger',
            default      => 'secondary',
        };
    }

    private function output(string $content): void
    {
        $layout = $this->render('layout.php', [
            'TITLE'             => 'Orders',
            'CONTENT'           => $content,
            'ACTIVE_ORDERS'     => 'active',
            'ACTIVE_DASHBOARD'  => '',
            'ACTIVE_HERO'       => '',
            'ACTIVE_PRODUCTS'   => '',
            'ACTIVE_CATEGORIES' => '',
            'ACTIVE_REVIEWS'    => '',
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}
