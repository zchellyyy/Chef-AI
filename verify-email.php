<?php
session_start();
include 'db_connect.php';
include 'mail_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Step 1: Check username
    if (isset($_POST['action']) && $_POST['action'] === 'check_username') {
        $username = trim($_POST['username']);
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Please enter your username']);
            exit();
        }
        
        // Check if username exists in database
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Mask the email for security (e.g., n***x*82*@gmail.com)
            $email = $user['email'];
            $email_parts = explode('@', $email);
            $local_part = $email_parts[0];
            $domain = $email_parts[1];
            
            // Mask the local part (keep first character, then mask with asterisks, keep some characters)
            $masked_local = '';
            $keep_positions = [0]; // Always keep first character
            $additional_keep = floor(strlen($local_part) / 3); // Keep about 1/3 of characters
            
            for ($i = 1; $i < $additional_keep && $i < strlen($local_part); $i++) {
                $keep_positions[] = $i * 2; // Keep characters at positions 2, 4, 6, etc.
            }
            
            for ($i = 0; $i < strlen($local_part); $i++) {
                if (in_array($i, $keep_positions) || $i === strlen($local_part) - 1) {
                    $masked_local .= $local_part[$i];
                } else {
                    $masked_local .= '*';
                }
            }
            
            $masked_email = $masked_local . '@' . $domain;
            
            // Store user info in session for next step
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_username'] = $user['username'];
            $_SESSION['reset_email'] = $user['email'];
            $_SESSION['masked_email'] = $masked_email;
            
            echo json_encode([
                'success' => true, 
                'message' => 'Username found!', 
                'masked_email' => $masked_email,
                'email' => $user['email'] // For verification but not displayed
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Username not found in our system']);
        }
        $stmt->close();
        exit();
    }
    
    // Step 2: Send verification email
    if (isset($_POST['action']) && $_POST['action'] === 'send_verification') {
        if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
            exit();
        }
        
        $user_id = $_SESSION['reset_user_id'];
        $email = $_SESSION['reset_email'];
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expiry_time = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        
        // Store token in database
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $token, $expiry_time, $user_id);
        
        if ($update_stmt->execute()) {
            // Store token in session for backup
            $_SESSION['reset_token'] = $token;
            
            // Send verification email
            $sendResult = sendVerificationEmail($email, $token);
            
            if ($sendResult['success']) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Password reset link sent successfully! Check your email inbox.',
                    'masked_email' => $_SESSION['masked_email']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate reset token. Please try again.']);
        }
        $update_stmt->close();
        exit();
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
    <title>Verify Email | ChefAi</title>
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
        
        input[type="text"],
        input[type="email"] {
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
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(201, 177, 148, 0.2);
            background-color: #fff;
        }
        
        /* Buttons */
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
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .back-btn {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 8px;
        }
        
        .back-btn:active {
            background-color: rgba(201, 177, 148, 0.1);
        }
        
        /* Messages */
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
            border-color: var(--error);
        }
        
        .success {
            color: var(--success);
            background-color: #f0f9f4;
            border-color: var(--success);
        }
        
        .info {
            color: var(--primary-color);
            background-color: #f9f6f0;
            border-color: var(--primary-color);
        }
        
        /* Email display */
        .email-display {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .email-label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
            display: block;
        }
        
        /* Back to login link */
        .back-link {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .back-link a {
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
        
        .login-card {
            animation: fadeIn 0.4s ease-out;
        }
        
        /* Prevent zoom on input focus in iOS */
        @media screen and (max-width: 768px) {
            input[type="text"],
            input[type="email"] {
                font-size: 16px !important;
            }
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
        
        .verification-sent {
            background: #f0f9f4;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        /* Step indicators */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 2;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: var(--success);
            color: white;
        }
        
        .step-line {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step-line.active {
            background: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="app-header">
                <div class="app-icon">
                    <img src="final-logo-txt.png" alt="ChefAI Logo">
                </div>
                <h1>ChefAI</h1>
                <p>Reset your password</p>
            </div>
            
            <div class="form-container">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step1Indicator">1</div>
                    <div class="step" id="step2Indicator">2</div>
                    <div class="step-line active" id="stepLine"></div>
                </div>
                
                <!-- Step 1: Username Input -->
                <div id="step1">
                    <div class="message info">
                        Enter your username to find your account.
                    </div>
                    
                    <form id="usernameForm">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-with-icon">
                                <i class="input-icon">👤</i>
                                <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-btn" id="checkBtn">
                            <span id="checkBtnText">Find Account</span>
                            <span id="checkBtnSpinner" class="spinner hidden"></span>
                        </button>
                    </form>
                </div>
                
                <!-- Step 2: Email Verification -->
                <div id="step2" class="hidden">
                    <div class="message info">
                        We found your account! Verify your email address.
                    </div>
                    
                    <div class="email-display">
                        <span class="email-label">Your email address:</span>
                        <div id="maskedEmailDisplay"></div>
                    </div>
                    
                    <div class="message info">
                        Is this your email address? We'll send a password reset link to this email.
                    </div>
                    
                    <button type="button" class="submit-btn" id="sendBtn">
                        <span id="sendBtnText">Send Reset Link</span>
                        <span id="sendBtnSpinner" class="spinner hidden"></span>
                    </button>
                    
                    <button type="button" class="back-btn" id="backToUsername">Back to Username</button>
                </div>
                
                <!-- Step 3: Success Message -->
                <div id="step3" class="hidden">
                    <div class="verification-sent">
                        <h3>✅ Reset Link Sent!</h3>
                        <p>We've sent a password reset link to:</p>
                        <div class="email-display">
                            <span id="successEmailDisplay"></span>
                        </div>
                        <p>Please check your inbox and click the link to reset your password.</p>
                    </div>
                </div>
                
                <div class="back-link">
                    <p>Remember your password? <a href="LoginMobile.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const step1Indicator = document.getElementById('step1Indicator');
            const step2Indicator = document.getElementById('step2Indicator');
            const stepLine = document.getElementById('stepLine');
            
            const usernameForm = document.getElementById('usernameForm');
            const checkBtn = document.getElementById('checkBtn');
            const checkBtnText = document.getElementById('checkBtnText');
            const checkBtnSpinner = document.getElementById('checkBtnSpinner');
            const sendBtn = document.getElementById('sendBtn');
            const sendBtnText = document.getElementById('sendBtnText');
            const sendBtnSpinner = document.getElementById('sendBtnSpinner');
            const maskedEmailDisplay = document.getElementById('maskedEmailDisplay');
            const successEmailDisplay = document.getElementById('successEmailDisplay');
            const backToUsernameBtn = document.getElementById('backToUsername');
            
            let userEmail = '';
            let userMaskedEmail = '';
            
            // Username form submission
            usernameForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const username = document.getElementById('username').value.trim();
                
                if (!username) {
                    showMessage('Please enter your username.', 'error');
                    return;
                }
                
                // Show loading state
                checkBtn.disabled = true;
                checkBtnText.textContent = 'Checking...';
                checkBtnSpinner.classList.remove('hidden');
                
                // Check username
                checkUsername(username);
            });
            
            // Send verification email
            sendBtn.addEventListener('click', function() {
                // Show loading state
                sendBtn.disabled = true;
                sendBtnText.textContent = 'Sending...';
                sendBtnSpinner.classList.remove('hidden');
                
                sendVerificationEmail();
            });
            
            // Back to username step
            backToUsernameBtn.addEventListener('click', function() {
                showStep(1);
            });
            
            function checkUsername(username) {
                const formData = new URLSearchParams();
                formData.append('action', 'check_username');
                formData.append('username', username);
                
                fetch('verify-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userEmail = data.email;
                        userMaskedEmail = data.masked_email;
                        
                        // Show masked email
                        maskedEmailDisplay.textContent = userMaskedEmail;
                        successEmailDisplay.textContent = userMaskedEmail;
                        
                        // Move to step 2
                        showStep(2);
                    } else {
                        showMessage(data.message, 'error');
                    }
                    
                    // Reset button state
                    checkBtn.disabled = false;
                    checkBtnText.textContent = 'Find Account';
                    checkBtnSpinner.classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Server error. Please try again.', 'error');
                    
                    // Reset button state
                    checkBtn.disabled = false;
                    checkBtnText.textContent = 'Find Account';
                    checkBtnSpinner.classList.add('hidden');
                });
            }
            
            function sendVerificationEmail() {
                const formData = new URLSearchParams();
                formData.append('action', 'send_verification');
                
                fetch('verify-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStep(3);
                    } else {
                        showMessage(data.message, 'error');
                    }
                    
                    // Reset button state
                    sendBtn.disabled = false;
                    sendBtnText.textContent = 'Send Reset Link';
                    sendBtnSpinner.classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Server error. Please try again.', 'error');
                    
                    // Reset button state
                    sendBtn.disabled = false;
                    sendBtnText.textContent = 'Send Reset Link';
                    sendBtnSpinner.classList.add('hidden');
                });
            }
            
            function showStep(stepNumber) {
                // Hide all steps
                step1.classList.add('hidden');
                step2.classList.add('hidden');
                step3.classList.add('hidden');
                
                // Reset indicators
                step1Indicator.classList.remove('completed');
                step2Indicator.classList.remove('completed', 'active');
                stepLine.classList.remove('active');
                
                // Show selected step and update indicators
                if (stepNumber === 1) {
                    step1.classList.remove('hidden');
                    step1Indicator.classList.add('active');
                } else if (stepNumber === 2) {
                    step2.classList.remove('hidden');
                    step1Indicator.classList.add('completed');
                    step2Indicator.classList.add('active');
                    stepLine.classList.add('active');
                } else if (stepNumber === 3) {
                    step3.classList.remove('hidden');
                    step1Indicator.classList.add('completed');
                    step2Indicator.classList.add('completed');
                    stepLine.classList.add('active');
                }
            }
            
            function showMessage(message, type) {
                // Remove any existing messages
                const existingMessage = document.querySelector('.message:not(.info)');
                if (existingMessage) {
                    existingMessage.remove();
                }
                
                // Create new message
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${type}`;
                messageDiv.textContent = message;
                
                // Insert before the current step form
                const currentStep = document.querySelector('#step1:not(.hidden), #step2:not(.hidden), #step3:not(.hidden)');
                if (currentStep) {
                    currentStep.insertBefore(messageDiv, currentStep.firstChild);
                }
                
                // Auto-remove after 5 seconds (except success messages)
                if (type !== 'success') {
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 5000);
                }
            }
        });
    </script>
</body>
</html>