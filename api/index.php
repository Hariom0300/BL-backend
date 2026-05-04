<?php
/**
 * Dongare Fashion E-commerce Backend API
 * 
 * Main API entry point - handles all incoming requests
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-15
 */

// Enable error reporting for development
if (($_ENV['ENVIRONMENT'] ?? 'development') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load dependencies
require_once '../src/Database.php';
require_once '../src/Auth.php';
require_once '../src/Products.php';
require_once '../src/Cart.php';
require_once '../src/Orders.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['FRONTEND_URL'] ?? 'http://localhost:3000'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
try {
    $db = new Database();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => ($_ENV['ENVIRONMENT'] ?? 'development') === 'development' ? $e->getMessage() : 'Internal server error'
    ]);
    exit();
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove /api prefix if present (for local development)
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}

$path = trim($path, '/');
$pathParts = explode('/', $path);

// Log requests for debugging
error_log("API Request: $method $path");

// Route requests
try {
    switch ($pathParts[0]) {
        case '':
        case 'health':
            handleHealthCheck();
            break;
            
        case 'products':
            handleProducts($method, $pathParts);
            break;
            
        case 'categories':
            handleCategories($method);
            break;
            
        case 'auth':
            handleAuth($method, $pathParts[1] ?? '');
            break;
            
        case 'cart':
            handleCart($method);
            break;
            
        case 'orders':
            handleOrders($method, $pathParts[1] ?? '');
            break;
            
        default:
            sendResponse(null, 404, 'API endpoint not found');
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendResponse(null, 500, 'Internal server error');
}

// Response helper function
function sendResponse($data, $status = 200, $message = '') {
    http_response_code($status);
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Handler functions
function handleHealthCheck() {
    sendResponse([
        'status' => 'healthy',
        'version' => '1.2.0',
        'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
        'database' => 'connected',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleProducts($method, $pathParts) {
    global $db;
    
    // For testing without database connection
    if (!$db) {
        // Return mock products for testing
        $mockProducts = [
            [
                'id' => 1,
                'name' => 'Classic White Shirt',
                'description' => 'Premium cotton white shirt for formal occasions',
                'price' => 1299.00,
                'category_id' => 1,
                'image' => '/assets/images/white-shirt.jpg',
                'stock' => 50,
                'status' => 'active',
                'created_at' => '2024-05-01 10:00:00'
            ],
            [
                'id' => 2,
                'name' => 'Blue Denim Jeans',
                'description' => 'Comfortable blue denim jeans with modern fit',
                'price' => 2499.00,
                'category_id' => 2,
                'image' => '/assets/images/blue-jeans.jpg',
                'stock' => 30,
                'status' => 'active',
                'created_at' => '2024-05-01 11:00:00'
            ],
            [
                'id' => 3,
                'name' => 'Black Leather Jacket',
                'description' => 'Genuine leather jacket for stylish look',
                'price' => 5999.00,
                'category_id' => 3,
                'image' => '/assets/images/black-jacket.jpg',
                'stock' => 15,
                'status' => 'active',
                'created_at' => '2024-05-01 12:00:00'
            ]
        ];
        
        if (!empty($pathParts[1]) && is_numeric($pathParts[1])) {
            // Get single product
            $productId = $pathParts[1];
            $product = null;
            foreach ($mockProducts as $p) {
                if ($p['id'] == $productId) {
                    $product = $p;
                    break;
                }
            }
            if ($product) {
                sendResponse($product);
            } else {
                sendResponse(null, 404, 'Product not found');
            }
        } else {
            // Get all products
            sendResponse($mockProducts);
        }
        return;
    }
    
    $products = new Products($db);
    
    switch ($method) {
        case 'GET':
            if (!empty($pathParts[1]) && is_numeric($pathParts[1])) {
                // Get single product
                $product = $products->getById($pathParts[1]);
                sendResponse($product);
            } else {
                // Get products with filters
                $params = $_GET;
                $products = $products->getAll($params);
                sendResponse($products);
            }
            break;
            
        case 'POST':
            // Create product (admin only)
            requireAuth('admin');
            $data = json_decode(file_get_contents('php://input'), true);
            $product = $products->create($data);
            sendResponse($product, 201, 'Product created successfully');
            break;
            
        case 'PUT':
            // Update product (admin only)
            requireAuth('admin');
            if (empty($pathParts[1]) || !is_numeric($pathParts[1])) {
                sendResponse(null, 400, 'Product ID required');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $product = $products->update($pathParts[1], $data);
            sendResponse($product, 200, 'Product updated successfully');
            break;
            
        case 'DELETE':
            // Delete product (admin only)
            requireAuth('admin');
            if (empty($pathParts[1]) || !is_numeric($pathParts[1])) {
                sendResponse(null, 400, 'Product ID required');
            }
            $products->delete($pathParts[1]);
            sendResponse(null, 200, 'Product deleted successfully');
            break;
            
        default:
            sendResponse(null, 405, 'Method not allowed');
    }
}

function handleCategories($method) {
    global $db;
    
    // For testing without database connection
    if (!$db) {
        $mockCategories = [
            [
                'id' => 1,
                'name' => 'Shirts',
                'description' => 'Formal and casual shirts',
                'product_count' => 15
            ],
            [
                'id' => 2,
                'name' => 'Jeans',
                'description' => 'Denim jeans for all occasions',
                'product_count' => 12
            ],
            [
                'id' => 3,
                'name' => 'Jackets',
                'description' => 'Stylish jackets and coats',
                'product_count' => 8
            ]
        ];
        
        if ($method === 'GET') {
            sendResponse($mockCategories);
        } else {
            sendResponse(null, 405, 'Method not allowed');
        }
        return;
    }
    
    $products = new Products($db);
    
    if ($method === 'GET') {
        $categories = $products->getCategories();
        sendResponse($categories);
    } else {
        sendResponse(null, 405, 'Method not allowed');
    }
}

function handleAuth($method, $action) {
    global $db;
    $auth = new Auth($db);
    
    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'login':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $auth->login($data['email'], $data['password']);
                    sendResponse($result, 200, 'Login successful');
                    break;
                    
                case 'register':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $auth->register($data);
                    sendResponse($result, 201, 'Registration successful');
                    break;
                    
                case 'logout':
                    $token = getBearerToken();
                    $result = $auth->logout($token);
                    sendResponse($result, 200, 'Logout successful');
                    break;
                    
                default:
                    sendResponse(null, 404, 'Auth endpoint not found');
            }
            break;
            
        case 'GET':
            if ($action === 'me') {
                $user = $auth->requireAuth();
                sendResponse($user);
            } else {
                sendResponse(null, 404, 'Auth endpoint not found');
            }
            break;
            
        default:
            sendResponse(null, 405, 'Method not allowed');
    }
}

function handleCart($method) {
    global $db;
    
    $user = null;
    try {
        $auth = new Auth($db);
        $user = $auth->getCurrentUser(getBearerToken());
    } catch (Exception $e) {
        // Continue with guest cart if auth fails
    }
    
    $cart = new Cart($db, $user ? $user['id'] : null);
    
    switch ($method) {
        case 'GET':
            $cartData = $cart->getCart();
            sendResponse($cartData);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $cartData = $cart->addItem($data['product_id'], $data['quantity'] ?? 1);
            sendResponse($cartData, 201, 'Item added to cart');
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $cartData = $cart->updateItem($data['product_id'], $data['quantity']);
            sendResponse($cartData, 200, 'Cart updated');
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $cartData = $cart->removeItem($data['product_id']);
            sendResponse($cartData, 200, 'Item removed from cart');
            break;
            
        default:
            sendResponse(null, 405, 'Method not allowed');
    }
}

function handleOrders($method, $action) {
    global $db;
    
    $auth = new Auth($db);
    $user = $auth->requireAuth();
    $orders = new Orders($db, $user['id']);
    
    switch ($method) {
        case 'GET':
            if ($action && is_numeric($action)) {
                // Get single order
                $order = $orders->getById($action);
                sendResponse($order);
            } else {
                // Get user orders
                $params = $_GET;
                $orderList = $orders->getUserOrders($params);
                sendResponse($orderList);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $order = $orders->createOrder($data);
            sendResponse($order, 201, 'Order created successfully');
            break;
            
        case 'PUT':
            if ($action && is_numeric($action)) {
                $data = json_decode(file_get_contents('php://input'), true);
                if (isset($data['status'])) {
                    $order = $orders->updateStatus($action, $data['status'], $data['notes'] ?? '');
                    sendResponse($order, 200, 'Order status updated');
                } else {
                    sendResponse(null, 400, 'Status required');
                }
            } else {
                sendResponse(null, 400, 'Order ID required');
            }
            break;
            
        case 'DELETE':
            if ($action && is_numeric($action)) {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $orders->cancelOrder($action, $data['reason'] ?? '');
                sendResponse($result, 200, 'Order cancelled');
            } else {
                sendResponse(null, 400, 'Order ID required');
            }
            break;
            
        default:
            sendResponse(null, 405, 'Method not allowed');
    }
}

// Helper functions
function requireAuth($role = null) {
    global $db;
    $auth = new Auth($db);
    
    if ($role === 'admin') {
        return $auth->requireAdmin();
    } else {
        return $auth->requireAuth();
    }
}

function getBearerToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        return null;
    }
    
    return str_replace('Bearer ', '', $authHeader);
}
?>
