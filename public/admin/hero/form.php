<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><!--TITLE--></h5>
    <a href="hero" class="btn btn-outline-secondary">Back to List</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<!--ACTION-->" method="post">
            <input type="hidden" name="csrf_token" value="<!--CSRF_TOKEN-->">
            
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Tagline</label>
                <div class="col-sm-10">
                    <input type="text" name="tagline" class="form-control" value="<!--IF:SLIDE--><!--SLIDE_TAGLINE--><!--ENDIF:SLIDE-->" placeholder="e.g., THE FESTIVE EDIT · 2026" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Headline</label>
                <div class="col-sm-10">
                    <textarea name="headline" class="form-control" rows="2" placeholder="e.g., Tradition,<br><em>Tailored</em> Beautifully." required><!--IF:SLIDE--><!--SLIDE_HEADLINE--><!--ENDIF:SLIDE--></textarea>
                    <small class="text-muted">HTML allowed (e.g., <em>, <br>)</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Paragraph</label>
                <div class="col-sm-10">
                    <textarea name="paragraph" class="form-control" rows="3" placeholder="Description text..." required><!--IF:SLIDE--><!--SLIDE_PARAGRAPH--><!--ENDIF:SLIDE--></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Button Text</label>
                <div class="col-sm-10">
                    <input type="text" name="button" class="form-control" value="<!--IF:SLIDE--><!--SLIDE_BUTTON--><!--ENDIF:SLIDE-->" placeholder="e.g., Explore Collection" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">CTA Link</label>
                <div class="col-sm-10">
                    <input type="url" name="cta_link" class="form-control" value="<!--IF:SLIDE--><!--SLIDE_CTA_LINK--><!--ENDIF:SLIDE-->" placeholder="e.g., #featured" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Image URL</label>
                <div class="col-sm-10">
                    <input type="url" name="image_url" class="form-control" value="<!--IF:SLIDE--><!--SLIDE_IMAGE_URL--><!--ENDIF:SLIDE-->" placeholder="https://example.com/image.jpg" required>
                    <small class="text-muted">Recommended: 1920x1080 or similar aspect ratio</small>
                </div>
            </div>

            <!--IF:SLIDE-->
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">Preview</label>
                <div class="col-sm-10">
                    <img src="<!--SLIDE_IMAGE_URL-->" alt="Preview" style="max-width: 300px; border-radius: 0.5rem;">
                </div>
            </div>
            <!--ENDIF:SLIDE-->

            <div class="row mb-3">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                    <a href="hero" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>