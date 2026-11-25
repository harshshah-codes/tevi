<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Inventory</h5>
    <form action="/admin/inventory" method="get" class="d-flex gap-2">
        <select name="product" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Select a product…</option>
            <!--PRODUCTS-->
        </select>
    </form>
</div>

<!--FLASH-->

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Stock levels<!--IF:PRODUCT_NAME--> &mdash; <!--PRODUCT_NAME--><!--ENDIF:PRODUCT_NAME--></strong>
                <span class="text-muted small">One row per colour and size</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Variant</th>
                                <th class="text-center">In stock</th>
                                <th class="text-center">Low at</th>
                                <th>Status</th>
                                <th class="text-end">Adjust</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!--ROWS-->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                <strong>Set</strong> replaces the figure and records a correction in the ledger.
                <strong>Add</strong> increments it and records a receipt. Nothing is ever edited silently.
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Needs attention</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Out</th>
                                <th class="text-center">Low</th>
                                <th class="text-center">Variants</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!--LOW_STOCK-->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><strong>Ledger</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Variant</th>
                        <th>Type</th>
                        <th class="text-end">Change</th>
                        <th class="text-center">Balance</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <!--LEDGER-->
                </tbody>
            </table>
        </div>
    </div>
</div>
