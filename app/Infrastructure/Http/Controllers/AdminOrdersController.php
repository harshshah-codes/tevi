<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\OrderService;
use App\Domain\Models\Order;

final class AdminOrdersController extends AdminController
{
    private const STATUSES = ['processing', 'shipped', 'delivered', 'cancelled'];

    public function __construct(
        private readonly OrderService $service,
    ) {
    }

    /**
     * Quick status change from the orders table (POST /admin/orders).
     */
    public function updateStatus(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $back = '/admin/orders';

        $order = $this->service->getOrder($id);
        if ($order === null) {
            $this->flash('Order not found', 'danger');
            $this->redirect($back);
        }

        $target = isset($_POST['return_to']) && $_POST['return_to'] === 'detail'
            ? '/admin/orders/detail?id=' . $id
            : $back;

        if (!in_array($status, self::STATUSES, true)) {
            $this->flash('Unknown status: ' . htmlspecialchars($status, ENT_QUOTES), 'danger');
            $this->redirect($target);
        }

        if ($status === $order->status()) {
            $this->flash('Order is already marked as ' . $status . '.', 'warning');
            $this->redirect($target);
        }

        $allowed = $this->service->adminTransitionsFrom($order->status());
        if (!in_array($status, $allowed, true)) {
            $this->flash(
                'Cannot move an order from "' . $order->status() . '" to "' . $status . '". '
                . ($allowed === []
                    ? 'Orders that are ' . $order->status() . ' are final.'
                    : 'Allowed next: ' . implode(', ', $allowed) . '.'),
                'danger'
            );
            $this->redirect($target);
        }

        $note = mb_substr($note, 0, 255);

        $updated = $this->service->adminUpdateStatus(
            $id,
            $allowed,
            $status,
            $note === '' ? null : $note,
            $this->adminActor(),
            $order->status()
        );

        if (!$updated) {
            $this->flash('Could not update this order — someone else may have changed it first.', 'danger');
            $this->redirect($target);
        }

        $this->flash('Order ' . $order->orderId() . ' moved from ' . $order->status() . ' to ' . $status . '.');
        $this->redirect($target);
    }

    /**
     * Who is making the change, recorded in order_status_history.
     */
    private function adminActor(): string
    {
        $username = (string) ($_SESSION['admin_username'] ?? '');

        return $username !== '' ? $username : 'admin';
    }

    public function index(): void
    {
        $this->requireAuth();

        $orders = $this->service->getAllOrders(200);
        $flash = $this->getFlash();
        $csrf = $this->csToken();

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
                . '<div class="d-inline-flex gap-2 align-items-center">'
                . $this->quickStatusForm($order, $csrf)
                . '<a href="/admin/orders/detail?id=' . $order->id() . '" class="btn btn-sm btn-outline-primary">View</a>'
                . '</div>'
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
            $note = trim($_POST['note'] ?? '');

            if (!in_array($status, self::STATUSES, true)) {
                $this->flash('Invalid status', 'danger');
                $this->redirect('/admin/orders/detail?id=' . $id);
            }

            if ($status === $order->status()) {
                $this->flash('Order is already marked as ' . $status . '.', 'warning');
                $this->redirect('/admin/orders/detail?id=' . $id);
            }

            $allowed = $this->service->adminTransitionsFrom($order->status());
            if (!in_array($status, $allowed, true)) {
                $this->flash(
                    'Cannot move an order from "' . $order->status() . '" to "' . $status . '". '
                    . ($allowed === []
                        ? 'Orders that are ' . $order->status() . ' are final.'
                        : 'Allowed next: ' . implode(', ', $allowed) . '.'),
                    'danger'
                );
                $this->redirect('/admin/orders/detail?id=' . $id);
            }

            $note = mb_substr($note, 0, 255);

            $updated = $this->service->adminUpdateStatus(
                $id,
                $allowed,
                $status,
                $note === '' ? null : $note,
                $this->adminActor(),
                $order->status()
            );

            if (!$updated) {
                $this->flash('Could not update this order — someone else may have changed it first.', 'danger');
                $this->redirect('/admin/orders/detail?id=' . $id);
            }

            $this->flash('Order ' . $order->orderId() . ' moved from ' . $order->status() . ' to ' . $status . '.');
            $this->redirect('/admin/orders/detail?id=' . $id);
        }

