<?php
/**
 * Products Management Class
 * 
 * Handles all product-related operations including CRUD, search, and filtering
 * 
 * @author Hariom Vimal
 * @version 1.2.0
 * @since 2024-04-15
 */

class Products {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getAll($params = []) {
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active'
        ";
        
        $queryParams = [];
        
        // Category filter
        if (!empty($params['category'])) {
            $sql .= " AND p.category_id = ?";
            $queryParams[] = $params['category'];
        }
        
        // Search filter
        if (!empty($params['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }
        
        // Price range filter
        if (!empty($params['min_price'])) {
            $sql .= " AND p.price >= ?";
            $queryParams[] = $params['min_price'];
        }
        
        if (!empty($params['max_price'])) {
            $sql .= " AND p.price <= ?";
            $queryParams[] = $params['max_price'];
        }
        
        // Sorting
        $orderBy = 'p.created_at DESC'; // default
        if (!empty($params['sort'])) {
            switch ($params['sort']) {
                case 'price_low':
                    $orderBy = 'p.price ASC';
                    break;
                case 'price_high':
                    $orderBy = 'p.price DESC';
                    break;
                case 'name_asc':
                    $orderBy = 'p.name ASC';
                    break;
                case 'name_desc':
                    $orderBy = 'p.name DESC';
                    break;
                case 'newest':
                    $orderBy = 'p.created_at DESC';
                    break;
                case 'oldest':
                    $orderBy = 'p.created_at ASC';
                    break;
            }
        }
        
        $sql .= " ORDER BY {$orderBy}";
        
        // Pagination
        $limit = 20; // default
        $offset = 0;
        
        if (!empty($params['limit'])) {
            $limit = (int)$params['limit'];
        }
        
        if (!empty($params['page'])) {
            $offset = ((int)$params['page'] - 1) * $limit;
        }
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        try {
            $products = $this->db->fetchAll($sql, $queryParams);
            
            // Format products for API response
            foreach ($products as &$product) {
                $product['image'] = $product['image'] ?? '/assets/images/default-product.jpg';
                $product['price_formatted'] = '₹' . number_format($product['price'], 2);
                $product['created_at_formatted'] = date('M d, Y', strtotime($product['created_at']));
            }
            
            return $products;
        } catch (Exception $e) {
            error_log("Failed to get products: " . $e->getMessage());
            throw new Exception("Failed to fetch products");
        }
    }
    
    public function getById($id) {
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.status = 'active'
        ";
        
        try {
            $product = $this->db->fetchOne($sql, [$id]);
            
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            // Get product variants if any
            $variants = $this->db->fetchAll(
                "SELECT * FROM product_variants WHERE product_id = ? AND status = 'active'",
                [$id]
            );
            
            $product['variants'] = $variants;
            $product['image'] = $product['image'] ?? '/assets/images/default-product.jpg';
            $product['price_formatted'] = '₹' . number_format($product['price'], 2);
            $product['created_at_formatted'] = date('M d, Y', strtotime($product['created_at']));
            
            return $product;
        } catch (Exception $e) {
            error_log("Failed to get product: " . $e->getMessage());
            throw new Exception("Failed to fetch product");
        }
    }
    
    public function create($productData) {
        // Validate required fields
        $required = ['name', 'price', 'category_id', 'description'];
        foreach ($required as $field) {
            if (empty($productData[$field])) {
                throw new Exception("{$field} is required");
            }
        }
        
        // Validate price
        if (!is_numeric($productData['price']) || $productData['price'] <= 0) {
            throw new Exception("Invalid price");
        }
        
        // Set defaults
        $productData['status'] = 'active';
        $productData['created_at'] = date('Y-m-d H:i:s');
        $productData['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            $productId = $this->db->insert('products', $productData);
            
            // Get created product
            return $this->getById($productId);
        } catch (Exception $e) {
            error_log("Failed to create product: " . $e->getMessage());
            throw new Exception("Failed to create product");
        }
    }
    
    public function update($id, $productData) {
        // Check if product exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM products WHERE id = ?",
            [$id]
        );
        
        if (!$existing) {
            throw new Exception("Product not found");
        }
        
        // Validate price if provided
        if (isset($productData['price'])) {
            if (!is_numeric($productData['price']) || $productData['price'] <= 0) {
                throw new Exception("Invalid price");
            }
        }
        
        $productData['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            $affected = $this->db->update(
                'products',
                $productData,
                'id = ?',
                [$id]
            );
            
            if ($affected === 0) {
                throw new Exception("No changes made");
            }
            
            return $this->getById($id);
        } catch (Exception $e) {
            error_log("Failed to update product: " . $e->getMessage());
            throw new Exception("Failed to update product");
        }
    }
    
    public function delete($id) {
        // Soft delete - just mark as inactive
        try {
            $affected = $this->db->update(
                'products',
                ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$id]
            );
            
            if ($affected === 0) {
                throw new Exception("Product not found");
            }
            
            return ['message' => 'Product deleted successfully'];
        } catch (Exception $e) {
            error_log("Failed to delete product: " . $e->getMessage());
            throw new Exception("Failed to delete product");
        }
    }
    
    public function getCategories() {
        try {
            $categories = $this->db->fetchAll(
                "SELECT * FROM categories WHERE status = 'active' ORDER BY name"
            );
            
            // Get product count for each category
            foreach ($categories as &$category) {
                $count = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'",
                    [$category['id']]
                );
                $category['product_count'] = $count['count'];
            }
            
            return $categories;
        } catch (Exception $e) {
            error_log("Failed to get categories: " . $e->getMessage());
            throw new Exception("Failed to fetch categories");
        }
    }
    
    public function search($query, $filters = []) {
        $params = [
            'search' => $query,
            'limit' => 50
        ];
        
        // Add any additional filters
        if (!empty($filters['category'])) {
            $params['category'] = $filters['category'];
        }
        
        if (!empty($filters['min_price'])) {
            $params['min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $params['max_price'] = $filters['max_price'];
        }
        
        return $this->getAll($params);
    }
    
    public function getFeatured($limit = 8) {
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' AND p.featured = 1
            ORDER BY RAND()
            LIMIT {$limit}
        ";
        
        try {
            $products = $this->db->fetchAll($sql);
            
            // Format products
            foreach ($products as &$product) {
                $product['image'] = $product['image'] ?? '/assets/images/default-product.jpg';
                $product['price_formatted'] = '₹' . number_format($product['price'], 2);
            }
            
            return $products;
        } catch (Exception $e) {
            error_log("Failed to get featured products: " . $e->getMessage());
            throw new Exception("Failed to fetch featured products");
        }
    }
    
    public function updateStock($productId, $quantity, $operation = 'subtract') {
        try {
            if ($operation === 'subtract') {
                $sql = "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?";
                $params = [$quantity, $productId, $quantity];
            } else {
                $sql = "UPDATE products SET stock = stock + ? WHERE id = ?";
                $params = [$quantity, $productId];
            }
            
            $affected = $this->db->query($sql, $params)->rowCount();
            
            if ($affected === 0 && $operation === 'subtract') {
                throw new Exception("Insufficient stock");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to update stock: " . $e->getMessage());
            throw new Exception("Failed to update stock");
        }
    }
}
?>
