<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="loginModalLabel">Admin Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 2rem;">
        <div class="text-center mb-4">
          <img src="assets/img/logo.jpg" alt="School Logo" style="width: 80px; height: auto; margin-bottom: 20px; border-radius: 50%; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
          <h4 style="color: #333; margin-bottom: 10px;">Welcome Back</h4>
          <p class="text-muted">Login in to access the school management system</p>
        </div>

        <form id="loginForm">
          <div class="mb-3 position-relative">
            <label for="username" class="form-label fw-bold">Username</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required style="border-radius: 0 5px 5px 0;">
            </div>
            <div class="error-message text-danger mt-1" id="usernameError" style="display: none;">Please enter a valid username</div>
          </div>

          <div class="mb-3 position-relative">
            <label for="password" class="form-label fw-bold">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required style="border-radius: 0 5px 5px 0;">
            </div>
            <div class="error-message text-danger mt-1" id="passwordError" style="display: none;">Please enter your password</div>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2" style="border-radius: 25px; font-weight: bold;">Sign In</button>
        </form>

        <div class="text-center mt-3">
          <a href="#" class="text-decoration-none text-primary">Forgot your password?</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="signupModalLabel">Admin Registration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 2rem;">
        <div class="text-center mb-4">
          <img src="assets/img/logo.jpg" alt="School Logo" style="width: 80px; height: auto; margin-bottom: 20px; border-radius: 50%; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
          <h4 style="color: #333; margin-bottom: 10px;">Join Our Community</h4>
          <p class="text-muted">Create your account to access the school management system</p>
        </div>

        <form id="signupForm">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="firstName" class="form-label fw-bold">First Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Enter first name" required style="border-radius: 0 5px 5px 0;">
              </div>
              <div class="error-message text-danger mt-1" id="firstNameError" style="display: none;">Please enter your first name</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="lastName" class="form-label fw-bold">Last Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Enter last name" required style="border-radius: 0 5px 5px 0;">
              </div>
              <div class="error-message text-danger mt-1" id="lastNameError" style="display: none;">Please enter your last name</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-bold">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address" required style="border-radius: 0 5px 5px 0;">
            </div>
            <div class="error-message text-danger mt-1" id="emailError" style="display: none;">Please enter a valid email address</div>
          </div>

          <div class="mb-3">
            <label for="username" class="form-label fw-bold">Username</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
              <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required style="border-radius: 0 5px 5px 0;">
            </div>
            <div class="error-message text-danger mt-1" id="usernameError" style="display: none;">Please enter a username</div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="password" class="form-label fw-bold">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required style="border-radius: 0 5px 5px 0;">
              </div>
              <div class="error-message text-danger mt-1" id="passwordError" style="display: none;">Password must be at least 8 characters</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="confirmPassword" class="form-label fw-bold">Confirm Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required style="border-radius: 0 5px 5px 0;">
              </div>
              <div class="error-message text-danger mt-1" id="confirmPasswordError" style="display: none;">Passwords do not match</div>
            </div>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
            <label class="form-check-label" for="terms">
              I agree to the <a href="terms-of-service.html" class="text-primary">Terms of Service</a> and <a href="privacy.html" class="text-primary">Privacy Policy</a>
            </label>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2" style="border-radius: 25px; font-weight: bold;">Create Account</button>
        </form>
      </div>
    </div>
  </div>
</div>
