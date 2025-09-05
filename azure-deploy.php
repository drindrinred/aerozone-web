<?php
/**
 * Azure deployment script for AeroZone
 * This script handles database migration and initial setup on Azure App Service
 */

require_once 'config/database_azure.php';

// Check if we're in Azure environment
if (!isset($_ENV['WEBSITE_SITE_NAME']) && !isset($_ENV['DB_HOST'])) {
    die('This script should only be run in Azure App Service environment');
}

try {
    $db = getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    echo "Connected to Azure Database successfully\n";
    
    // Read and execute PostgreSQL schema
    $schema = file_get_contents('database/postgresql_schema.sql');
    
    if (!$schema) {
        throw new Exception('Could not read PostgreSQL schema file');
    }
    
    // Split schema into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "Executing " . count($statements) . " SQL statements...\n";
    
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            try {
                $db->exec($statement);
                echo "✓ Executed statement\n";
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // Create default admin user if it doesn't exist
    $adminCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCheck->execute();
    $adminCount = $adminCheck->fetchColumn();
    
    if ($adminCount == 0) {
        echo "Creating default admin user...\n";
        
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminQuery = $db->prepare("
            INSERT INTO users (username, email, password_hash, role, full_name, is_active) 
            VALUES (?, ?, ?, 'admin', 'System Administrator', true)
        ");
        
        $adminQuery->execute(['admin', 'admin@aerozone.com', $adminPassword]);
        
        // Get the admin user ID
        $adminId = $db->lastInsertId();
        
        // Insert into admins table
        $adminInsert = $db->prepare("INSERT INTO admins (user_id, admin_level) VALUES (?, 'super_admin')");
        $adminInsert->execute([$adminId]);
        
        echo "✓ Default admin user created (username: admin, password: admin123)\n";
        echo "⚠ Please change the default password after first login!\n";
    }
    
    // Create uploads directories if they don't exist
    $uploadDirs = ['uploads', 'uploads/documents', 'uploads/listings'];
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✓ Created directory: $dir\n";
        }
    }
    
    echo "\n🎉 Azure deployment completed successfully!\n";
    echo "Your AeroZone web portal is ready to use on Azure App Service.\n";
    echo "App URL: https://" . ($_ENV['WEBSITE_SITE_NAME'] ?? 'your-app') . ".azurewebsites.net\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
