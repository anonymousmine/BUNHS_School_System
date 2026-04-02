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
        // First, let's check what password column exists
        $password_col = 'password'; // Default to 'password' column
        
        // Try to find the correct password column
        $result = $conn->query("SHOW COLUMNS FROM admin LIKE '%password%'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $password_col = $row['Field'];
        }
        
        // Check admin table first
        $admin = null;
        $stmt = $conn->prepare("SELECT id, password, school_email FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // If not found in admin, check sub_admin
        if (!$admin) {
            // Check sub_admin password column
            $result = $conn->query("SHOW COLUMNS FROM sub_admin LIKE '%password%'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $password_col = $row['Field'];
            }
            
            $stmt = $conn->prepare("SELECT id, password, email FROM sub_admin WHERE username = ? AND status = 'approved' LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        if ($admin && password_verify($password, $admin['password'])) {
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
            
            // Debug info
            if ($admin) {
                $login_error .= ' (User found but password incorrect)';
            } else {
                $login_error .= ' (User not found)';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login (Fixed)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Admin Login Test (Fixed)</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="test_login_fixed.php">
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
                        
                        <hr>
                        <p class="text-muted">
                            <small>
                                Test with: <br>
                                Username: <code>Admin_SchoolHead_BUNHS</code><br>
                                Password: <code>BUNHS_Admin_DEPED_buyoan</code>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
