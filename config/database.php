<?php
// Database configuration for Aerozone
// Check if we're in Azure production environment
if (isset($_ENV['WEBSITE_SITE_NAME']) || (isset($_ENV['DB_HOST']) && strpos($_ENV['DB_HOST'], 'postgres') !== false)) {
    // Use Azure production configuration
    require_once 'database_azure.php';
    return;
}

class Database {
    private $host = 'localhost';
    private $db_name = 'aerozone';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Global database connection function
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>
