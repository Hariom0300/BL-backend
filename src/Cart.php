<?php
/**
 * Shopping Cart Management Class
 * 
 * Handles cart operations, session management, and checkout process
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-25
 */

class Cart {
    private $db;
    private $userId;
    
    public function __construct($database, $userId = null) {
        $this->db = $database;
        $this->userId = $userId;
    }
    
    public function getCart() {
        if ($this->userId) {
            return $this->getUserCart();
        } else {
            return $this->getSessionCart();
        }
    }
    
    private function getUserCart() {
        $sql = "
            SELECT ci.*, p.name, p.price, p.image, p.stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.user_id = ? AND ci.status = 'active'
            ORDER BY ci.created_at DESC
        ";
        
        try {
            $items = $this->db->fetchAll($sql, [$this->userId]);
            
            $cart = [
                'items' => [],
                'total' => 0,
                'count' => 0
            ];
            
            foreach ($items as $item) {
                // Check if product is still available
                if ($item['stock'] < $item['quantity']) {
                    // Update cart item to available stock
                    $item['quantity'] = min($item['quantity'], $item['stock']);
                    $this->updateItem($item['product_id'], $item['quantity']);
                }
                
                $item['subtotal'] = $item['price'] * $item['quantity'];
                $item['price_formatted'] = '₹' . number_format($item['price'], 2);
                $item['subtotal_formatted'] = '₹' . number_format($item['subtotal'], 2);
                
                $cart['items'][] = $item;
                $cart['total'] += $item['subtotal'];
                $cart['count'] += $item['quantity'];
            }
            
            $cart['total_formatted'] = '₹' . number_format($cart['total'], 2);
            
            return $cart;
        } catch (Exception $e) {
            error_log("Failed to get user cart: " . $e->getMessage());
            throw new Exception("Failed to fetch cart");
        }
    }
    
    private function getSessionCart() {
        // For guest users, store cart in session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $cart = [
            'items' => [],
            'total' => 0,
            'count' => 0
        ];
        
        foreach ($_SESSION['cart'] as $productId => $item) {
            // Get product details
            $product = $this->db->fetchOne(
                "SELECT * FROM products WHERE id = ? AND status = 'active'",
                [$productId]
            );
            
            if ($product) {
                // Check stock
                if ($product['stock'] < $item['quantity']) {
                    $item['quantity'] = min($item['quantity'], $product['stock']);
                    $_SESSION['cart'][$productId]['quantity'] = $item['quantity'];
                }
                
                $item['product_id'] = $productId;
                $item['name'] = $product['name'];
                $item['price'] = $product['price'];
                $item['image'] = $product['image'] ?? '/assets/images/default-product.jpg';
                $item['subtotal'] = $item['price'] * $item['quantity'];
                $item['price_formatted'] = '₹' . number_format($item['price'], 2);
                $item['subtotal_formatted'] = '₹' . number_format($item['subtotal'], 2);
                
                $cart['items'][] = $item;
                $cart['total'] += $item['subtotal'];
                $cart['count'] += $item['quantity'];
            }
        }
        
        $cart['total_formatted'] = '₹' . number_format($cart['total'], 2);
        
