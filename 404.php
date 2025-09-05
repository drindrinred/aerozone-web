<?php
// Send 404 HTTP status code
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/aerozone/assets/css/style.css" rel="stylesheet">
    <style>
        .not-found-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .not-found-card { max-width: 640px; }
    </style>
    </head>
<body class="bg-light">
    <div class="container not-found-wrapper">
        <div class="card shadow not-found-card w-100">
            <div class="card-body p-5 text-center">
                <div class="mb-3">
                    <i class="fas fa-compass fa-3x text-danger"></i>
                </div>
                <h1 class="display-5 fw-bold">404</h1>
                <p class="lead mb-3">Error 404: Not Found</p>
                <p class="text-muted mb-4">The page you're looking for doesn't exist or may have been moved.</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="/aerozone/index.php" class="btn btn-primary"><i class="fas fa-home me-2"></i>Go to Home</a>
                    <a href="/aerozone/auth/login.php" class="btn btn-outline-secondary"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


