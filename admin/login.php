<?php
require_once __DIR__ . '/../config_sqlite.php';

// Redirect if already logged in as admin
if (isLoggedIn() && $_SESSION['role'] === 'admin') {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            redirect('index.php');
        } else {
            $error = 'Invalid admin credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dongare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-login {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-login-card {
            background: #fff;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        .admin-login-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
            text-align: center;
        }
        .admin-login-card p {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 2rem;
        }
        .admin-badge {
            background: var(--accent);
            color: #fff;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            text-align: center;
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-login">
        <div class="admin-login-card">
            <span class="admin-badge">Administrator Access</span>
            <h2>Dongare Admin</h2>
            <p>Sign in to manage your store</p>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username" class="form-control" required 
                           placeholder="Enter admin credentials" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter password">
                </div>
                <button type="submit" class="btn btn--primary btn--full">Sign In</button>
            </form>
            
            <div style="margin-top: 2rem; padding: 1rem; background: var(--bg-alt); border-radius: var(--radius); font-size: 0.85rem;">
                <strong>Demo Credentials:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>password</code>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="../index.php" style="color: var(--text-light); font-size: 0.85rem;">← Back to Store</a>
            </div>
        </div>
    </div>
</body>
</html>
