<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | House of Viraasat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 420px; margin: 0 auto; border: none; border-radius: 1rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .login-header { background: linear-gradient(135deg, #1c1c1c 0%, #2d2d2d 100%); color: #fff; border-radius: 1rem 1rem 0 0; padding: 2rem; text-align: center; }
        .login-body { padding: 2.5rem; }
        .form-control { padding: 0.75rem 1rem; border-radius: 0.5rem; }
        .btn-login { padding: 0.75rem; font-weight: 600; border-radius: 0.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card">
            <div class="login-header">
                <i class="bi bi-gem fs-1 mb-2"></i>
                <h3 class="mb-0">House of Viraasat</h3>
                <small>Admin Panel</small>
            </div>
            <div class="login-body">
                <!--ERROR-->
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-dark btn-login w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</button>
                </form>
                <div class="text-center mt-4 text-muted small">
                    Default: <code>admin</code> / <code>password</code>
                </div>
            </div>
        </div>
    </div>
</body>
</html>