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
            case 'toggle_status':
                $id = intval($_POST['id']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role != 'admin'");
                    if ($stmt->execute([$is_active, $id])) {
                        $message = 'User status updated successfully!';
                    } else {
                        $error = 'Failed to update user status.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                try {
                    // Don't allow deleting admin users
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                    
                    if ($user && $user['role'] === 'admin') {
                        $error = 'Cannot delete admin users.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                        if ($stmt->execute([$id])) {
                            $message = 'User deleted successfully!';
                        } else {
                            $error = 'Failed to delete user.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
                
            case 'reset_password':
                $id = intval($_POST['id']);
                $new_password = password_hash('password123', PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$new_password, $id])) {
                        $message = 'Password reset successfully! New password: password123';
                    } else {
                        $error = 'Failed to reset password.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get users with statistics
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
               (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent
        FROM users u 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get user statistics
$stats = [];
try {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_active = 1")->fetchColumn();
    $stats['new_users_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) = DATE('now')")->fetchColumn();
    $stats['new_users_month'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) >= DATE('now', '-30 days')")->fetchColumn();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - LUXE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-wrap { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .admin-side { background: #1a1a1a; color: #fff; padding: 2rem 1.5rem; }
        .admin-side h2 { font-size: 1.3rem; margin-bottom: 2rem; color: var(--accent); }
        .admin-side a { display: block; padding: .75rem 1rem; color: rgba(255,255,255,.7); border-radius: 6px; margin-bottom: .25rem; font-size: .9rem; transition: all .2s; }
        .admin-side a:hover, .admin-side a.active { background: rgba(255,255,255,.1); color: #fff; }
        .admin-main { padding: 2rem; background: var(--bg-alt); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow); }
        .stat-card__num { font-size: 2rem; font-weight: 700; color: var(--accent); margin-bottom: .25rem; }
        .stat-card__label { color: var(--text-light); font-size: .85rem; }
        .admin-table { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .admin-table table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .admin-table th { background: var(--primary); color: #fff; }
        .table-actions { display: flex; gap: .5rem; }
        .status-badge { padding: .3rem .8rem; border-radius: 20px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .role-badge { padding: .2rem .6rem; border-radius: 12px; font-size: .7rem; font-weight: 600; }
        .role-admin { background: #fef3c7; color: #92400e; }
        .role-customer { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <nav class="admin-side">
            <h2>LUXE Admin</h2>
            <a href="index.php">📊 Dashboard</a>
            <a href="products.php">📦 Products</a>
            <a href="categories.php">🏷️ Categories</a>
            <a href="orders.php">📋 Orders</a>
            <a href="users.php" class="active">👥 Users</a>
            <a href="analytics.php">📈 Analytics</a>
            <a href="../index.php">🌐 View Site</a>
            <a href="../logout.php">↪ Logout</a>
        </nav>
        <main class="admin-main">
            <?php if ($message): ?><div class="alert alert--success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert--error"><?php echo $error; ?></div><?php endif; ?>

            <h2 style="margin-bottom: 1.5rem;">User Management</h2>
            
            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__num"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-card__label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__num"><?php echo $stats['active_users']; ?></div>
                    <div class="stat-card__label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__num"><?php echo $stats['new_users_today']; ?></div>
                    <div class="stat-card__label">New Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__num"><?php echo $stats['new_users_month']; ?></div>
                    <div class="stat-card__label">New This Month</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0;">All Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem;">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                            <small style="color: var(--text-light);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['phone'] ?: '—'; ?></td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo $user['order_count']; ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo formatPrice($user['total_spent']); ?></span>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle user status?');">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn--sm btn--outline">
                                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password to: password123?');">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn--sm btn--outline">Reset Pass</button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user permanently?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn--sm btn--outline" style="color: #dc3545; border-color: #dc3545;">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: var(--text-light); font-size: .85rem;">Admin</span>
                                            <?php endif; ?>
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
