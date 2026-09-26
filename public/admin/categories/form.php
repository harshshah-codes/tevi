<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><!--TITLE--></h5>
    <a href="categories" class="btn btn-outline-secondary">Back to List</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<!--ACTION-->" method="post">
            <input type="hidden" name="csrf_token" value="<!--CSRF_TOKEN-->">

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Name</label>
                <div class="col-sm-10">
                    <input type="text" name="name" class="form-control" value="<!--IF:CATEGORY--><!--CATEGORY_NAME--><!--ENDIF:CATEGORY-->" placeholder="Category name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Slug</label>
                <div class="col-sm-10">
                    <input type="text" name="slug" class="form-control" value="<!--IF:CATEGORY--><!--CATEGORY_SLUG--><!--ENDIF:CATEGORY-->" placeholder="category-slug" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Image URL</label>
                <div class="col-sm-10">
                    <input type="url" name="image" class="form-control" value="<!--IF:CATEGORY--><!--CATEGORY_IMAGE--><!--ENDIF:CATEGORY-->" placeholder="https://example.com/image.jpg" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Tagline</label>
                <div class="col-sm-10">
                    <input type="text" name="tagline" class="form-control" value="<!--IF:CATEGORY--><!--CATEGORY_TAGLINE--><!--ENDIF:CATEGORY-->" placeholder="Category tagline">
                </div>
            </div>

            <!--IF:CATEGORY-->
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Preview</label>
                <div class="col-sm-10">
                    <img src="<!--CATEGORY_IMAGE-->" alt="Preview" style="max-width: 200px; border-radius: 0.5rem;">
                </div>
            </div>
            <!--ENDIF:CATEGORY-->

            <div class="row mb-3">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    <a href="categories" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>