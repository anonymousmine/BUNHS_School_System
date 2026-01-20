<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Login - Buyoan National High School</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"/>
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <img src="assets/img/logo.jpg" alt="School Logo">
        <h2>Admin Login</h2>
        <p>Please sign in to access the school management system</p>
      </div>

      <form id="loginForm">
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" required>
          <div class="error-message" id="usernameError">Please enter a valid username</div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
          <div class="error-message" id="passwordError">Please enter your password</div>
        </div>

        <button type="submit" class="btn btn-login">Sign In</button>
      </form>

      <div class="forgot-password">
        <a href="#">Forgot your password?</a>
      </div>

      <div class="signup-link">
        <p>Don't have an account? <a href="signup.html">Sign up here</a></p>
      </div>
    </div>
  </div>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      let isValid = true;

      // Reset error messages
      document.getElementById('usernameError').style.display = 'none';
      document.getElementById('passwordError').style.display = 'none';

      // Simple validation
      if (!username.trim()) {
        document.getElementById('usernameError').style.display = 'block';
        isValid = false;
      }

      if (!password.trim()) {
        document.getElementById('passwordError').style.display = 'block';
        isValid = false;
      }

      if (isValid) {
        // Check credentials (for demo purposes, accept admin/admin)
        if (username === 'admin' && password === 'admin') {
          alert('Login successful! Redirecting to dashboard...');
          
        } else {
          alert('Invalid credentials. Please try again.');
        }
      }
    });
  </script>
</body>
</html>
