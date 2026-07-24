<?php
session_start();
include 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: homeMobile.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if username exists
        $check_username_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        $check_username_stmt->store_result();
        
        // Check if email exists
        $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email_stmt->bind_param("s", $email);
        $check_email_stmt->execute();
        $check_email_stmt->store_result();
        
        if ($check_username_stmt->num_rows > 0) {
            $error = "Username already taken.";
        } elseif ($check_email_stmt->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Create new user with email
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_username_stmt->close();
        $check_email_stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Sign Up | ChefAi</title>
  <style>
    /* CSS Variables for consistent theming */
    :root {
        --primary-color: #C9B194;
        --primary-dark: #A89276;
        --text-color: #333333;
        --text-light: #666666;
        --background: #F6F1DE;
        --surface: #FFFFFF;
        --error: #e74c3c;
        --success: #198754;
        --border-radius: 12px;
        --transition: all 0.3s ease;
    }
    
    /* Reset and base styles */
    *, *::before, *::after { 
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        background-color: var(--background);
        color: var(--text-color);
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    }
    
    /* Main container */
    .container {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 20px 16px;
        width: 100%;
    }
    
    /* Signup card */
    .signup-card {
        width: 100%;
        max-width: 400px;
        background: var(--surface);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    /* App header with logo */
    .app-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        padding: 28px 20px;
        text-align: center;
        color: white;
        position: relative;
    }
    
    .app-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 16px;
        background-color: white;
        border-radius: 18px;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .app-icon img {
        width: 40px;
        height: 40px;
        object-fit: contain;
    }
    
    .app-header h1 {
        font-size: 22px;
        margin-bottom: 8px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    
    .app-header p {
        opacity: 0.9;
        font-size: 14px;
    }
    
    /* Form container */
    .form-container {
        padding: 24px 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
        text-align: left;
        font-size: 14px;
    }
    
    .input-with-icon {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-color);
        font-size: 18px;
    }
    
    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 16px 16px 16px 48px;
        border: 1px solid #ddd;
        border-radius: 10px;
        font-size: 16px;
        transition: var(--transition);
        background-color: #f9f9f9;
        -webkit-appearance: none;
    }
    
    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(201, 177, 148, 0.2);
        background-color: #fff;
    }
    
    /* Password visibility toggle */
    .password-group { 
        position: relative; 
        width: 100%; 
    }
    
    .password-group input {
        width: 100%;
        padding-right: 50px;
    }
    
    .toggle-visibility {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
    }
    
    .toggle-visibility svg {
        width: 20px;
        height: 20px;
    }
    
    /* Password strength indicator */
    .password-strength {
        margin-top: 8px;
        height: 4px;
        border-radius: 2px;
        background-color: #eee;
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: var(--transition);
    }
    
    /* Signup button */
    .signup-btn {
        width: 100%;
        padding: 18px;
        background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        margin-top: 8px;
        box-shadow: 0 4px 12px rgba(201, 177, 148, 0.3);
    }
    
    .signup-btn:active {
        transform: scale(0.98);
    }
    
    /* Messages */
    .error-message {
        color: var(--error);
        background-color: #fdeded;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 14px;
        border-left: 4px solid var(--error);
    }
    
    .success-message {
        color: var(--success);
        background-color: #d4edda;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 14px;
        border-left: 4px solid var(--success);
    }
    
    /* Login link */
    .login-link {
        margin-top: 24px;
        text-align: center;
        font-size: 14px;
        color: var(--text-light);
    }
    
    .login-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }
    
    /* Footer */
    .footer {
        padding: 16px;
        text-align: center;
        color: var(--text-light);
        font-size: 12px;
        margin-top: auto;
        padding-bottom: calc(16px + env(safe-area-inset-bottom));
    }
    
    /* Animation for better UX */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .signup-card {
        animation: fadeIn 0.4s ease-out;
    }
    
    /* Prevent zoom on input focus in iOS */
    @media screen and (max-width: 768px) {
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            font-size: 16px !important;
        }
    }
  </style>
</head>
<body>
    <div class="container">
        <div class="signup-card">
            <div class="app-header">
                <div class="app-icon">
                    <img src="final-logo-txt.png" alt="ChefAI Logo">
                </div>
                <h1>ChefAI</h1>
                <p>Create your account</p>
            </div>
            
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                    <div class="login-link">
                        <p><a href="LoginMobile.php">Go to Login</a></p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="signupForm">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-with-icon">
                                <div class="input-icon">👤</div>
                                <input type="text" id="username" name="username" placeholder="Choose a username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-with-icon">
                                <div class="input-icon">📧</div>
                                <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-with-icon password-group">
                                <div class="input-icon">🔒</div>
                                <input type="password" id="password" name="password" placeholder="Create a password" required autocomplete="new-password">
                                <button type="button" class="toggle-visibility" aria-label="Show password" data-target="#password">
                                    <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div style="font-size: 12px; color: var(--text-light); margin-top: 4px;">
                                Must be at least 8 characters
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-with-icon password-group">
                                <div class="input-icon">🔒</div>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required autocomplete="new-password">
                                <button type="button" class="toggle-visibility" aria-label="Show confirm password" data-target="#confirm_password">
                                    <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="signup-btn">Create Account</button>
                    </form>
                    
                    <div class="login-link">
                        <p>Already have an account? <a href="LoginMobile.php">Login here</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle visibility for all password fields
            document.querySelectorAll('.toggle-visibility').forEach(function(btn) {
                const targetId = btn.getAttribute('data-target');
                const input = document.querySelector(targetId);
                const eyeIcon = btn.querySelector('.icon-eye');
                const eyeOffIcon = btn.querySelector('.icon-eye-off');
                
                if (!input) return;
                
                btn.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeIcon.style.display = 'none';
                        eyeOffIcon.style.display = 'block';
                        btn.setAttribute('aria-label', 'Hide password');
                    } else {
                        input.type = 'password';
                        eyeIcon.style.display = 'block';
                        eyeOffIcon.style.display = 'none';
                        btn.setAttribute('aria-label', 'Show password');
                    }
                });
            });
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('passwordStrengthBar');
            
            if (passwordInput && strengthBar) {
                passwordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    let strength = 0;
                    
                    if (password.length >= 8) strength += 25;
                    if (/[A-Z]/.test(password)) strength += 25;
                    if (/[0-9]/.test(password)) strength += 25;
                    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
                    
                    strengthBar.style.width = strength + '%';
                    
                    // Color coding
                    if (strength < 50) {
                        strengthBar.style.backgroundColor = '#e74c3c'; // Red
                    } else if (strength < 75) {
                        strengthBar.style.backgroundColor = '#f39c12'; // Orange
                    } else {
                        strengthBar.style.backgroundColor = '#2ecc71'; // Green
                    }
                });
            }
            
            // Form validation
            const form = document.getElementById('signupForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const email = document.getElementById('email').value;
                    
                    // Email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match. Please check and try again.');
                        return;
                    }
                    
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long.');
                        return;
                    }
                });
            }
            
            // Real-time password matching indicator
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput && passwordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (confirmPassword.length > 0) {
                        if (password === confirmPassword) {
                            confirmPasswordInput.style.borderColor = '#2ecc71';
                        } else {
                            confirmPasswordInput.style.borderColor = '#e74c3c';
                        }
                    } else {
                        confirmPasswordInput.style.borderColor = '#ddd';
                    }
                });
            }
            
            // Prevent form zoom on focus in iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.addEventListener('focus', function(e) {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                        document.body.style.zoom = '100%';
                    }
                }, true);
            }
        });
    </script>
</body>
</html>