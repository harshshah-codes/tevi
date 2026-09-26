<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><!--TITLE--></h5>
    <a href="reviews" class="btn btn-outline-secondary">Back to List</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<!--ACTION-->" method="post">
            <input type="hidden" name="csrf_token" value="<!--CSRF_TOKEN-->">

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Product ID</label>
                <div class="col-sm-10">
                    <input type="number" name="product_id" class="form-control" value="<!--IF:REVIEW--><!--REVIEW_PRODUCT_ID--><!--ENDIF:REVIEW-->" placeholder="Product ID" required min="1">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Author</label>
                <div class="col-sm-10">
                    <input type="text" name="author" class="form-control" value="<!--IF:REVIEW--><!--REVIEW_AUTHOR--><!--ENDIF:REVIEW-->" placeholder="Author name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Rating</label>
                <div class="col-sm-10">
                    <select name="rating" class="form-select" style="max-width: 150px;">
                        <option value="5" <!--IF:REVIEW_RATING_5-->selected<!--ENDIF:REVIEW_RATING_5-->>5 Stars</option>
                        <option value="4" <!--IF:REVIEW_RATING_4-->selected<!--ENDIF:REVIEW_RATING_4-->>4 Stars</option>
                        <option value="3" <!--IF:REVIEW_RATING_3-->selected<!--ENDIF:REVIEW_RATING_3-->>3 Stars</option>
                        <option value="2" <!--IF:REVIEW_RATING_2-->selected<!--ENDIF:REVIEW_RATING_2-->>2 Stars</option>
                        <option value="1" <!--IF:REVIEW_RATING_1-->selected<!--ENDIF:REVIEW_RATING_1-->>1 Star</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Review Text</label>
                <div class="col-sm-10">
                    <textarea name="review_text" class="form-control" rows="4" placeholder="Review text..." required><!--IF:REVIEW--><!--REVIEW_TEXT--><!--ENDIF:REVIEW--></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Date</label>
                <div class="col-sm-10">
                    <input type="date" name="created_at" class="form-control" value="<!--IF:REVIEW--><!--REVIEW_DATE--><!--ENDIF:REVIEW-->" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Context/Category</label>
                <div class="col-sm-10">
                    <input type="text" name="context" class="form-control" value="<!--IF:REVIEW--><!--REVIEW_CONTEXT--><!--ENDIF:REVIEW-->" placeholder="e.g., Occasionwear, Wedding Collection">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    <a href="reviews" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>