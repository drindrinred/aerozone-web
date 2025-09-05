<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEROZONE - Airsoft Community Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-crosshairs me-2"></i>AEROZONE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-2 px-3" href="auth/register.php">Join Now</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center min-vh-75">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Welcome to AEROZONE</h1>
                    <p class="lead mb-4">The ultimate platform for the airsoft community. Connect with players, manage your gear, find trusted stores, and enhance your airsoft experience.</p>
                    <div class="d-flex gap-3">
                        <a href="auth/register.php" class="btn btn-light btn-lg">Get Started</a>
                        <a href="#features" class="btn btn-outline-light btn-lg">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-bullseye display-1 opacity-75"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-5 fw-bold">Platform Features</h2>
                    <p class="lead text-muted">Everything you need for your airsoft journey</p>
                </div>
            </div>
            <div class="row g-4">
                <!-- Player Features -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-user-friends fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">For Players</h5>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Inventory Management</li>
                                <li><i class="fas fa-check text-success me-2"></i>Maintenance Scheduling</li>
                                <li><i class="fas fa-check text-success me-2"></i>Marketplace Access</li>
                                <li><i class="fas fa-check text-success me-2"></i>Gear Requirements</li>
                                <li><i class="fas fa-check text-success me-2"></i>Service Reports</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Store Owner Features -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-store fa-3x text-success mb-3"></i>
                            <h5 class="card-title">For Store Owners</h5>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Business Registration</li>
                                <li><i class="fas fa-check text-success me-2"></i>Service Management</li>
                                <li><i class="fas fa-check text-success me-2"></i>Appointment Scheduling</li>
                                <li><i class="fas fa-check text-success me-2"></i>Inventory Tracking</li>
                                <li><i class="fas fa-check text-success me-2"></i>Customer Communication</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Admin Features -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-cog fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Platform Management</h5>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>User Management</li>
                                <li><i class="fas fa-check text-success me-2"></i>Content Management</li>
                                <li><i class="fas fa-check text-success me-2"></i>Store Verification</li>
                                <li><i class="fas fa-check text-success me-2"></i>Analytics & Reports</li>
                                <li><i class="fas fa-check text-success me-2"></i>System Notifications</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="display-5 fw-bold mb-4">About AEROZONE</h2>
                    <p class="mb-4">AEROZONE is designed to strengthen the airsoft community by providing a comprehensive platform that connects players, store owners, and administrators. Our mission is to enhance the airsoft experience through better organization, communication, and service management.</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt fa-2x text-primary me-3"></i>
                                <div>
                                    <h6 class="mb-0">Secure</h6>
                                    <small class="text-muted">Protected platform</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-users fa-2x text-success me-3"></i>
                                <div>
                                    <h6 class="mb-0">Community</h6>
                                    <small class="text-muted">Built for airsofters</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-crosshairs fa-10x text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-crosshairs me-2"></i>AEROZONE</h5>
                    <p class="mb-0">Connecting the airsoft community</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2025 AEROZONE. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