        return $cart;
    }
    
    public function addItem($productId, $quantity = 1) {
        // Validate product exists and is active
        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND status = 'active'",
            [$productId]
        );
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Check stock
        if ($product['stock'] < $quantity) {
            throw new Exception("Insufficient stock. Only {$product['stock']} available.");
        }
        
        if ($this->userId) {
            return $this->addUserItem($productId, $quantity);
        } else {
            return $this->addSessionItem($productId, $quantity);
        }
    }
    
    private function addUserItem($productId, $quantity) {
        // Check if item already exists in cart
        $existing = $this->db->fetchOne(
            "SELECT * FROM cart_items WHERE user_id = ? AND product_id = ? AND status = 'active'",
            [$this->userId, $productId]
        );
        
        try {
            if ($existing) {
                // Update quantity
                $newQuantity = $existing['quantity'] + $quantity;
                
                // Check stock again
                $product = $this->db->fetchOne(
                    "SELECT stock FROM products WHERE id = ?",
                    [$productId]
                );
                
                if ($product['stock'] < $newQuantity) {
                    throw new Exception("Insufficient stock. Only {$product['stock']} available.");
                }
                
                $this->db->update(
                    'cart_items',
                    ['quantity' => $newQuantity, 'updated_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$existing['id']]
                );
            } else {
                // Add new item
                $cartItem = [
                    'user_id' => $this->userId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('cart_items', $cartItem);
            }
            
            return $this->getUserCart();
        } catch (Exception $e) {
            error_log("Failed to add item to user cart: " . $e->getMessage());
            throw new Exception("Failed to add item to cart");
        }
    }
    
    private function addSessionItem($productId, $quantity) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$productId])) {
            // Update quantity
            $newQuantity = $_SESSION['cart'][$productId]['quantity'] + $quantity;
            
            // Check stock
            $product = $this->db->fetchOne(
                "SELECT stock FROM products WHERE id = ?",
                [$productId]
            );
            
            if ($product['stock'] < $newQuantity) {
                throw new Exception("Insufficient stock. Only {$product['stock']} available.");
            }
            
            $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
        } else {
            // Add new item
            $_SESSION['cart'][$productId] = [
                'quantity' => $quantity,
                'added_at' => time()
            ];
        }
        
        return $this->getSessionCart();
    }
    
    public function updateItem($productId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }
        
        // Check stock
        $product = $this->db->fetchOne(
            "SELECT stock FROM products WHERE id = ? AND status = 'active'",
            [$productId]
        );
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        if ($product['stock'] < $quantity) {
            throw new Exception("Insufficient stock. Only {$product['stock']} available.");
        }
        
        if ($this->userId) {
            return $this->updateUserItem($productId, $quantity);
        } else {
            return $this->updateSessionItem($productId, $quantity);
        }
    }
    
    private function updateUserItem($productId, $quantity) {
        try {
            $affected = $this->db->update(
                'cart_items',
                ['quantity' => $quantity, 'updated_at' => date('Y-m-d H:i:s')],
                'user_id = ? AND product_id = ? AND status = ?',
                [$this->userId, $productId, 'active']
            );
            
            if ($affected === 0) {
                throw new Exception("Item not found in cart");
            }
            
            return $this->getUserCart();
        } catch (Exception $e) {
            error_log("Failed to update user cart item: " . $e->getMessage());
            throw new Exception("Failed to update cart item");
        }
    }
    
    private function updateSessionItem($productId, $quantity) {
        if (!isset($_SESSION['cart'][$productId])) {
            throw new Exception("Item not found in cart");
        }
        
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
        
        return $this->getSessionCart();
    }
    
    public function removeItem($productId) {
        if ($this->userId) {
            return $this->removeUserItem($productId);
        } else {
            return $this->removeSessionItem($productId);
        }
    }
    
    private function removeUserItem($productId) {
        try {
            $affected = $this->db->update(
                'cart_items',
                ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')],
                'user_id = ? AND product_id = ? AND status = ?',
                [$this->userId, $productId, 'active']
            );
            
            if ($affected === 0) {
                throw new Exception("Item not found in cart");
            }
            
            return $this->getUserCart();
        } catch (Exception $e) {
            error_log("Failed to remove user cart item: " . $e->getMessage());
            throw new Exception("Failed to remove cart item");
        }
    }
    
    private function removeSessionItem($productId) {
        if (!isset($_SESSION['cart'][$productId])) {
            throw new Exception("Item not found in cart");
        }
        
        unset($_SESSION['cart'][$productId]);
        
        return $this->getSessionCart();
    }
    
    public function clear() {
        if ($this->userId) {
            return $this->clearUserCart();
        } else {
            return $this->clearSessionCart();
        }
    }
    
    private function clearUserCart() {
        try {
            $this->db->update(
                'cart_items',
                ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')],
                'user_id = ? AND status = ?',
                [$this->userId, 'active']
            );
            
            return ['message' => 'Cart cleared successfully'];
        } catch (Exception $e) {
            error_log("Failed to clear user cart: " . $e->getMessage());
            throw new Exception("Failed to clear cart");
        }
    }
    
    private function clearSessionCart() {
        $_SESSION['cart'] = [];
        
        return ['message' => 'Cart cleared successfully'];
    }
    
    public function getCartCount() {
        $cart = $this->getCart();
        return $cart['count'];
    }
    
    public function getCartTotal() {
        $cart = $this->getCart();
        return $cart['total'];
    }
    
    // Convert session cart to user cart when user logs in
    public function mergeSessionCart($userId) {
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            return;
        }
        
        $this->userId = $userId;
        
        foreach ($_SESSION['cart'] as $productId => $item) {
            try {
                $this->addItem($productId, $item['quantity']);
            } catch (Exception $e) {
                // Skip items that can't be added (e.g., out of stock)
                error_log("Failed to merge cart item: " . $e->getMessage());
            }
        }
        
        // Clear session cart
        unset($_SESSION['cart']);
    }
}
?>
