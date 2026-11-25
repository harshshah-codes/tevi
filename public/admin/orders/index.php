<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Orders</h5>
</div>

<!--FLASH-->

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!--ROWS-->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle status change via AJAX
    document.querySelectorAll('form[data-status-form]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = e.target;
            const orderId = form.querySelector('input[name="id"]').value;
            const statusSelect = form.querySelector('select[name="status"]');
            const status = statusSelect.value;
            const card = form.closest('tr');
            const badge = card.querySelector('.badge');
            const btn = form.querySelector('button[type="submit"]');
            
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            fetch('/admin/orders/update-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    id: orderId,
                    status: status,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update badge
                    badge.className = 'badge bg-' + getStatusBadgeClass(status);
                    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    showToast('Order status updated successfully', 'success');
                } else {
                    showToast(data.error || 'Failed to update order', 'danger');
                }
            })
            .catch(error => {
                showToast('Error updating order', 'danger');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Set';
            });
        });
    });

    // Handle delete via AJAX
    document.querySelectorAll('form[data-delete-order]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this order? This cannot be undone.')) {
                return;
            }
            
            const form = e.target;
            const orderId = form.querySelector('input[name="id"]').value;
            const row = form.closest('tr');
            const btn = form.querySelector('button[type="submit"]');
            
            btn.disabled = true;
            btn.textContent = 'Deleting...';
            
            fetch('/admin/orders/delete?id=' + orderId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    showToast('Order deleted successfully', 'success');
                } else {
                    showToast(data.error || 'Failed to delete order', 'danger');
                }
            })
            .catch(error => {
                showToast('Error deleting order', 'danger');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Delete';
            });
        });
    });

    function getStatusBadgeClass(status) {
        const map = {
            'processing': 'warning',
            'shipped': 'info',
            'delivered': 'success',
            'cancelled': 'danger'
        };
        return map[status] || 'secondary';
    }

    function showToast(message, type = 'info') {
        // Create toast element
        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center text-white bg-' + (type === 'success' ? 'success' : type === 'danger' ? 'danger' : 'info');
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button></div>';
        
        document.body.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
});
</script>
