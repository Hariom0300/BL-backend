<?php
require_once __DIR__ . '/../config_sqlite.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $sort_order = intval($_POST['sort_order']);
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)");
                        if ($stmt->execute([$name, $description, $sort_order])) {
                            $message = 'Category added successfully!';
                        } else {
                            $error = 'Failed to add category.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $sort_order = intval($_POST['sort_order']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?");
                        if ($stmt->execute([$name, $description, $sort_order, $is_active, $id])) {
                            $message = 'Category updated successfully!';
                        } else {
                            $error = 'Failed to update category.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                try {
                    // Check if category has products
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                    $stmt->execute([$id]);
                    $count = $stmt->fetch()['count'];
                    
                    if ($count > 0) {
                        $error = 'Cannot delete category with existing products.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = 'Category deleted successfully!';
                        } else {
                            $error = 'Failed to delete category.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY c.sort_order, c.name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get category for editing
$editing_category = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $editing_category = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Categories Management - LUXE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-wrap { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .admin-side { background: #1a1a1a; color: #fff; padding: 2rem 1.5rem; }
        .admin-side h2 { font-size: 1.3rem; margin-bottom: 2rem; color: var(--accent); }
        .admin-side a { display: block; padding: .75rem 1rem; color: rgba(255,255,255,.7); border-radius: 6px; margin-bottom: .25rem; font-size: .9rem; transition: all .2s; }
        .admin-side a:hover, .admin-side a.active { background: rgba(255,255,255,.1); color: #fff; }
        .admin-main { padding: 2rem; background: var(--bg-alt); }
        .admin-content { background: #fff; padding: 2rem; border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 2rem; }
        .admin-table { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .admin-table table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .admin-table th { background: var(--primary); color: #fff; }
        .table-actions { display: flex; gap: .5rem; }
        .status-badge { padding: .3rem .8rem; border-radius: 20px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <nav class="admin-side">
            <h2>LUXE Admin</h2>
            <a href="index.php">📊 Dashboard</a>
            <a href="products.php">📦 Products</a>
            <a href="categories.php" class="active">🏷️ Categories</a>
            <a href="orders.php">📋 Orders</a>
            <a href="users.php">👥 Users</a>
            <a href="analytics.php">📈 Analytics</a>
            <a href="../index.php">🌐 View Site</a>
            <a href="../logout.php">↪ Logout</a>
        </nav>
        <main class="admin-main">
            <?php if ($message): ?><div class="alert alert--success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert--error"><?php echo $error; ?></div><?php endif; ?>

            <div class="admin-content">
                <h2 style="margin-bottom: 1.5rem;"><?php echo $editing_category ? 'Edit Category' : 'Add New Category'; ?></h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $editing_category ? 'edit' : 'add'; ?>">
                    <?php if ($editing_category): ?>
                        <input type="hidden" name="id" value="<?php echo $editing_category['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category Name *</label>
                            <input type="text" name="name" class="form-control" required 
                                   value="<?php echo $editing_category ? htmlspecialchars($editing_category['name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" 
                                   value="<?php echo $editing_category ? $editing_category['sort_order'] : 0; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo $editing_category ? htmlspecialchars($editing_category['description']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($editing_category): ?>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" <?php echo $editing_category['is_active'] ? 'checked' : ''; ?>>
                                Category Active
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn--primary">
                            <?php echo $editing_category ? 'Update Category' : 'Add Category'; ?>
                        </button>
                        <?php if ($editing_category): ?>
                            <a href="categories.php" class="btn btn--outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0;">All Categories</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">No categories found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?></td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo $category['product_count']; ?></span>
                                        <?php if ($category['product_count'] > 0): ?>
                                            <small style="color: var(--text-light);">products</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $category['sort_order']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?php echo $category['id']; ?>" class="btn btn--sm btn--outline">Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn--sm btn--outline" style="color: #dc3545; border-color: #dc3545;">Delete</button>
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
</body>
</html>
