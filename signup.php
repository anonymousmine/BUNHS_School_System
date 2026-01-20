<?php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// Include database connection
include 'db_connection.php';

// Function to sanitize input
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to log signup attempts
function logSignupAttempt($username, $email, $success)
{
    $logEntry = sprintf(
        "[%s] Email: %s | Username: %s | Success: %s\n",
        date('Y-m-d H:i:s'),
        htmlspecialchars($email),
        htmlspecialchars($username),
        $success ? 'Yes' : 'No'
    );
    file_put_contents('logs/signup_attempts.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to send verification email
function sendVerificationEmail($email, $code)
{
    $subject = 'Your Verification Code - Buyoan National High School';
    $message = "Hello,\n\nYour verification code is: $code\n\nPlease enter this code to complete your signup.\n\nBest regards,\nBuyoan National High School";
    $headers = 'From: noreply@buyoannationalhighschool.com' . "\r\n" .
        'Reply-To: noreply@buyoannationalhighschool.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    mail($email, $subject, $message, $headers);
}

// Function to generate OTP
function generateOTP()
{
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

$signup_error = '';
$signup_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'signup';

    if ($action === 'signup') {
        $firstName = sanitizeInput($_POST['firstName'] ?? '');
        $lastName = sanitizeInput($_POST['lastName'] ?? '');
        $contact_method = $_POST['contact_method'] ?? 'email';
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $terms = isset($_POST['terms']);

        // Validation
        if (empty($firstName) || empty($lastName) || empty($username) || empty($password) || empty($confirmPassword)) {
            $signup_error = 'Please fill in all required fields.';
        } elseif (!preg_match('/^[A-Za-z\s]+$/', $firstName) || !preg_match('/^[A-Za-z\s]+$/', $lastName)) {
            $signup_error = 'Names can only contain letters and spaces.';
        } elseif ($contact_method === 'email' && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $signup_error = 'Please enter a valid email address.';
        } elseif ($contact_method === 'phone' && empty($phone)) {
            $signup_error = 'Please enter a phone number.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $signup_error = 'Username must be between 3 and 50 characters.';
        } elseif (strlen($password) < 8) {
            $signup_error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $signup_error = 'Passwords do not match.';
        } elseif (!$terms) {
            $signup_error = 'You must agree to the Terms of Service and Privacy Policy.';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $signup_error = 'Username already exists.';
                $stmt->close();
            } else {
                $stmt->close();

                // Check if email or phone already exists
                if ($contact_method === 'email') {
                    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE email = ?");
                    $stmt->bind_param("s", $email);
                } else {
                    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE phone = ?");
                    $stmt->bind_param("s", $phone);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $signup_error = ($contact_method === 'email') ? 'this email is already used' : 'Phone number already exists.';
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Store signup data in session without OTP yet
                    $_SESSION['signup_data'] = [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'contact_method' => $contact_method,
                        'email' => $email,
                        'phone' => $phone,
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT)
                    ];

                    $signup_success = 'Signup successful. Please verify your email.';
                    logSignupAttempt($username, $email ?: $phone, true);

                    // Redirect to verification page
                    header('Location: index.php?verify=true');
                    exit();
                }
            }
        }

        if (!empty($signup_error)) {
            logSignupAttempt($username, $email ?: $phone, false);
        }
    } elseif ($action === 'verify_otp') {
        $otp_input = $_POST['otp'] ?? '';

        if (empty($_SESSION['signup_data'])) {
            $signup_error = 'Session expired. Please start registration again.';
        } elseif (empty($otp_input)) {
            $signup_error = 'Please enter the OTP.';
        } elseif ($_SESSION['signup_data']['otp_expires'] < time()) {
            $signup_error = 'OTP has expired. Please request a new one.';
            unset($_SESSION['signup_data']);
        } elseif ($otp_input !== $_SESSION['signup_data']['otp']) {
            $signup_error = 'Invalid OTP. Please try again.';
        } else {
            // OTP verified, create account
            $data = $_SESSION['signup_data'];
            $full_name = $data['firstName'] . ' ' . $data['lastName'];
            $status = 'pending';

            if ($data['contact_method'] === 'email') {
                $stmt = $conn->prepare("INSERT INTO `sub_admin` (username, password, email, full_name, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $data['username'], $data['password'], $data['email'], $full_name, $status);
            } else {
                $stmt = $conn->prepare("INSERT INTO `sub_admin` (username, password, phone, full_name, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $data['username'], $data['password'], $data['phone'], $full_name, $status);
            }

            if ($stmt->execute()) {
                $signup_success = 'Account created successfully! Please wait for admin approval.';
                unset($_SESSION['signup_data']);
                logSignupAttempt($data['username'], $data['email'] ?: $data['phone'], true);
            } else {
                $signup_error = 'Failed to create account: ' . $stmt->error;
                logSignupAttempt($data['username'], $data['email'] ?: $data['phone'], false);
            }
            $stmt->close();
        }
    } elseif ($action === 'resend_otp') {
        if (empty($_SESSION['signup_data'])) {
            $signup_error = 'Session expired. Please start registration again.';
        } else {
            $otp = generateOTP();
            $_SESSION['signup_data']['otp'] = $otp;
            $_SESSION['signup_data']['otp_expires'] = time() + 300;

            sendVerificationEmail($_SESSION['signup_data']['email'], $otp);
            $signup_success = 'New OTP sent to your email.';
        }
    } elseif ($action === 'send_otp') {
        if (empty($_SESSION['signup_data'])) {
            $signup_error = 'Session expired. Please start registration again.';
        } else {
            $otp = generateOTP();
            $_SESSION['signup_data']['otp'] = $otp;
            $_SESSION['signup_data']['otp_expires'] = time() + 300;

            sendVerificationEmail($_SESSION['signup_data']['email'], $otp);
            $signup_success = 'Verification code sent to your email.';
        }
    }
}

// Close database connection
$conn->close();

// If it's a POST request, return JSON response for AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => empty($signup_error),
        'message' => $signup_success ?: $signup_error
    ]);
    exit();
}

// If GET request, show the standalone signup page (from School System/signup.php)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Sign Up - Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
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

        .success-message {
            color: #28a745;
            font-size: 14px;
            margin-top: 5px;
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

            <?php if (!empty($signup_error)): ?>
                <div class="alert alert-danger"><?php echo $signup_error; ?></div>
            <?php endif; ?>
            <?php if (!empty($signup_success)): ?>
                <div class="alert alert-success"><?php echo $signup_success; ?></div>
            <?php endif; ?>

            <form id="signupForm" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required value="<?php echo htmlspecialchars($firstName ?? ''); ?>">
                            <div class="error-message" id="firstNameError">Please enter your first name</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required value="<?php echo htmlspecialchars($lastName ?? ''); ?>">
                            <div class="error-message" id="lastNameError">Please enter your last name</div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <div class="error-message" id="emailError">Please enter a valid email address</div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
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
                    <input type="checkbox" id="terms" name="terms" required <?php echo $terms ? 'checked' : ''; ?>>
                    <label for="terms">I agree to the <a href="terms-of-service.php" style="color: #667eea;">Terms of Service</a> and <a href="privacy.php" style="color: #667eea;">Privacy Policy</a></label>
                </div>

                <button type="submit" class="btn btn-signup">Create Account</button>
            </form>

            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
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
            // Allow form submission for server-side processing
        });
    </script>
</body>

</html>