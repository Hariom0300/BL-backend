<?php
/**
 * API Routes for BL-backend Repository
 * RESTful endpoints for frontend integration
 */

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Route the request
switch ($path) {
    // Products API
    case 'products':
        if ($method === 'GET') {
            getProducts();
        } elseif ($method === 'POST') {
            createProduct();
        }
        break;
        
    case 'products/':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($method === 'GET' && $id > 0) {
            getProduct($id);
        } elseif ($method === 'PUT' && $id > 0) {
            updateProduct($id);
        } elseif ($method === 'DELETE' && $id > 0) {
            deleteProduct($id);
        }
        break;
    
    // Categories API
    case 'categories':
        if ($method === 'GET') {
            getCategories();
        }
        break;
    
    // Auth API
    case 'auth/login':
        if ($method === 'POST') {
            login();
        }
        break;
        
    case 'auth/register':
        if ($method === 'POST') {
            register();
        }
        break;
        
    case 'auth/logout':
        if ($method === 'POST') {
            logout();
        }
        break;
    
    // Cart API
    case 'cart':
        if ($method === 'GET') {
            getCart();
        } elseif ($method === 'POST') {
            addToCart();
        }
        break;
        
    case 'cart/clear':
        if ($method === 'POST') {
            clearCart();
        }
        break;
    
    // Orders API
    case 'orders':
        if ($method === 'GET') {
            getOrders();
        } elseif ($method === 'POST') {
            createOrder();
        }
        break;
    
    default:
        apiResponse(null, 404, 'API endpoint not found');
}

// Products Functions
function getProducts() {
    global $pdo;
    
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    
    $query = "SELECT p.*, c.name as category_name 
               FROM products p 
               LEFT JOIN categories c ON p.category_id = c.id 
               WHERE p.is_active = 1";
    
    $params = [];
    
    if ($category_id > 0) {
        $query .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        apiResponse($products);
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

function getProduct($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Get variants
            $variantStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1");
            $variantStmt->execute([$id]);
            $product['variants'] = $variantStmt->fetchAll();
        }
        
        apiResponse($product);
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

// Categories Functions
function getCategories() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
        $categories = $stmt->fetchAll();
        apiResponse($categories);
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

// Authentication Functions
function login() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        apiResponse(null, 400, 'Email and password required');
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'customer'");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($data['password'], $user['password'])) {
            // Generate JWT token
            $token = generateJWT($user);
            apiResponse([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            apiResponse(null, 401, 'Invalid credentials');
        }
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

function register() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['username', 'email', 'password', 'full_name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            apiResponse(null, 400, "$field is required");
        }
    }
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$data['email'], $data['username']]);
        
        if ($stmt->fetch()) {
            apiResponse(null, 400, 'Email or username already exists');
        }
        
        // Create user
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone, role) 
            VALUES (?, ?, ?, ?, ?, 'customer')
        ");
        $stmt->execute([
            $data['username'],
            $data['email'],
            $hash,
            $data['full_name'],
            $data['phone'] ?? ''
        ]);
        
        $user_id = $pdo->lastInsertId();
        apiResponse(['user_id' => $user_id], 201, 'User registered successfully');
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

// Cart Functions
function getCart() {
    $session_id = session_id();
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.session_id = ?
        ");
        $stmt->execute([$session_id]);
        $cart = $stmt->fetchAll();
        
        apiResponse($cart);
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

function addToCart() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['product_id']) || !isset($data['quantity'])) {
        apiResponse(null, 400, 'Product ID and quantity required');
    }
    
    $session_id = session_id();
    global $pdo;
    
    try {
        // Check if already in cart
        $stmt = $pdo->prepare("
            SELECT id FROM cart WHERE session_id = ? AND product_id = ?
        ");
        $stmt->execute([$session_id, $data['product_id']]);
        
        if ($stmt->fetch()) {
            // Update quantity
            $stmt = $pdo->prepare("
                UPDATE cart SET quantity = quantity + ? 
                WHERE session_id = ? AND product_id = ?
            ");
            $stmt->execute([$data['quantity'], $session_id, $data['product_id']]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("
                INSERT INTO cart (session_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$session_id, $data['product_id'], $data['quantity']]);
        }
        
        apiResponse(null, 200, 'Item added to cart');
    } catch (PDOException $e) {
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

// Orders Functions
function createOrder() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate order data
    if (!isset($data['items']) || !isset($data['shipping_info'])) {
        apiResponse(null, 400, 'Order items and shipping info required');
    }
    
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Create order
        $order_number = 'DG' . date('Ymd') . strtoupper(substr(uniqid(), -5));
        $total_amount = calculateOrderTotal($data['items']);
        
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, user_id, total_amount, status, payment_status, shipping_name, shipping_phone, shipping_address) 
            VALUES (?, ?, ?, 'pending', 'pending', ?, ?, ?)
        ");
        $stmt->execute([
            $order_number,
            $_SESSION['user_id'] ?? null,
            $total_amount,
            $data['shipping_info']['name'],
            $data['shipping_info']['phone'],
            $data['shipping_info']['address']
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Add order items
        foreach ($data['items'] as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price_per_item, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['price'] * $item['quantity']
            ]);
        }
        
        $pdo->commit();
        apiResponse(['order_id' => $order_id, 'order_number' => $order_number], 201, 'Order created successfully');
    } catch (PDOException $e) {
        $pdo->rollBack();
        apiResponse(null, 500, 'Database error: ' . $e->getMessage());
    }
}

// Helper Functions
function calculateOrderTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function generateJWT($user) {
    // Simple JWT implementation (use firebase/php-jwt in production)
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'exp' => time() + JWT_EXPIRY
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>
