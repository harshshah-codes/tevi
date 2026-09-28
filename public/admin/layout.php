<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><!--TITLE--> | House of Viraasat Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #1c1c1c;
            color: #fff;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1.5rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: #2d2d2d;
        }
        .sidebar .nav-link i {
            width: 1.25rem;
            text-align: center;
        }
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid #333;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="sidebar-brand">
                    <h4 class="mb-0"><i class="bi bi-gem me-2"></i>Viraasat Admin</h4>
                    <small class="text-muted">House of Viraasat</small>
                </div>
                <nav class="nav flex-column px-3 py-3">
                    <a class="nav-link <!--IF:ACTIVE_DASHBOARD-->active<!--ENDIF:ACTIVE_DASHBOARD-->" href="/admin/">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                    <a class="nav-link <!--IF:ACTIVE_HERO-->active<!--ENDIF:ACTIVE_HERO-->" href="/admin/hero">
                        <i class="bi bi-image me-2"></i>Hero Slides
                    </a>
                    <a class="nav-link <!--IF:ACTIVE_PRODUCTS-->active<!--ENDIF:ACTIVE_PRODUCTS-->" href="/admin/products">
                        <i class="bi bi-box-seam me-2"></i>Products
                    </a>
                    <a class="nav-link <!--IF:ACTIVE_CATEGORIES-->active<!--ENDIF:ACTIVE_CATEGORIES-->" href="/admin/categories">
                        <i class="bi bi-tags me-2"></i>Categories
                    </a>
                    <a class="nav-link <!--IF:ACTIVE_ORDERS-->active<!--ENDIF:ACTIVE_ORDERS-->" href="/admin/orders">
                        <i class="bi bi-receipt me-2"></i>Orders
                    </a>
                    <a class="nav-link <!--IF:ACTIVE_REVIEWS-->active<!--ENDIF:ACTIVE_REVIEWS-->" href="/admin/reviews">
                        <i class="bi bi-star me-2"></i>Reviews
                    </a>
                    <hr class="text-secondary mx-3">
                    <a class="nav-link" href="/" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-2"></i>View Site
                    </a>
                </nav>
            </aside>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-2 mb-3 border-bottom">
                    <h1 class="h3 mb-0"><!--TITLE--></h1>
                </div>

                <!--FLASH-->

                <!--CONTENT-->
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.nav-link').forEach(link => {
            const active = document.querySelector('.nav-link.active');
            if (active) active.classList.remove('active');
            if (link.getAttribute('href') === window.location.pathname.split('/').pop() ||
                (link.getAttribute('href') === 'dashboard' && window.location.pathname.endsWith('/admin/'))) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>