        $flash = $this->getFlash();
        $order = $this->service->getOrder($id) ?? $order;

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

        $allowedFrom = $this->service->adminTransitionsFrom($order->status());
        $statusOptions = '';
        foreach ($allowedFrom as $s) {
            $statusOptions .= '<option value="' . $s . '">' . ucfirst($s) . '</option>';
        }
        if ($statusOptions === '') {
            $statusOptions = '<option value="' . htmlspecialchars($order->status(), ENT_QUOTES) . '">'
                . ucfirst($order->status()) . ' (final)</option>';
        }

        $historyRows = '';
        foreach ($this->service->statusHistory($id) as $entry) {
            $when = date('d M Y, H:i', strtotime($entry['created_at'])) ?: $entry['created_at'];
            $historyRows .= '<tr>'
                . '<td>' . htmlspecialchars($when, ENT_QUOTES) . '</td>'
                . '<td>' . ($entry['from_status'] === ''
                    ? '<span class="text-muted">—</span>'
                    : '<span class="badge bg-' . $this->statusColor($entry['from_status']) . '">'
                        . htmlspecialchars($entry['from_status'], ENT_QUOTES) . '</span>') . '</td>'
                . '<td><span class="badge bg-' . $this->statusColor($entry['to_status']) . '">'
                    . htmlspecialchars($entry['to_status'], ENT_QUOTES) . '</span></td>'
                . '<td>' . htmlspecialchars($entry['note'] ?? '—', ENT_QUOTES) . '</td>'
                . '<td class="text-muted small">' . htmlspecialchars($entry['changed_by'], ENT_QUOTES) . '</td>'
                . '</tr>';
        }
        if ($historyRows === '') {
            $historyRows = '<tr><td colspan="5" class="text-center py-3 text-muted">No status changes recorded yet</td></tr>';
        }

        $cancelBlock = '';
        if ($order->status() === 'cancelled') {
            $when = $order->cancelledAt()
                ? date('d M Y, H:i', strtotime($order->cancelledAt()))
                : 'unknown date';
            $cancelBlock = '<div class="alert alert-danger py-2 small mb-0">'
                . '<strong>Cancelled</strong> on ' . htmlspecialchars($when, ENT_QUOTES)
                . ($order->cancelReason()
                    ? '<br>Customer reason: ' . htmlspecialchars($order->cancelReason(), ENT_QUOTES)
                    : '<br>No reason given.')
                . '</div>';
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
            'CANCEL_BLOCK'     => $cancelBlock,
            'HISTORY_ROWS'     => $historyRows,
            'CSRF_TOKEN'       => $this->csToken(),
        ]);

        $this->output($content);
    }

    /**
     * Inline status dropdown for the orders table. Only legal next states are
     * offered, so the admin never sees a transition the server will reject.
     */
    private function quickStatusForm(Order $order, string $csrf): string
    {
        $allowed = $this->service->adminTransitionsFrom($order->status());

        if ($allowed === []) {
            return '<span class="text-muted small">final</span>';
        }

        $options = '';
        foreach ($allowed as $status) {
            $options .= '<option value="' . $status . '">' . ucfirst($status) . '</option>';
        }

        return '<form action="/admin/orders/update-status" method="post" class="d-inline-flex gap-1 align-items-center">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="id" value="' . $order->id() . '">'
            . '<select name="status" class="form-select form-select-sm" style="width:auto">' . $options . '</select>'
            . '<button type="submit" class="btn btn-sm btn-primary">Set</button>'
            . '</form>';
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
