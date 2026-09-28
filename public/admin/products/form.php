<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><!--TITLE--></h5>
    <a href="products" class="btn btn-outline-secondary">Back to List</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<!--ACTION-->" method="post">
            <input type="hidden" name="csrf_token" value="<!--CSRF_TOKEN-->">

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Name</label>
                <div class="col-sm-10">
                    <input type="text" name="name" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_NAME--><!--ENDIF:PRODUCT-->" placeholder="Product name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Slug</label>
                <div class="col-sm-10">
                    <input type="text" name="slug" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_SLUG--><!--ENDIF:PRODUCT-->" placeholder="product-slug" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Description</label>
                <div class="col-sm-10">
                    <textarea name="description" class="form-control" rows="4" placeholder="Product description..." required><!--IF:PRODUCT--><!--PRODUCT_DESCRIPTION--><!--ENDIF:PRODUCT--></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Price (₹)</label>
                <div class="col-sm-10">
                    <input type="number" name="price" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_PRICE--><!--ENDIF:PRODUCT-->" placeholder="2899" min="0" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Image URL</label>
                <div class="col-sm-10">
                    <input type="url" name="image" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_IMAGE--><!--ENDIF:PRODUCT-->" placeholder="https://example.com/image.jpg" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Badge</label>
                <div class="col-sm-10">
                    <select name="badge" class="form-select">
                        <option value="">None</option>
                        <!--BADGES-->
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Sizes (comma-separated)</label>
                <div class="col-sm-10">
                    <input type="text" name="sizes" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_SIZES--><!--ENDIF:PRODUCT-->" placeholder="S, M, L, XL, XXL">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Colors (comma-separated)</label>
                <div class="col-sm-10">
                    <input type="text" name="colors" class="form-control" value="<!--IF:PRODUCT--><!--PRODUCT_COLORS--><!--ENDIF:PRODUCT-->" placeholder="Ivory, Gold">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Featured</label>
                <div class="col-sm-10">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" <!--IF:PRODUCT_FEATURED-->checked<!--ENDIF:PRODUCT_FEATURED-->>
                        <label class="form-check-label" for="is_featured">Show in featured section</label>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Categories</label>
                <div class="col-sm-10">
                    <!--CATEGORIES-->
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    <a href="products" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>