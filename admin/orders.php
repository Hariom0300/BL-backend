<?php
require_once __DIR__ . '/../config_sqlite.php';
if (!isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid = intval($_POST['order_id']);
    $status = sanitize($_POST['status']);
    updateOrderStatus($oid, $status);
    $messages = ['confirmed'=>'Order has been confirmed','shipped'=>'Order has been shipped and is on its way','delivered'=>'Order has been delivered','cancelled'=>'Order has been cancelled'];
    addTrackingEvent($oid, ucfirst($status), $messages[$status] ?? 'Order status updated to ' . $status);
    redirect('orders.php?updated=1');
}

$orders = $pdo->query("SELECT o.*, u.full_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();
$detail = null; $detailItems = []; $detailTracking = [];
if (isset($_GET['id'])) {
    $detail = getOrderById(intval($_GET['id']));
    $detailItems = getOrderItems($detail['id']);
    $detailTracking = getOrderTracking($detail['id']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Orders - LUXE Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
<style>.admin-wrap{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.admin-side{background:#1a1a1a;color:#fff;padding:2rem 1.5rem}.admin-side h2{font-size:1.3rem;margin-bottom:2rem;color:var(--accent)}.admin-side a{display:block;padding:.75rem 1rem;color:rgba(255,255,255,.7);border-radius:6px;margin-bottom:.25rem;font-size:.9rem;transition:all .2s}.admin-side a:hover,.admin-side a.active{background:rgba(255,255,255,.1);color:#fff}.admin-main{padding:2rem;background:var(--bg-alt)}</style></head><body>
<div class="admin-wrap">
    <nav class="admin-side">
        <h2>LUXE Admin</h2>
        <a href="index.php">📊 Dashboard</a>
        <a href="products.php">📦 Products</a>
        <a href="categories.php">🏷️ Categories</a>
        <a href="orders.php" class="active">� Orders</a>
        <a href="users.php">👥 Users</a>
        <a href="analytics.php">📈 Analytics</a>
        <a href="../index.php">🌐 View Site</a>
        <a href="../logout.php">↪ Logout</a>
    </nav>
    <main class="admin-main">
        <?php if (isset($_GET['updated'])): ?><div class="alert alert--success">Order status updated!</div><?php endif; ?>

        <?php if ($detail): ?>
        <h1 style="margin-bottom:1.5rem"><a href="orders.php" style="color:var(--text-light)">← Orders</a> / #<?php echo $detail['order_number']; ?></h1>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
            <div class="account-content">
                <h3 style="margin-bottom:1rem">Order Details</h3>
                <p><strong>Status:</strong> <span class="status-badge status-badge--<?php echo $detail['status']; ?>"><?php echo ucfirst($detail['status']); ?></span></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y g:i A',strtotime($detail['created_at'])); ?></p>
                <p><strong>Payment:</strong> <?php echo ucfirst($detail['payment_method']); ?></p>
                <p><strong>Total:</strong> <?php echo formatPrice($detail['total_amount']); ?></p>
                <hr style="margin:1rem 0">
                <p><strong>Ship to:</strong> <?php echo htmlspecialchars($detail['shipping_name']); ?></p>
                <p><?php echo htmlspecialchars($detail['shipping_address'].', '.$detail['shipping_city'].', '.$detail['shipping_state'].' '.$detail['shipping_zip']); ?></p>
                <hr style="margin:1rem 0">
                <form method="POST" style="display:flex;gap:.5rem;align-items:end">
                    <input type="hidden" name="order_id" value="<?php echo $detail['id']; ?>">
                    <div class="form-group" style="margin:0;flex:1"><label>Update Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $detail['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <button type="submit" name="update_status" class="btn btn--primary btn--sm">Update</button>
                </form>
            </div>
            <div class="account-content">
                <h3 style="margin-bottom:1rem">Items</h3>
                <?php foreach ($detailItems as $item): ?>
                <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.9rem">
                    <span><?php echo htmlspecialchars($item['product_name']); ?> <?php echo $item['size']?'('.$item['size'].')':''; ?> × <?php echo $item['quantity']; ?></span>
                    <span><?php echo formatPrice($item['subtotal']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <h1 style="margin-bottom:1.5rem">Orders (<?php echo count($orders); ?>)</h1>
        <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead><tr style="border-bottom:2px solid var(--border);text-align:left"><th style="padding:1rem">Order</th><th style="padding:1rem">Customer</th><th style="padding:1rem">Total</th><th style="padding:1rem">Payment</th><th style="padding:1rem">Status</th><th style="padding:1rem">Date</th></tr></thead>
                <tbody>
                <?php if (empty($orders)): ?><tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--text-light)">No orders yet</td></tr>
                <?php else: foreach ($orders as $o): ?>
                <tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:1rem"><a href="?id=<?php echo $o['id']; ?>" style="color:var(--accent);font-weight:500">#<?php echo $o['order_number']; ?></a></td>
                    <td style="padding:1rem"><?php echo htmlspecialchars($o['full_name']??'Guest'); ?></td>
                    <td style="padding:1rem;font-weight:600"><?php echo formatPrice($o['total_amount']); ?></td>
                    <td style="padding:1rem"><?php echo ucfirst($o['payment_method']); ?></td>
                    <td style="padding:1rem"><span class="status-badge status-badge--<?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span></td>
                    <td style="padding:1rem"><?php echo date('M j, Y',strtotime($o['created_at'])); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>
</body></html>
