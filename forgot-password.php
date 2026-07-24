<?php
session_start();
include 'db_connect.php';

$error = '';
$success = '';
$valid_token = false;
$token = '';

// Check if token is provided in URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token format (basic check)
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        $error = "Invalid reset token format.";
    } else {
        // Check if token is valid and not expired
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $valid_token = true;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_token'] = $token; // Store token in session for verification
        } else {
            $error = "Invalid or expired reset token.";
        }
        $stmt->close();
    }
} else {
    // No token provided - redirect to login
    header('Location: LoginMobile.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['reset_user_id'])) {
    // Verify that the token in session matches the one from URL
    if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token) {
        $error = "Token validation failed. Please request a new reset link.";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password and clear the reset token
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
            
            if ($stmt->execute()) {
                $success = "Password reset successfully!";
                // Clear session data
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_token']);
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Reset Password | ChefAI</title>
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
            --success: #2ecc71;
            --google-blue: #4285F4;
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
        
        /* iOS-style status bar */
        .status-bar {
            height: 44px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding-top: env(safe-area-inset-top);
        }
        
        .status-bar-time {
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Main container */
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px 16px;
            margin-top: calc(44px + env(safe-area-inset-top));
            width: 100%;
        }
        
        /* Login card */
        .login-card {
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
        
        /* Consistent styling for both password and text inputs */
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background-color: #f9f9f9;
            -webkit-appearance: none;
            font-family: inherit; /* Ensure consistent font */
        }
        
        input[type="password"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(201, 177, 148, 0.2);
            background-color: #fff;
        }
        
        /* Placeholder styling */
        input::placeholder {
            color: #999;
            font-family: inherit;
            opacity: 1; /* Ensure consistent opacity across browsers */
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
            z-index: 2; /* Ensure button stays above input */
        }
        
        .toggle-visibility svg {
            width: 20px;
            height: 20px;
        }
        
        /* Submit button */
        .submit-btn {
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
        
        .submit-btn:active {
            transform: scale(0.98);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Message styles */
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid;
        }
        
        .error {
            color: var(--error);
            background-color: #fdeded;
            border-left-color: var(--error);
        }
        
        .success {
            color: var(--success);
            background-color: #edf7f0;
            border-left-color: var(--success);
        }
        
        .info {
            color: #3498db;
            background-color: #e8f4fd;
            border-left-color: #3498db;
        }
        
        /* Success message after popup */
        .final-message {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            font-size: 16px;
            line-height: 1.5;
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
        
        /* Popup notification */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .popup {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 24px;
            max-width: 300px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: var(--transition);
        }
        
        .popup-overlay.active .popup {
            transform: translateY(0);
        }
        
        .popup-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .popup h3 {
            margin: 0 0 12px;
            color: var(--text-color);
        }
        
        .popup p {
            margin: 0 0 20px;
            color: var(--text-light);
            line-height: 1.5;
        }
        
        .popup-btn {
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .popup-btn:active {
            background: var(--primary-dark);
        }
        
        /* Animation for better UX */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-card {
            animation: fadeIn 0.4s ease-out;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
        
        /* Prevent zoom on input focus in iOS */
        @media screen and (max-width: 768px) {
            input[type="password"],
            input[type="text"] {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>
    <div class="status-bar">
        <div class="status-bar-time" id="time">12:00</div>
    </div>
    
    <div class="container">
        <div class="login-card">
            <div class="app-header">
                <div class="app-icon">
                    <img src="logoAI1.png" alt="ChefAI Logo">
                </div>
                <h1>ChefAI</h1>
                <p>Set your new password</p>
            </div>
            
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                    <div class="back-link">
                        <p><a href="forgot-password.php">Request a new reset link</a></p>
                    </div>
                <?php elseif (!empty($success)): ?>
                    <!-- Show only the final message after popup is closed -->
                    <div id="finalMessage" class="final-message" style="display: none;">
                        <p>Your password has been reset successfully. You can now return to your ChefAI mobile app to log in.</p>
                    </div>
                <?php elseif ($valid_token): ?>
                    <div class="message info">
                        You can now set a new password for your account.
                    </div>
                    
                    <form method="POST" action="" id="resetPasswordForm">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-with-icon password-group">
                                <i class="input-icon">🔒</i>
                                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required minlength="6">
                                <button type="button" class="toggle-visibility" aria-label="Show password">
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
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-with-icon password-group">
                                <i class="input-icon">🔒</i>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
                                <button type="button" class="toggle-visibility" aria-label="Show password">
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
                        
                        <button type="submit" class="submit-btn" id="resetBtn">
                            <span id="resetBtnText">Reset Password</span>
                            <span id="resetBtnSpinner" class="spinner hidden"></span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="message error">Unable to process your request. Please request a new password reset link.</div>
                    <div class="back-link">
                        <p><a href="forgot-password.php">Request a new reset link</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> ChefAI. All rights reserved.</p>
    </div>

    <!-- Popup Notification for Successful Password Change -->
    <div class="popup-overlay" id="successPopup">
        <div class="popup">
            <div class="popup-icon">✅</div>
            <h3>Password Changed Successfully</h3>
            <p>Your password has been changed successfully. You can now return to your ChefAI app.</p>
            <button class="popup-btn" onclick="closePopup()">OK</button>
        </div>
    </div>

    <script>
        // Update time in status bar
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('time').textContent = timeString;
        }
        
        // Show popup notification
        function showPopup() {
            document.getElementById('successPopup').classList.add('active');
        }
        
        // Close popup notification
        function closePopup() {
            document.getElementById('successPopup').classList.remove('active');
            // Show the final message instead of redirecting
            document.getElementById('finalMessage').style.display = 'block';
            // Hide the form container
            document.querySelector('.form-container').style.display = 'none';
        }
        
        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtns = document.querySelectorAll('.toggle-visibility');
            
            toggleBtns.forEach(toggleBtn => {
                const passwordInput = toggleBtn.parentElement.querySelector('input');
                const eyeIcon = toggleBtn.querySelector('.icon-eye');
                const eyeOffIcon = toggleBtn.querySelector('.icon-eye-off');
                
                toggleBtn.addEventListener('click', function() {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        eyeIcon.style.display = 'none';
                        eyeOffIcon.style.display = 'block';
                        toggleBtn.setAttribute('aria-label', 'Hide password');
                    } else {
                        passwordInput.type = 'password';
                        eyeIcon.style.display = 'block';
                        eyeOffIcon.style.display = 'none';
                        toggleBtn.setAttribute('aria-label', 'Show password');
                    }
                    
                    // Force placeholder to maintain styling
                    passwordInput.style.fontFamily = 'inherit';
                    passwordInput.style.fontSize = '16px';
                });
            });
            
            // Form submission handler
            const resetForm = document.getElementById('resetPasswordForm');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const resetBtn = document.getElementById('resetBtn');
                    const resetBtnText = document.getElementById('resetBtnText');
                    const resetBtnSpinner = document.getElementById('resetBtnSpinner');
                    
                    // Validate passwords
                    if (newPassword.length < 6) {
                        alert("Password must be at least 6 characters long.");
                        return;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        alert("Passwords do not match.");
                        return;
                    }
                    
                    // Show loading state
                    resetBtn.disabled = true;
                    resetBtnText.textContent = 'Resetting...';
                    resetBtnSpinner.classList.remove('hidden');
                    
                    // Submit the form after a brief delay to show loading state
                    setTimeout(() => {
                        resetForm.submit();
                    }, 500);
                });
            }
            
            // Check if we should show the success popup
            <?php if (!empty($success)): ?>
                setTimeout(showPopup, 500);
            <?php endif; ?>
            
            // Prevent form zoom on focus in iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.addEventListener('focus', function(e) {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                        document.body.style.zoom = '100%';
                    }
                }, true);
            }
        });
        
        // Initial call and set interval
        updateTime();
        setInterval(updateTime, 60000);
    </script>
</body>
</html>