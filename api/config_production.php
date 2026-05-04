<?php
// Production Configuration for Dongare E-commerce Website
// Replace these values with your actual production settings

// Environment
define('ENVIRONMENT', 'production');

// Site Configuration
define('SITE_NAME', 'Dongare');
define('SITE_TAGLINE', 'Premium Fashion');
define('SITE_URL', 'https://yourdomain.com'); // Replace with your actual domain
define('ADMIN_EMAIL', 'hariomvimal33333@gmail.com');

// Database Configuration
// For production, consider using MySQL instead of SQLite for better performance
define('DB_HOST', 'localhost');
define('DB_NAME', 'dongare_ecommerce');
define('DB_USER', 'your_db_user'); // Replace with actual DB user
define('DB_PASS', 'your_db_password'); // Replace with actual DB password
define('DB_TYPE', 'mysql'); // 'mysql' or 'sqlite'

// File Upload Configuration
define('UPLOAD_PATH', 'images/products/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// E-commerce Configuration
define('TAX_RATE', 0.05); // 5% tax
define('FREE_SHIPPING_MIN', 2000); // Free shipping above ₹2000
define('SHIPPING_COST', 149); // ₹149 shipping cost
define('CURRENCY_SYMBOL', '₹');

// Security Configuration
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here'); // Generate a secure key
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Email Configuration (for order notifications, etc.)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Dongare Fashion');

// Payment Gateway Configuration (if using payment gateways)
define('RAZORPAY_KEY_ID', 'your_razorpay_key_id');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_key_secret');

// Error Reporting
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS (if needed)
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Production Database Class
class ProductionDatabase {
    private $conn;
    
    public function __construct() {
        try {
            if (DB_TYPE === 'mysql') {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                // SQLite fallback
                $this->conn = new PDO('sqlite:' . __DIR__ . '/dongare_ecommerce.db');
                $this->conn->exec("PRAGMA foreign_keys=ON");
            }
        } catch(PDOException $exception) {
            if (ENVIRONMENT === 'production') {
                error_log("Database connection failed: " . $exception->getMessage());
                die("Service temporarily unavailable. Please try again later.");
            } else {
                die("Database connection failed: " . $exception->getMessage());
            }
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function createTables() {
        // Table creation code here (same as config_sqlite.php)
        $this->createProductionTables();
    }
    
    private function createProductionTables() {
        // Import table creation from config_sqlite.php
        require_once __DIR__ . '/config_sqlite.php';
        $db = new Database();
        $db->createTables();
    }
}

// Initialize production database
$database = new ProductionDatabase();
$pdo = $database->getConnection();

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    session_name('dongare_session');
    session_start();
    
    // Regenerate session ID for security
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}
?>
