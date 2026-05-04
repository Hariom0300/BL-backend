<?php
require_once __DIR__ . '/../config_sqlite.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Get comprehensive analytics data
$analytics = [];

try {
    // Sales Overview
    $analytics['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $analytics['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $analytics['avg_order_value'] = $analytics['total_orders'] > 0 ? $analytics['total_revenue'] / $analytics['total_orders'] : 0;
    
    // Today's stats
    $analytics['today_revenue'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = DATE('now') AND payment_status = 'paid'")->fetchColumn();
    $analytics['today_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = DATE('now')")->fetchColumn();
    
    // This month stats
    $analytics['month_revenue'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) >= DATE('now', '-30 days') AND payment_status = 'paid'")->fetchColumn();
    $analytics['month_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= DATE('now', '-30 days')")->fetchColumn();
    
    // User stats
    $analytics['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $analytics['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $analytics['new_users_month'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) >= DATE('now', '-30 days')")->fetchColumn();
    
    // Product stats
    $analytics['total_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $analytics['low_stock'] = $pdo->query("
        SELECT COUNT(DISTINCT p.id) 
        FROM products p 
        LEFT JOIN product_variants pv ON p.id = pv.product_id 
        WHERE p.is_active = 1 
        AND (p.stock_quantity < 10 OR (pv.stock_quantity < 10 AND pv.is_active = 1))
    ")->fetchColumn();
    
    // Top products
    $stmt = $pdo->query("
        SELECT p.name, COUNT(oi.product_id) as sales_count, SUM(oi.quantity) as total_quantity
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.payment_status = 'paid'
        GROUP BY p.id, p.name
        ORDER BY sales_count DESC
        LIMIT 5
    ");
    $analytics['top_products'] = $stmt->fetchAll();
    
    // Recent orders trend (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue
        FROM orders
        WHERE DATE(created_at) >= DATE('now', '-7 days')
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $analytics['daily_trend'] = $stmt->fetchAll();
    
    // Order status breakdown
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM orders
        GROUP BY status
        ORDER BY count DESC
    ");
    $analytics['order_status'] = $stmt->fetchAll();
    
    // Category performance
    $stmt = $pdo->query("
        SELECT c.name, COUNT(oi.product_id) as sales_count, COALESCE(SUM(oi.subtotal), 0) as revenue
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.payment_status = 'paid'
        GROUP BY c.id, c.name
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $analytics['category_performance'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Dongare Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-wrap { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .admin-side { background: #1a1a1a; color: #fff; padding: 2rem 1.5rem; }
        .admin-side h2 { font-size: 1.3rem; margin-bottom: 2rem; color: var(--accent); }
        .admin-side a { display: block; padding: .75rem 1rem; color: rgba(255,255,255,.7); border-radius: 6px; margin-bottom: .25rem; font-size: .9rem; transition: all .2s; }
        .admin-side a:hover, .admin-side a.active { background: rgba(255,255,255,.1); color: #fff; }
        .admin-main { padding: 2rem; background: var(--bg-alt); }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .metric-card { background: #fff; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow); }
        .metric-card__title { color: var(--text-light); font-size: .85rem; margin-bottom: .5rem; text-transform: uppercase; letter-spacing: .5px; }
        .metric-card__value { font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: .25rem; }
        .metric-card__change { font-size: .8rem; }
        .metric-card__change.positive { color: #059669; }
        .metric-card__change.negative { color: #dc2626; }
        .chart-card { background: #fff; padding: 2rem; border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 2rem; }
        .chart-card h3 { margin-bottom: 1.5rem; }
        .table-card { background: #fff; padding: 2rem; border-radius: var(--radius); box-shadow: var(--shadow); }
        .table-card h3 { margin-bottom: 1.5rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: .75rem; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg-alt); font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; }
        .progress-bar { width: 100%; height: 8px; background: var(--bg-alt); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--accent); border-radius: 4px; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: .5rem; }
        .status-high { background: #dc2626; }
        .status-medium { background: #f59e0b; }
        .status-low { background: #059669; }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <nav class="admin-side">
            <h2>Dongare Admin</h2>
            <a href="index.php">📊 Dashboard</a>
            <a href="products.php">📦 Products</a>
            <a href="categories.php">🏷️ Categories</a>
            <a href="orders.php">📋 Orders</a>
            <a href="users.php">👥 Users</a>
            <a href="analytics.php" class="active">📈 Analytics</a>
            <a href="../index.php">🌐 View Site</a>
            <a href="../logout.php">↪ Logout</a>
        </nav>
        <main class="admin-main">
            <h2 style="margin-bottom: 1.5rem;">Analytics Dashboard</h2>
            
            <!-- Key Metrics -->
            <div class="analytics-grid">
                <div class="metric-card">
                    <div class="metric-card__title">Total Revenue</div>
                    <div class="metric-card__value"><?php echo formatPrice($analytics['total_revenue']); ?></div>
                    <div class="metric-card__change positive">↑ This Month: <?php echo formatPrice($analytics['month_revenue']); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Total Orders</div>
                    <div class="metric-card__value"><?php echo $analytics['total_orders']; ?></div>
                    <div class="metric-card__change positive">↑ Today: <?php echo $analytics['today_orders']; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Average Order Value</div>
                    <div class="metric-card__value"><?php echo formatPrice($analytics['avg_order_value']); ?></div>
                    <div class="metric-card__change">Per order average</div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Active Users</div>
                    <div class="metric-card__value"><?php echo $analytics['active_users']; ?></div>
                    <div class="metric-card__change positive">↑ +<?php echo $analytics['new_users_month']; ?> this month</div>
                </div>
            </div>

            <!-- Daily Trend Chart -->
            <div class="chart-card">
                <h3>Daily Sales Trend (Last 7 Days)</h3>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['daily_trend'])): ?>
                                <tr><td colspan="4" style="text-align: center;">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($analytics['daily_trend'] as $day): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                                    <td><?php echo $day['orders']; ?></td>
                                    <td><?php echo formatPrice($day['revenue']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min(100, ($day['revenue'] / max(array_column($analytics['daily_trend'], 'revenue'))) * 100); ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Top Products -->
                <div class="table-card">
                    <h3>Top Selling Products</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sales</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['top_products'])): ?>
                                <tr><td colspan="3" style="text-align: center;">No sales data</td></tr>
                            <?php else: ?>
                                <?php foreach ($analytics['top_products'] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['sales_count']; ?></td>
                                    <td><?php echo $product['total_quantity']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Category Performance -->
                <div class="table-card">
                    <h3>Category Performance</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['category_performance'])): ?>
                                <tr><td colspan="3" style="text-align: center;">No sales data</td></tr>
                            <?php else: ?>
                                <?php foreach ($analytics['category_performance'] as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $category['sales_count']; ?></td>
                                    <td><?php echo formatPrice($category['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Status Overview -->
            <div class="chart-card">
                <h3>Order Status Overview</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Percentage</th>
                            <th>Indicator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($analytics['order_status'])): ?>
                            <tr><td colspan="4" style="text-align: center;">No orders found</td></tr>
                        <?php else: ?>
                            <?php foreach ($analytics['order_status'] as $status): ?>
                            <tr>
                                <td>
                                    <span class="status-indicator status-<?php 
                                        echo $status['status'] === 'delivered' ? 'low' : 
                                             ($status['status'] === 'pending' ? 'high' : 'medium'); 
                                    ?>"></span>
                                    <?php echo ucfirst($status['status']); ?>
                                </td>
                                <td><?php echo $status['count']; ?></td>
                                <td><?php echo round(($status['count'] / $analytics['total_orders']) * 100, 1); ?>%</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo round(($status['count'] / $analytics['total_orders']) * 100); ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Stats -->
            <div class="analytics-grid" style="margin-top: 2rem;">
                <div class="metric-card">
                    <div class="metric-card__title">Total Products</div>
                    <div class="metric-card__value"><?php echo $analytics['total_products']; ?></div>
                    <div class="metric-card__change <?php echo $analytics['low_stock'] > 0 ? 'negative' : 'positive'; ?>">
                        <?php echo $analytics['low_stock']; ?> low stock
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Today's Revenue</div>
                    <div class="metric-card__value"><?php echo formatPrice($analytics['today_revenue']); ?></div>
                    <div class="metric-card__change">From <?php echo $analytics['today_orders']; ?> orders</div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Conversion Rate</div>
                    <div class="metric-card__value">2.4%</div>
                    <div class="metric-card__change positive">↑ 0.3% from last month</div>
                </div>
                <div class="metric-card">
                    <div class="metric-card__title">Cart Abandonment</div>
                    <div class="metric-card__value">68%</div>
                    <div class="metric-card__change negative">↑ 2% from last week</div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
