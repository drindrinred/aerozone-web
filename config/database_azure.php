<?php
// Azure Database configuration for Aerozone
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        // Azure App Service environment variables
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'aerozone';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->port = $_ENV['DB_PORT'] ?? '5432';
        
        // Azure Database for PostgreSQL connection string format
        if (isset($_ENV['DATABASE_URL'])) {
            $this->parseConnectionString($_ENV['DATABASE_URL']);
        }
    }
    
    private function parseConnectionString($connectionString) {
        // Parse Azure connection string format
        // postgresql://username:password@host:port/database
        $parsed = parse_url($connectionString);
        
        if ($parsed) {
            $this->host = $parsed['host'];
            $this->port = $parsed['port'] ?? '5432';
            $this->db_name = ltrim($parsed['path'], '/');
            $this->username = $parsed['user'];
            $this->password = $parsed['pass'];
        }
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            // PostgreSQL connection for Azure
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ]);
            
        } catch(PDOException $exception) {
            error_log("Azure Database connection error: " . $exception->getMessage());
            // Don't expose database errors in production
            if ($_ENV['APP_ENV'] === 'development') {
                echo "Database connection failed: " . $exception->getMessage();
            } else {
                echo "Database connection failed. Please check your configuration.";
            }
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
