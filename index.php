<?php
/**
 * Main Backend Entry Point for BL-backend Repository
 * Handles all API requests and routing
 */

// Include configuration
require_once 'backend_api_config.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['FRONTEND_URL'] ?? 'https://hariom0300.github.io/BL-frontend'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Log requests for debugging
error_log("API Request: $method $path");

// Route to appropriate handler
switch ($path) {
    case '':
    case 'health':
        healthCheck();
        break;
        
    case 'products':
        handleProducts($method);
        break;
        
    case 'categories':
        handleCategories($method);
        break;
        
    case 'auth/login':
        handleLogin();
        break;
        
    case 'auth/register':
        handleRegister();
        break;
        
    case 'cart':
        handleCart($method);
        break;
        
    case 'orders':
        handleOrders($method);
        break;
        
    default:
        // Check for product ID
        if (preg_match('/^products\/(\d+)$/', $path, $matches)) {
            handleProduct($matches[1], $method);
        } else {
            apiResponse(null, 404, 'API endpoint not found');
        }
        break;
}

// API Handler Functions
function healthCheck() {
    apiResponse([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'environment' => $_ENV['ENVIRONMENT'] ?? 'development'
    ]);
}

function handleProducts($method) {
    switch ($method) {
        case 'GET':
            getProducts();
            break;
        case 'POST':
            createProduct();
            break;
        default:
            apiResponse(null, 405, 'Method not allowed');
    }
}

function handleCategories($method) {
    if ($method === 'GET') {
        getCategories();
    } else {
        apiResponse(null, 405, 'Method not allowed');
    }
}

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        login();
    } else {
        apiResponse(null, 405, 'Method not allowed');
    }
}

function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        register();
    } else {
        apiResponse(null, 405, 'Method not allowed');
    }
}

function handleCart($method) {
    switch ($method) {
        case 'GET':
            getCart();
            break;
        case 'POST':
            addToCart();
            break;
        default:
            apiResponse(null, 405, 'Method not allowed');
    }
}

function handleOrders($method) {
    switch ($method) {
        case 'GET':
            getOrders();
            break;
        case 'POST':
            createOrder();
            break;
        default:
            apiResponse(null, 405, 'Method not allowed');
    }
}

function handleProduct($id, $method) {
    switch ($method) {
        case 'GET':
            getProduct($id);
            break;
        case 'PUT':
            updateProduct($id);
            break;
        case 'DELETE':
            deleteProduct($id);
            break;
        default:
            apiResponse(null, 405, 'Method not allowed');
    }
}

// Include API route handlers
require_once 'api_routes.php';
?>
