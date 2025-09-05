<?php
$user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base_url = '/aerozone';
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'index.php' && $current_dir === 'dashboard') ? 'active' : ''; ?>" 
                   href="<?php echo $base_url; ?>/dashboard/index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <?php if ($user['role'] === 'admin'): ?>
                <!-- Admin Menu -->
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Administration</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'users' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/users/index.php">
                        <i class="fas fa-users me-2"></i>
                        User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'stores' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/stores/index.php">
                        <i class="fas fa-store me-2"></i>
                        Store Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'content' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/content/index.php">
                        <i class="fas fa-edit me-2"></i>
                        Content Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'reports' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/reports/index.php">
                        <i class="fas fa-chart-bar me-2"></i>
                        Reports & Analytics
                    </a>
                </li>
            <?php elseif ($user['role'] === 'player'): ?>
                <!-- Player Menu -->
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>My Gear</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'inventory' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/inventory/index.php">
                        <i class="fas fa-box me-2"></i>
                        My Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'requirements' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/requirements/index.php">
                        <i class="fas fa-list-check me-2"></i>
                        Gear Requirements
                    </a>
                </li>

                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Services</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'appointments' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/appointments/index.php">
                        <i class="fas fa-calendar me-2"></i>
                        Service Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'stores' && $current_page === 'browse.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/stores/browse.php">
                        <i class="fas fa-store me-2"></i>
                        Browse Stores
                    </a>
                </li>

                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Community</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'marketplace' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/marketplace/index.php">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Marketplace
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'membership' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/membership/policies.php">
                        <i class="fas fa-id-card me-2"></i>
                        Membership Info
                    </a>
                </li>

            <?php elseif ($user['role'] === 'store_owner'): ?>
                <!-- Store Owner Menu -->
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Store Management</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'store' && $current_page === 'profile.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/store/profile.php">
                        <i class="fas fa-store me-2"></i>
                        Store Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_dir === 'employees' ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/employees/index.php">
                        <i class="fas fa-users me-2"></i>
                        Manage Staff
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'inventory' && $current_page === 'store.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/inventory/store.php">
                        <i class="fas fa-boxes me-2"></i>
                        Store Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'inventory' && $current_page === 'sales.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/inventory/sales.php">
                        <i class="fas fa-cash-register me-2"></i>
                        Sales
                    </a>
                </li>
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Reports & Analytics</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'reports' && $current_page === 'index.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/reports/index.php">
                        <i class="fas fa-chart-dashboard me-2"></i>
                        Reports Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'inventory' && $current_page === 'reports.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/inventory/reports.php">
                        <i class="fas fa-boxes me-2"></i>
                        Inventory Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'inventory' && $current_page === 'sales-reports.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/inventory/sales-reports.php">
                        <i class="fas fa-chart-line me-2"></i>
                        Sales Reports
                    </a>
                </li>
                <li class="nav-item">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                        <span>Services</span>
                    </h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_dir === 'appointments' && $current_page === 'manage.php') ? 'active' : ''; ?>" 
                       href="<?php echo $base_url; ?>/dashboard/appointments/manage.php">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Manage Service Requests
                    </a>
                </li>
            <?php endif; ?>

            <!-- Common Menu Items -->
            <li class="nav-item">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white fw-bold">
                    <span>Account</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'profile' ? 'active' : ''; ?>" 
                   href="<?php echo $base_url; ?>/dashboard/profile/index.php">
                    <i class="fas fa-user me-2"></i>
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'settings' ? 'active' : ''; ?>" 
                   href="<?php echo $base_url; ?>/dashboard/settings/index.php">
                    <i class="fas fa-cog me-2"></i>
                    Settings
                </a>
            </li>
        </ul>

        <!-- Logout Button -->
        <div class="px-3 mt-4">
            <a href="<?php echo $base_url; ?>/auth/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>
</nav>
