<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Sign Up - Buyoan National High School</title>
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

  <style>
    .signup-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px;
    }
    .signup-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      padding: 40px;
      width: 100%;
      max-width: 500px;
    }
    .signup-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .signup-header img {
      width: 80px;
      height: auto;
      margin-bottom: 20px;
    }
    .signup-header h2 {
      color: #333;
      font-weight: 600;
      margin-bottom: 10px;
    }
    .signup-header p {
      color: #666;
      font-size: 14px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-control {
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      padding: 12px 15px;
      font-size: 16px;
      transition: border-color 0.3s ease;
    }
    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    .btn-signup {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      border-radius: 8px;
      padding: 12px;
      font-size: 16px;
      font-weight: 600;
      color: white;
      width: 100%;
      transition: transform 0.2s ease;
    }
    .btn-signup:hover {
      transform: translateY(-2px);
      color: white;
    }
    .terms-checkbox {
      display: flex;
      align-items: flex-start;
      margin-bottom: 20px;
    }
    .terms-checkbox input {
      margin-right: 10px;
      margin-top: 2px;
    }
    .terms-checkbox label {
      font-size: 14px;
      color: #666;
      line-height: 1.4;
    }
    .login-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e1e5e9;
    }
    .login-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }
    .error-message {
      color: #dc3545;
      font-size: 14px;
      margin-top: 5px;
      display: none;
    }
    .row {
      --bs-gutter-x: 15px;
    }
  </style>
</head>
<body>
  <div class="signup-container">
    <div class="signup-card">
      <div class="signup-header">
        <img src="assets/img/logo.jpg" alt="School Logo">
        <h2>Admin Registration</h2>
        <p>Create your account to access the school management system</p>
      </div>

      <form id="signupForm">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="firstName" class="form-label">First Name</label>
              <input type="text" class="form-control" id="firstName" name="firstName" required>
              <div class="error-message" id="firstNameError">Please enter your first name</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="lastName" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="lastName" name="lastName" required>
              <div class="error-message" id="lastNameError">Please enter your last name</div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" name="email" required>
          <div class="error-message" id="emailError">Please enter a valid email address</div>
        </div>

        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" required>
          <div class="error-message" id="usernameError">Please enter a username</div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
          <div class="error-message" id="passwordError">Password must be at least 8 characters</div>
        </div>

        <div class="form-group">
          <label for="confirmPassword" class="form-label">Confirm Password</label>
          <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
          <div class="error-message" id="confirmPasswordError">Passwords do not match</div>
        </div>

        <div class="terms-checkbox">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">I agree to the <a href="#" style="color: #667eea;">Terms of Service</a> and <a href="#" style="color: #667eea;">Privacy Policy</a></label>
        </div>

        <button type="submit" class="btn btn-signup">Create Account</button>
      </form>

      <div class="login-link">
        <p>Already have an account? <a href="login.html">Sign in here</a></p>
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
    document.getElementById('signupForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const firstName = document.getElementById('firstName').value;
      const lastName = document.getElementById('lastName').value;
      const email = document.getElementById('email').value;
      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const terms = document.getElementById('terms').checked;

      let isValid = true;

      // Reset error messages
      document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');

      // Validation
      if (!firstName.trim()) {
        document.getElementById('firstNameError').style.display = 'block';
        isValid = false;
      }

      if (!lastName.trim()) {
        document.getElementById('lastNameError').style.display = 'block';
        isValid = false;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        document.getElementById('emailError').style.display = 'block';
        isValid = false;
      }

      if (!username.trim()) {
        document.getElementById('usernameError').style.display = 'block';
        isValid = false;
      }

      if (password.length < 8) {
        document.getElementById('passwordError').style.display = 'block';
        isValid = false;
      }

      if (password !== confirmPassword) {
        document.getElementById('confirmPasswordError').style.display = 'block';
        isValid = false;
      }

      if (!terms) {
        alert('Please agree to the Terms of Service and Privacy Policy');
        isValid = false;
      }

      if (isValid) {
        alert('Account created successfully! You can now log in.');
        // In a real application, you would send data to server
        // For demo, redirect to login
        window.location.href = 'login.html';
      }
    });
  </script>
</body>
</html>
