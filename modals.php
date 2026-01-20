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

                <form id="loginForm" method="post">
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

                <!-- Signup Form -->
                <div id="signupFormContainer">
                    <form id="signupForm" method="POST">
                        <input type="hidden" name="action" value="signup">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label fw-bold">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Enter first name" required style="border-radius: 0 5px 5px 0;" oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')" pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed.">
                                </div>
                                <div class="error-message text-danger mt-1" id="firstNameError" style="display: none;">Please enter your first name</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label fw-bold">Last Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Enter last name" required style="border-radius: 0 5px 5px 0;" oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')" pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed.">
                                </div>
                                <div class="error-message text-danger mt-1" id="lastNameError" style="display: none;">Please enter your last name</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Method</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contact_method" id="contactEmail" value="email" checked>
                                <label class="form-check-label" for="contactEmail">
                                    Use Gmail
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contact_method" id="contactPhone" value="phone">
                                <label class="form-check-label" for="contactPhone">
                                    Use Phone Number
                                </label>
                            </div>
                        </div>

                        <div id="emailField">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address" required style="border-radius: 0 5px 5px 0;">
                                </div>
                                <div class="error-message text-danger mt-1" id="emailError" style="display: none;">Please enter a valid email address</div>
                                <div class="text-danger mt-1" id="emailWarning" style="display: none;">this email is already use</div>
                            </div>
                        </div>

                        <div id="phoneField" style="display: none;">
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-bold">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter phone number" style="border-radius: 0 5px 5px 0;">
                                </div>
                                <div class="error-message text-danger mt-1" id="phoneError" style="display: none;">Please enter a valid phone number</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="signupUsername" class="form-label fw-bold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                <input type="text" class="form-control" id="signupUsername" name="username" placeholder="Choose a username" required style="border-radius: 0 5px 5px 0;">
                            </div>
                            <div class="error-message text-danger mt-1" id="signupUsernameError" style="display: none;">Please enter a username</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="signupPassword" class="form-label fw-bold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="signupPassword" name="password" placeholder="Create password" required style="border-radius: 0 5px 5px 0;">
                                </div>
                                <div class="error-message text-danger mt-1" id="signupPasswordError" style="display: none;">Password must be at least 8 characters</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="signupConfirmPassword" class="form-label fw-bold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="signupConfirmPassword" name="confirmPassword" placeholder="Confirm password" required style="border-radius: 0 5px 5px 0;">
                                </div>
                                <div class="error-message text-danger mt-1" id="signupConfirmPasswordError" style="display: none;">Passwords do not match</div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="terms-of-service.html" class="text-primary">Terms of Service</a> and <a href="privacy.html" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2" style="border-radius: 25px; font-weight: bold;">Send Verification Code</button>
                    </form>
                </div>

                <!-- OTP Verification Form -->
                <div id="otpFormContainer" style="display: none;">
                    <div class="text-center mb-4">
                        <h4 style="color: #333; margin-bottom: 10px;">Verify Your Email</h4>
                        <p class="text-muted">We've sent a 6-digit verification code to your email address</p>
                    </div>

                    <form id="otpForm">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="mb-3">
                            <label for="otp" class="form-label fw-bold">Verification Code</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="text" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit code" maxlength="6" required style="border-radius: 0 5px 5px 0; text-align: center; font-size: 18px; letter-spacing: 2px;">
                            </div>
                            <div class="error-message text-danger mt-1" id="otpError" style="display: none;">Please enter the verification code</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3" style="border-radius: 25px; font-weight: bold;">Verify & Create Account</button>

                        <div class="text-center">
                            <button type="button" id="resendOtpBtn" class="btn btn-link text-decoration-none">Didn't receive code? Resend</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function() {
        const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
        const contactEmail = document.getElementById('contactEmail');
        const contactPhone = document.getElementById('contactPhone');
        const emailField = document.getElementById('emailField');
        const phoneField = document.getElementById('phoneField');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const emailWarning = document.getElementById('emailWarning');
        const signupFormContainer = document.getElementById('signupFormContainer');
        const otpFormContainer = document.getElementById('otpFormContainer');

        function toggleContactFields() {
            if (contactEmail.checked) {
                emailField.style.display = 'block';
                phoneField.style.display = 'none';
                emailInput.required = true;
                phoneInput.required = false;
            } else {
                emailField.style.display = 'none';
                phoneField.style.display = 'block';
                emailInput.required = false;
                phoneInput.required = true;
            }
        }

        contactEmail.addEventListener('change', toggleContactFields);
        contactPhone.addEventListener('change', toggleContactFields);

        // Handle signup form submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('signup.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show OTP verification form
                        signupFormContainer.style.display = 'none';
                        otpFormContainer.style.display = 'block';
                        document.getElementById('otp').focus();
                    } else {
                        if (data.message === 'this email is already used') {
                            emailWarning.textContent = data.message;
                            emailWarning.style.display = 'block';
                            emailWarning.style.fontSize = 'small';
                            emailWarning.style.color = 'red';
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        });

        // Handle OTP form submission
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('signup.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        signupModal.hide();
                        // Reset forms
                        document.getElementById('signupForm').reset();
                        document.getElementById('otpForm').reset();
                        signupFormContainer.style.display = 'block';
                        otpFormContainer.style.display = 'none';
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        });

        // Handle resend OTP
        document.getElementById('resendOtpBtn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'resend_otp');

            fetch('signup.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        });

        // Reset modal when closed
        document.getElementById('signupModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('signupForm').reset();
            document.getElementById('otpForm').reset();
            signupFormContainer.style.display = 'block';
            otpFormContainer.style.display = 'none';
            emailWarning.style.display = 'none';
        });
    });
</script>