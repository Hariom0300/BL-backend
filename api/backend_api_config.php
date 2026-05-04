<?php
/**
 * Backend API Configuration for BL-backend Repository
 * This file handles API endpoints for the frontend
 */

// CORS Configuration for Frontend-Backend Separation
header('Access-Control-Allow-Origin: https://hariom0300.github.io');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// API Response Helper
function apiResponse($data, $status = 200, $message = '') {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'data' => $data,
        'message' => $message,
        'status' => $status
    ]);
    exit();
}

// Database Configuration (for backend)
define('DB_HOST', 'localhost');
define('DB_NAME', 'dongare_backend');
define('DB_USER', 'root');
define('DB_PASS', '');

// Backend URL Configuration
define('BACKEND_URL', 'https://bl-backend.onrender.com');
define('FRONTEND_URL', 'https://hariom0300.github.io/BL-frontend');

// JWT Configuration for API Authentication
define('JWT_SECRET', 'your-super-secret-jwt-key-here');
define('JWT_EXPIRY', 3600); // 1 hour

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/products/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Initialize Database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    apiResponse(null, 500, 'Database connection failed: ' . $e->getMessage());
}

// Include API Routes
require_once 'api_routes.php';
?>
