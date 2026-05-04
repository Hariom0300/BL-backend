<?php
require_once __DIR__ . '/../config_sqlite.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../login.php');
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $slug = sanitize($_POST['slug']);
                $description = sanitize($_POST['description']);
                $price = floatval($_POST['price']);
                $original_price = floatval($_POST['original_price']);
                $category_id = intval($_POST['category_id']);
                $brand = sanitize($_POST['brand']);
                $material = sanitize($_POST['material']);
                $care_instructions = sanitize($_POST['care_instructions']);
                $image = sanitize($_POST['image']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new = isset($_POST['is_new']) ? 1 : 0;
                $sku = sanitize($_POST['sku']);
                
                if (empty($name) || empty($price) || empty($category_id)) {
                    $error = 'Name, price, and category are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO products (name, slug, description, price, original_price, category_id, brand, material, care_instructions, image, stock_quantity, is_active, is_featured, is_new, sku) VALUES (:name, :slug, :description, :price, :original_price, :category_id, :brand, :material, :care_instructions, :image, :stock_quantity, :is_active, :is_featured, :is_new, :sku)");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':slug', $slug);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':price', $price);
                        $stmt->bindParam(':original_price', $original_price);
                        $stmt->bindParam(':category_id', $category_id);
                        $stmt->bindParam(':brand', $brand);
                        $stmt->bindParam(':material', $material);
                        $stmt->bindParam(':care_instructions', $care_instructions);
                        $stmt->bindParam(':image', $image);
                        $stmt->bindParam(':stock_quantity', $stock_quantity);
                        $stmt->bindParam(':is_active', $is_active);
                        $stmt->bindParam(':is_featured', $is_featured);
                        $stmt->bindParam(':is_new', $is_new);
                        $stmt->bindParam(':sku', $sku);
                        
                        if ($stmt->execute()) {
                            $product_id = $pdo->lastInsertId();
                            
                            // Add product variants if provided
                            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                                foreach ($_POST['variants'] as $variant) {
                                    if (!empty($variant['size']) && !empty($variant['color']) && !empty($variant['stock'])) {
                                        $variant_sku = $sku . '-' . $variant['size'] . '-' . strtoupper(substr($variant['color'], 0, 3));
                                        $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock_quantity) VALUES (:product_id, :size, :color, :color_hex, :sku, :stock_quantity)");
                                        $stmt->bindParam(':product_id', $product_id);
                                        $stmt->bindParam(':size', $variant['size']);
                                        $stmt->bindParam(':color', $variant['color']);
                                        $stmt->bindParam(':color_hex', $variant['color_hex']);
                                        $stmt->bindParam(':sku', $variant_sku);
                                        $stmt->bindParam(':stock_quantity', $variant['stock']);
                                        $stmt->execute();
                                    }
                                }
                            }
                            
                            $message = 'Product added successfully!';
                        } else {
                            $error = 'Failed to add product.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = sanitize($_POST['name']);
                $slug = sanitize($_POST['slug']);
                $description = sanitize($_POST['description']);
                $price = floatval($_POST['price']);
                $original_price = floatval($_POST['original_price']);
                $category_id = intval($_POST['category_id']);
                $brand = sanitize($_POST['brand']);
                $material = sanitize($_POST['material']);
                $care_instructions = sanitize($_POST['care_instructions']);
                $image = sanitize($_POST['image']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new = isset($_POST['is_new']) ? 1 : 0;
                $sku = sanitize($_POST['sku']);
                
                if (empty($name) || empty($price) || empty($category_id)) {
                    $error = 'Name, price, and category are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE products SET name = :name, slug = :slug, description = :description, price = :price, original_price = :original_price, category_id = :category_id, brand = :brand, material = :material, care_instructions = :care_instructions, image = :image, stock_quantity = :stock_quantity, is_active = :is_active, is_featured = :is_featured, is_new = :is_new, sku = :sku WHERE id = :id");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':slug', $slug);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':price', $price);
                        $stmt->bindParam(':original_price', $original_price);
                        $stmt->bindParam(':category_id', $category_id);
                        $stmt->bindParam(':brand', $brand);
                        $stmt->bindParam(':material', $material);
                        $stmt->bindParam(':care_instructions', $care_instructions);
                        $stmt->bindParam(':image', $image);
                        $stmt->bindParam(':stock_quantity', $stock_quantity);
                        $stmt->bindParam(':is_active', $is_active);
                        $stmt->bindParam(':is_featured', $is_featured);
                        $stmt->bindParam(':is_new', $is_new);
                        $stmt->bindParam(':sku', $sku);
                        $stmt->bindParam(':id', $id);
                        
                        if ($stmt->execute()) {
                            // Update product variants
                            $stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = :product_id");
                            $stmt->bindParam(':product_id', $id);
                            $stmt->execute();
                            
                            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                                foreach ($_POST['variants'] as $variant) {
                                    if (!empty($variant['size']) && !empty($variant['color']) && !empty($variant['stock'])) {
                                        $variant_sku = $sku . '-' . $variant['size'] . '-' . strtoupper(substr($variant['color'], 0, 3));
                                        $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock_quantity) VALUES (:product_id, :size, :color, :color_hex, :sku, :stock_quantity)");
                                        $stmt->bindParam(':product_id', $id);
                                        $stmt->bindParam(':size', $variant['size']);
                                        $stmt->bindParam(':color', $variant['color']);
                                        $stmt->bindParam(':color_hex', $variant['color_hex']);
                                        $stmt->bindParam(':sku', $variant_sku);
                                        $stmt->bindParam(':stock_quantity', $variant['stock']);
                                        $stmt->execute();
                                    }
                                }
                            }
                            
                            $message = 'Product updated successfully!';
                        } else {
                            $error = 'Failed to update product.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                try {
                    $stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = :product_id");
                    $stmt->bindParam(':product_id', $id);
                    $stmt->execute();
                    
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    
                    if ($stmt->execute()) {
                        $message = 'Product deleted successfully!';
                    } else {
                        $error = 'Failed to delete product.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get products with categories
$products = [];
try {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get product for editing
$editing_product = null;
$editing_variants = [];
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $editing_product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($editing_product) {
            $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $id);
            $stmt->execute();
            $editing_variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Dongare Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
        }
        
        .admin-sidebar ul {
            list-style: none;
        }
        
        .admin-sidebar li {
            margin-bottom: 0.5rem;
        }
        
        .admin-sidebar a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .admin-sidebar a:hover,
        .admin-sidebar a.active {
            background-color: #34495e;
        }
        
        .admin-main {
            flex: 1;
            background-color: #f8f9fa;
            padding: 2rem;
        }
        
        .admin-header {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .admin-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .admin-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .admin-table th {
            background-color: #007bff;
            color: white;
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .product-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .status-active { background-color: #28a745; color: white; }
        .status-inactive { background-color: #dc3545; color: white; }
        
        .variant-section {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .variant-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .product-image-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .checkbox-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <div style="padding: 0 1.5rem 2rem;">
                <h3>Admin Panel</h3>
                <p style="opacity: 0.8; font-size: 0.875rem;">Welcome, <?php echo $_SESSION['username']; ?></p>
            </div>
            
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="products.php" class="active">Products</a></li>
                <li><a href="categories.php">Categories</a></li>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="analytics.php">Analytics</a></li>
                <li><a href="../index.php">View Site</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <!-- Admin Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Products Management</h1>
                <p>Add, edit, and manage your product catalog</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Product Form -->
            <div class="product-form">
                <h3><?php echo $editing_product ? 'Edit Product' : 'Add New Product'; ?></h3>
                
                <form method="POST" id="productForm">
                    <input type="hidden" name="action" value="<?php echo $editing_product ? 'edit' : 'add'; ?>">
                    <?php if ($editing_product): ?>
                        <input type="hidden" name="id" value="<?php echo $editing_product['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">URL Slug</label>
                            <input type="text" id="slug" name="slug" class="form-control" 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['slug']) : ''; ?>"
                                   placeholder="auto-generated if empty">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($editing_product && $editing_product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Brand</label>
                            <input type="text" id="brand" name="brand" class="form-control" 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['brand']) : ''; ?>"
                                   placeholder="e.g., Dongare Originals">
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="price">Price *</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required 
                                   value="<?php echo $editing_product ? $editing_product['price'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="original_price">Original Price</label>
                            <input type="number" id="original_price" name="original_price" class="form-control" step="0.01" min="0" 
                                   value="<?php echo $editing_product ? $editing_product['original_price'] : ''; ?>"
                                   placeholder="for discount display">
                        </div>
                        
                        <div class="form-group">
                            <label for="sku">SKU</label>
                            <input type="text" id="sku" name="sku" class="form-control" 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['sku']) : ''; ?>"
                                   placeholder="e.g., DG-MEN-001">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Product Image URL</label>
                        <input type="url" id="image" name="image" class="form-control" 
                               value="<?php echo $editing_product ? htmlspecialchars($editing_product['image']) : ''; ?>"
                               placeholder="https://example.com/image.jpg"
                               onchange="previewImage(this.value)">
                        <?php if ($editing_product && !empty($editing_product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($editing_product['image']); ?>" class="product-image-preview" alt="Product preview">
                        <?php endif; ?>
                        <div id="imagePreview" class="product-image-preview" style="display: none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo $editing_product ? htmlspecialchars($editing_product['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="material">Material</label>
                            <input type="text" id="material" name="material" class="form-control" 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['material']) : ''; ?>"
                                   placeholder="e.g., 100% Cotton">
                        </div>
                        
                        <div class="form-group">
                            <label for="care_instructions">Care Instructions</label>
                            <input type="text" id="care_instructions" name="care_instructions" class="form-control" 
                                   value="<?php echo $editing_product ? htmlspecialchars($editing_product['care_instructions']) : ''; ?>"
                                   placeholder="e.g., Machine wash cold">
                        </div>
                    </div>
                    
                    <!-- Product Variants Section -->
                    <div class="variant-section">
                        <h4>Product Variants (Colors & Sizes)</h4>
                        <p style="color: #666; font-size: 0.875rem; margin-bottom: 1rem;">Add different colors and sizes with their stock quantities</p>
                        
                        <div id="variantsContainer">
                            <?php if ($editing_product && !empty($editing_variants)): ?>
                                <?php foreach ($editing_variants as $index => $variant): ?>
                                    <div class="variant-row" data-index="<?php echo $index; ?>">
                                        <input type="text" name="variants[<?php echo $index; ?>][size]" class="form-control" placeholder="Size" value="<?php echo htmlspecialchars($variant['size']); ?>">
                                        <div class="color-input-group">
                                            <input type="text" name="variants[<?php echo $index; ?>][color]" class="form-control" placeholder="Color" value="<?php echo htmlspecialchars($variant['color']); ?>" onchange="updateColorPreview(<?php echo $index; ?>)">
                                            <input type="color" name="variants[<?php echo $index; ?>][color_hex]" class="color-preview" value="<?php echo htmlspecialchars($variant['color_hex']); ?>" onchange="updateColorName(<?php echo $index; ?>)">
                                        </div>
                                        <input type="number" name="variants[<?php echo $index; ?>][stock]" class="form-control" placeholder="Stock" min="0" value="<?php echo $variant['stock_quantity']; ?>">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeVariant(<?php echo $index; ?>)">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary" onclick="addVariant()">+ Add Variant</button>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo ($editing_product && $editing_product['is_active']) || !$editing_product ? 'checked' : ''; ?>>
                            Active
                        </label>
                        <label>
                            <input type="checkbox" name="is_featured" <?php echo $editing_product && $editing_product['is_featured'] ? 'checked' : ''; ?>>
                            Featured
                        </label>
                        <label>
                            <input type="checkbox" name="is_new" <?php echo $editing_product && $editing_product['is_new'] ? 'checked' : ''; ?>>
                            New Arrival
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editing_product ? 'Update Product' : 'Add Product'; ?>
                        </button>
                        <?php if ($editing_product): ?>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0;">All Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Brand</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" alt="Product">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #999;">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <?php if ($product['is_featured']): ?><span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 4px;">Featured</span><?php endif; ?>
                                            <?php if ($product['is_new']): ?><span style="background: #28a745; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 4px;">New</span><?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($product['sku']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>
                                        <div><?php echo formatPrice($product['price']); ?></div>
                                        <?php if ($product['original_price'] > $product['price']): ?>
                                            <div style="text-decoration: line-through; color: #999; font-size: 0.8rem;"><?php echo formatPrice($product['original_price']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['brand']); ?></td>
                                    <td>
                                        <?php 
                                        // Get total stock from variants
                                        $stmt = $pdo->prepare("SELECT SUM(stock_quantity) as total_stock FROM product_variants WHERE product_id = :product_id");
                                        $stmt->bindParam(':product_id', $product['id']);
                                        $stmt->execute();
                                        $stock_result = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $total_stock = $stock_result['total_stock'] ?: $product['stock_quantity'];
                                        
                                        if ($total_stock == 0): ?>
                                            <span style="color: #dc3545; font-weight: bold;">Out of Stock</span>
                                        <?php elseif ($total_stock < 10): ?>
                                            <span style="color: #ffc107; font-weight: bold;"><?php echo $total_stock; ?></span>
                                        <?php else: ?>
                                            <?php echo $total_stock; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $product['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        let variantIndex = <?php echo $editing_product ? count($editing_variants) : 0; ?>;
        
        function addVariant() {
            const container = document.getElementById('variantsContainer');
            const variantRow = document.createElement('div');
            variantRow.className = 'variant-row';
            variantRow.setAttribute('data-index', variantIndex);
            
            variantRow.innerHTML = `
                <input type="text" name="variants[${variantIndex}][size]" class="form-control" placeholder="Size">
                <div class="color-input-group">
                    <input type="text" name="variants[${variantIndex}][color]" class="form-control" placeholder="Color" onchange="updateColorPreview(${variantIndex})">
                    <input type="color" name="variants[${variantIndex}][color_hex]" class="color-preview" value="#000000" onchange="updateColorName(${variantIndex})">
                </div>
                <input type="number" name="variants[${variantIndex}][stock]" class="form-control" placeholder="Stock" min="0">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeVariant(${variantIndex})">Remove</button>
            `;
            
            container.appendChild(variantRow);
            variantIndex++;
        }
        
        function removeVariant(index) {
            const variantRow = document.querySelector(`[data-index="${index}"]`);
            if (variantRow) {
                variantRow.remove();
            }
        }
        
        function updateColorPreview(index) {
            const colorInput = document.querySelector(`input[name="variants[${index}][color]"]`);
            const colorHexInput = document.querySelector(`input[name="variants[${index}][color_hex]"]`);
            if (colorInput && colorHexInput) {
                const colorName = colorInput.value.toLowerCase();
                // Simple color name to hex mapping
                const colorMap = {
                    'red': '#FF0000', 'blue': '#0000FF', 'green': '#008000',
                    'black': '#000000', 'white': '#FFFFFF', 'gray': '#808080',
                    'brown': '#8B4513', 'pink': '#FFC0CB', 'yellow': '#FFFF00',
                    'purple': '#800080', 'orange': '#FFA500', 'navy': '#000080',
                    'burgundy': '#800020', 'emerald': '#50C878', 'khaki': '#C3B091',
                    'tan': '#D2B48C', 'sky blue': '#87CEEB'
                };
                
                if (colorMap[colorName]) {
                    colorHexInput.value = colorMap[colorName];
                }
            }
        }
        
        function updateColorName(index) {
            const colorInput = document.querySelector(`input[name="variants[${index}][color]"]`);
            const colorHexInput = document.querySelector(`input[name="variants[${index}][color_hex]"]`);
            if (colorInput && colorHexInput && !colorInput.value) {
                // Simple hex to color name mapping
                const hexMap = {
                    '#FF0000': 'Red', '#0000FF': 'Blue', '#008000': 'Green',
                    '#000000': 'Black', '#FFFFFF': 'White', '#808080': 'Gray',
                    '#8B4513': 'Brown', '#FFC0CB': 'Pink', '#FFFF00': 'Yellow',
                    '#800080': 'Purple', '#FFA500': 'Orange', '#000080': 'Navy',
                    '#800020': 'Burgundy', '#50C878': 'Emerald', '#C3B091': 'Khaki',
                    '#D2B48C': 'Tan', '#87CEEB': 'Sky Blue'
                };
                
                const hex = colorHexInput.value.toUpperCase();
                if (hexMap[hex]) {
                    colorInput.value = hexMap[hex];
                }
            }
        }
        
        function previewImage(url) {
            const preview = document.getElementById('imagePreview');
            if (url) {
                preview.innerHTML = `<img src="${url}" style="max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 4px;" alt="Product preview" onerror="this.parentElement.style.display='none'">`;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Auto-generate slug from product name
        document.getElementById('name').addEventListener('input', function() {
            const slugInput = document.getElementById('slug');
            if (!slugInput.value) {
                const slug = this.value.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                slugInput.value = slug;
            }
        });
        
        // Auto-generate SKU from brand and category
        document.getElementById('category_id').addEventListener('change', function() {
            const skuInput = document.getElementById('sku');
            const brandInput = document.getElementById('brand');
            const categorySelect = this;
            
            if (!skuInput.value && brandInput.value && categorySelect.value) {
                const categoryText = categorySelect.options[categorySelect.selectedIndex].text.toUpperCase().substring(0, 3);
                const brandText = brandInput.value.toUpperCase().substring(0, 2);
                const randomNum = Math.floor(Math.random() * 900) + 100;
                skuInput.value = `${brandText}-${categoryText}-${randomNum}`;
            }
        });
    </script>
</body>
</html>
