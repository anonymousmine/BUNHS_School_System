<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db_connection.php';

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username === '' || $password === '') {
        $login_error = 'Please enter username and password.';
    } else {
        // Check admin table first
        $admin = null;
        $stmt = $conn->prepare("SELECT id, password_hash, school_email FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // If not found in admin, check sub_admin
        if (!$admin) {
            $stmt = $conn->prepare("SELECT id, password_hash, email FROM sub_admin WHERE username = ? AND status = 'approved' LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Password correct - set session
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = isset($admin['email']) ? 'sub-admin' : 'admin';
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Redirect to admin dashboard
            header('Location: admin_account/admin_dashboard.php');
            exit;
        } else {
            $login_error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Admin Login Test</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="test_login.php">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
