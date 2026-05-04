<?php
require_once __DIR__ . '/../config_sqlite.php';
if (!isAdmin()) redirect('../login.php');
$stats = getAdminStats();
$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin - Dongare</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
<style>.admin-wrap{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.admin-side{background:#1a1a1a;color:#fff;padding:2rem 1.5rem}.admin-side h2{font-size:1.3rem;margin-bottom:2rem;color:var(--accent)}.admin-side a{display:block;padding:.75rem 1rem;color:rgba(255,255,255,.7);border-radius:6px;margin-bottom:.25rem;font-size:.9rem;transition:all .2s}.admin-side a:hover,.admin-side a.active{background:rgba(255,255,255,.1);color:#fff}.admin-main{padding:2rem;background:var(--bg-alt)}.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}.stat-card{background:#fff;padding:1.5rem;border-radius:var(--radius);box-shadow:var(--shadow)}.stat-card__num{font-size:2rem;font-weight:700;color:var(--accent)}.stat-card__label{color:var(--text-light);font-size:.85rem}</style></head><body>
<div class="admin-wrap">
    <nav class="admin-side">
        <h2>Dongare Admin</h2>
        <a href="index.php">📊 Dashboard</a>
        <a href="products.php">📦 Products</a>
        <a href="categories.php">🏷️ Categories</a>
        <a href="orders.php">� Orders</a>
        <a href="users.php">👥 Users</a>
        <a href="analytics.php">📈 Analytics</a>
        <a href="../index.php">🌐 View Site</a>
        <a href="../logout.php">↪ Logout</a>
    </nav>
    <main class="admin-main">
        <h1 style="margin-bottom:2rem">Dashboard</h1>
        <div class="stat-grid">
            <div class="stat-card"><div class="stat-card__num"><?php echo $stats['total_orders']; ?></div><div class="stat-card__label">Total Orders</div></div>
            <div class="stat-card"><div class="stat-card__num"><?php echo formatPrice($stats['total_revenue']); ?></div><div class="stat-card__label">Revenue</div></div>
            <div class="stat-card"><div class="stat-card__num"><?php echo $stats['total_customers']; ?></div><div class="stat-card__label">Customers</div></div>
            <div class="stat-card"><div class="stat-card__num"><?php echo $stats['total_products']; ?></div><div class="stat-card__label">Products</div></div>
            <div class="stat-card"><div class="stat-card__num"><?php echo $stats['pending_orders']; ?></div><div class="stat-card__label">Pending Orders</div></div>
        </div>
        <div style="background:#fff;padding:2rem;border-radius:var(--radius);box-shadow:var(--shadow)">
            <h3 style="margin-bottom:1rem">Recent Orders</h3>
            <?php if (empty($stats['recent_orders'])): ?><p style="color:var(--text-light)">No orders yet.</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead><tr style="border-bottom:2px solid var(--border);text-align:left"><th style="padding:.75rem">Order</th><th style="padding:.75rem">Customer</th><th style="padding:.75rem">Total</th><th style="padding:.75rem">Status</th><th style="padding:.75rem">Date</th></tr></thead>
                <tbody><?php foreach ($stats['recent_orders'] as $o): ?>
                <tr style="border-bottom:1px solid var(--border)"><td style="padding:.75rem"><a href="orders.php?id=<?php echo $o['id']; ?>" style="color:var(--accent)">#<?php echo $o['order_number']; ?></a></td><td style="padding:.75rem"><?php echo htmlspecialchars($o['full_name']??'Guest'); ?></td><td style="padding:.75rem"><?php echo formatPrice($o['total_amount']); ?></td><td style="padding:.75rem"><span class="status-badge status-badge--<?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span></td><td style="padding:.75rem"><?php echo date('M j',strtotime($o['created_at'])); ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body></html>
