<?php
session_start();
include 'db_connect.php';

header("Cache-Control: public, max-age=3600, stale-while-revalidate=86400, stale-if-error=43200");

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: homeMobile.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check for admin credentials first
        if ($username === 'walanato' && $password === 'walanato') {
            $_SESSION['user_id'] = 0; // Special ID for admin
            $_SESSION['username'] = 'keenAdmin';
            $_SESSION['is_admin'] = true;
            header('Location: admin.php');
            exit();
        }

        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $db_username, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                header('Location: homeMobile.php');
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Login | ChefAi</title>
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
        
        /* Main container */
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px 16px;
            margin-top: calc(20px + env(safe-area-inset-top));
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
            z-index: 2;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background-color: #f9f9f9;
            -webkit-appearance: none;
            position: relative;
            z-index: 1;
        }
        
        input[type="text"]:focus,
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
            z-index: 3;
        }
        
        .toggle-visibility svg {
            width: 20px;
            height: 20px;
        }
        
        .icon-eye-off {
            display: none;
        }
        
        /* Login button */
        .login-btn {
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
        
        .login-btn:active {
            transform: scale(0.98);
        }
        
        /* Google sign-in button */
        .google-btn {
            width: 100%;
            padding: 16px;
            background: white;
            color: var(--text-color);
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .google-btn:active {
            background-color: #f5f5f5;
        }
        
        .google-btn img {
            width: 20px;
            height: 20px;
        }
        
        /* Error message */
        .error {
            color: var(--error);
            background-color: #fdeded;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid var(--error);
        }
        
        /* Sign up link */
        .signup-link {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .signup-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: block;
            margin-top: 8px;
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
            input[type="password"] {
                font-size: 16px !important;
            }
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
                <p>Welcome back! Please login to your account.</p>
            </div>
            
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-with-icon">
                            <i class="input-icon">👤</i>
                            <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon password-group">
                            <i class="input-icon">🔒</i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="toggle-visibility" aria-label="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-btn">Login</button>
                </form>
                
                <a href="javascript:void(0)" onclick="handleGoogleLogin()" class="google-btn">
                    <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo">
                    Continue with Google
                </a>
                
                <div class="signup-link">
                    <p>New User? <a href="signupMobile.php">Sign up here</a></p>
                    <a href="verify-email.php">Forgot password?</a>
                </div>
            </div>
        </div>
    </div>
    

    <script>
        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector('.toggle-visibility');
            const passwordInput = document.getElementById('password');
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
            });
            
            // Prevent form zoom on focus in iOS
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.addEventListener('focus', function(e) {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                        document.body.style.zoom = '100%';
                    }
                }, true);
            }
        });

        function handleGoogleLogin() {
            if (typeof AndroidBridge !== 'undefined' && AndroidBridge.startGoogleLogin) {
                // Use native Android Google Sign-in
                AndroidBridge.startGoogleLogin();
            } else {
                // Fallback to web-based Google Sign-in
                window.location.href = 'https://chefaiph.site/google-auth.php';
            }
        }

        // This function will be called from Android
        function onGoogleLoginSuccess(userData) {
            console.log('Received user data:', userData);
            
            // Send to PHP backend
            const formData = new URLSearchParams();
            formData.append('email', userData.email);
            formData.append('name', userData.name);
            formData.append('google_id', userData.id);
            formData.append('id_token', userData.idToken);
            
            fetch('https://chefaiph.site/google-login-mobile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'homeMobile.php';
                } else {
                    alert('Login failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }

        function onGoogleLoginError(error) {
            alert('Google sign-in failed: ' + error);
        }
    </script>
</body>
</html>