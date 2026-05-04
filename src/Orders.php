<?php
/**
 * Orders Management Class
 * 
 * Handles order creation, processing, and management
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-28
 */

class Orders {
    private $db;
    private $userId;
    
    public function __construct($database, $userId = null) {
        $this->db = $database;
        $this->userId = $userId;
    }
    
    public function createOrder($orderData) {
        // Validate required fields
        $required = ['shipping_address', 'payment_method', 'total_amount'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                throw new Exception("{$field} is required");
            }
        }
        
        // Get cart items
        $cart = new Cart($this->db, $this->userId);
        $cartItems = $cart->getCart();
        
        if (empty($cartItems['items'])) {
            throw new Exception("Cart is empty");
        }
        
        // Validate total amount
        $calculatedTotal = $cartItems['total'];
        if (abs($calculatedTotal - $orderData['total_amount']) > 0.01) {
            throw new Exception("Total amount mismatch");
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Create order
            $order = [
                'user_id' => $this->userId,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'total_amount' => $orderData['total_amount'],
                'shipping_address' => json_encode($orderData['shipping_address']),
                'payment_method' => $orderData['payment_method'],
                'payment_status' => 'pending',
                'notes' => $orderData['notes'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $orderId = $this->db->insert('orders', $order);
            
            // Add order items
            foreach ($cartItems['items'] as $item) {
                $orderItem = [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('order_items', $orderItem);
                
                // Update product stock
                $products = new Products($this->db);
                $products->updateStock($item['product_id'], $item['quantity'], 'subtract');
            }
            
            // Clear cart
            $cart->clear();
            
            // Commit transaction
            $this->db->commit();
            
            // Return created order
            return $this->getById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to create order: " . $e->getMessage());
            throw new Exception("Failed to create order");
        }
    }
    
    public function getById($orderId) {
        $sql = "
            SELECT o.*, 
                   JSON_EXTRACT(o.shipping_address, '$.name') as shipping_name,
                   JSON_EXTRACT(o.shipping_address, '$.phone') as shipping_phone,
                   JSON_EXTRACT(o.shipping_address, '$.address') as shipping_address_line
            FROM orders o 
            WHERE o.id = ?
        ";
        
        try {
            $order = $this->db->fetchOne($sql, [$orderId]);
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Check if user owns this order (if user is logged in)
            if ($this->userId && $order['user_id'] != $this->userId) {
                throw new Exception("Access denied");
            }
            
            // Get order items
            $items = $this->db->fetchAll(
                "SELECT oi.*, p.name, p.image 
                 FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = ?",
                [$orderId]
            );
            
            // Format items
            foreach ($items as &$item) {
                $item['price_formatted'] = '₹' . number_format($item['price'], 2);
                $item['subtotal_formatted'] = '₹' . number_format($item['subtotal'], 2);
                $item['image'] = $item['image'] ?? '/assets/images/default-product.jpg';
            }
            
            $order['items'] = $items;
            $order['total_formatted'] = '₹' . number_format($order['total_amount'], 2);
            $order['created_at_formatted'] = date('M d, Y h:i A', strtotime($order['created_at']));
            $order['shipping_address'] = json_decode($order['shipping_address'], true);
            
            return $order;
        } catch (Exception $e) {
            error_log("Failed to get order: " . $e->getMessage());
            throw new Exception("Failed to fetch order");
        }
    }
    
    public function getUserOrders($params = []) {
        if (!$this->userId) {
            throw new Exception("User not logged in");
        }
        
        $sql = "
            SELECT o.*, 
                   COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ";
        
        $queryParams = [$this->userId];
        
        // Add status filter
        if (!empty($params['status'])) {
            $sql .= " HAVING o.status = ?";
            $queryParams[] = $params['status'];
        }
        
        // Pagination
        $limit = 20;
        $offset = 0;
        
        if (!empty($params['limit'])) {
            $limit = (int)$params['limit'];
        }
        
        if (!empty($params['page'])) {
            $offset = ((int)$params['page'] - 1) * $limit;
        }
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        try {
            $orders = $this->db->fetchAll($sql, $queryParams);
            
            // Format orders
            foreach ($orders as &$order) {
                $order['total_formatted'] = '₹' . number_format($order['total_amount'], 2);
                $order['created_at_formatted'] = date('M d, Y', strtotime($order['created_at']));
                $order['status_display'] = ucfirst($order['status']);
            }
            
            return $orders;
        } catch (Exception $e) {
            error_log("Failed to get user orders: " . $e->getMessage());
            throw new Exception("Failed to fetch orders");
        }
    }
    
    public function updateStatus($orderId, $status, $notes = '') {
        // Validate status
        $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        try {
            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($notes)) {
                $updateData['notes'] = $notes;
            }
            
            $affected = $this->db->update(
                'orders',
                $updateData,
                'id = ?',
                [$orderId]
            );
            
            if ($affected === 0) {
                throw new Exception("Order not found");
            }
            
            return $this->getById($orderId);
        } catch (Exception $e) {
            error_log("Failed to update order status: " . $e->getMessage());
            throw new Exception("Failed to update order status");
        }
    }
    
    public function updatePaymentStatus($orderId, $paymentStatus, $transactionId = '') {
        try {
            $updateData = [
                'payment_status' => $paymentStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($transactionId)) {
                $updateData['transaction_id'] = $transactionId;
            }
            
            $affected = $this->db->update(
                'orders',
                $updateData,
                'id = ?',
                [$orderId]
            );
            
            if ($affected === 0) {
                throw new Exception("Order not found");
            }
            
            // If payment is successful, update order status
            if ($paymentStatus === 'paid') {
                $this->updateStatus($orderId, 'confirmed', 'Payment received');
            }
            
            return $this->getById($orderId);
        } catch (Exception $e) {
            error_log("Failed to update payment status: " . $e->getMessage());
            throw new Exception("Failed to update payment status");
        }
    }
    
    public function cancelOrder($orderId, $reason = '') {
        try {
            $order = $this->getById($orderId);
            
            // Check if order can be cancelled
            if (!in_array($order['status'], ['pending', 'confirmed'])) {
                throw new Exception("Order cannot be cancelled at this stage");
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Restore product stock
            foreach ($order['items'] as $item) {
                $products = new Products($this->db);
                $products->updateStock($item['product_id'], $item['quantity'], 'add');
            }
            
            // Update order status
            $this->updateStatus($orderId, 'cancelled', $reason);
            
            // Update payment status if paid
            if ($order['payment_status'] === 'paid') {
                $this->updatePaymentStatus($orderId, 'refunded');
            }
            
            $this->db->commit();
            
            return ['message' => 'Order cancelled successfully'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to cancel order: " . $e->getMessage());
            throw new Exception("Failed to cancel order");
        }
    }
    
    private function generateOrderNumber() {
        // Generate unique order number
        $prefix = 'DNG';
        $date = date('ymd');
        $random = mt_rand(1000, 9999);
        
        $orderNumber = $prefix . $date . $random;
        
        // Check if it already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM orders WHERE order_number = ?",
            [$orderNumber]
        );
        
        if ($existing) {
            // Regenerate if exists
            return $this->generateOrderNumber();
        }
        
        return $orderNumber;
    }
    
    public function getOrderStats($userId = null) {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total
            FROM orders
        ";
        
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " GROUP BY status";
        
        try {
            $stats = $this->db->fetchAll($sql, $params);
            
            $result = [
                'total_orders' => 0,
                'total_revenue' => 0,
                'status_breakdown' => []
            ];
            
            foreach ($stats as $stat) {
                $result['total_orders'] += $stat['count'];
                $result['total_revenue'] += $stat['total'];
                $result['status_breakdown'][$stat['status']] = [
                    'count' => $stat['count'],
                    'total' => $stat['total']
                ];
            }
            
            $result['total_revenue_formatted'] = '₹' . number_format($result['total_revenue'], 2);
            
            return $result;
        } catch (Exception $e) {
            error_log("Failed to get order stats: " . $e->getMessage());
            throw new Exception("Failed to fetch order statistics");
        }
    }
}
?>
