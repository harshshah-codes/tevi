<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Order <!--ORDER_ID--></h5>
    <a href="/admin/orders" class="btn btn-outline-secondary">Back to List</a>
</div>

<!--FLASH-->

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items</strong>
                <span class="badge bg-<!--STATUS_COLOR-->"><!--STATUS--></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product ID</th>
                                <th></th>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Color</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!--ITEM_ROWS-->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-end">
                    <table style="min-width:260px">
                        <tr><td class="text-muted pe-4">Subtotal</td><td class="text-end">₹<!--SUBTOTAL--></td></tr>
                        <tr><td class="text-muted pe-4">Shipping</td><td class="text-end"><!--SHIPPING--></td></tr>
                        <tr><td class="text-muted pe-4">Tax</td><td class="text-end">₹<!--TAX--></td></tr>
                        <tr class="border-top fw-bold"><td class="pe-4 pt-2">Total</td><td class="text-end pt-2">₹<!--TOTAL--></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><strong>Customer</strong></div>
            <div class="card-body">
                <p class="mb-1 fw-bold"><!--CUSTOMER_NAME--></p>
                <p class="mb-1 text-muted small"><i class="bi bi-envelope me-1"></i><!--CUSTOMER_EMAIL--></p>
                <p class="mb-1 text-muted small"><i class="bi bi-phone me-1"></i><!--CUSTOMER_PHONE--></p>
                <hr class="my-2">
                <p class="mb-1 small"><i class="bi bi-geo-alt me-1"></i><!--ADDRESS--></p>
                <p class="mb-0 small text-muted"><!--CITY_STATE--></p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Payment</strong></div>
            <div class="card-body">
                <p class="mb-1 small text-muted">Method: <span class="text-capitalize"><!--PAYMENT--></span></p>
                <p class="mb-0 small text-muted">Placed: <!--DATE--></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Update Status</strong></div>
            <div class="card-body">
                <!--IF:CANCEL_BLOCK--><!--CANCEL_BLOCK--><!--ENDIF:CANCEL_BLOCK-->
                <form action="/admin/orders/detail?id=<!--ORDER_DB_ID-->" method="post" id="statusForm">
                    <input type="hidden" name="csrf_token" value="<!--CSRF_TOKEN-->">
                    <div class="input-group mb-2">
                        <select name="status" class="form-select"><!--STATUS_OPTIONS--></select>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                    <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="Internal note (optional, e.g. courier tracking no.)">
                    <p class="text-muted small mt-2 mb-0">Only valid next states are listed. Changing away from <em>cancelled</em> reinstates the order and clears its cancellation details.</p>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Status History</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>When</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Note</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!--HISTORY_ROWS-->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
