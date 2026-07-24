<?php
session_start();
$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

include 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
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
        if ($username === 'keenAdmin' && $password === 'admin123') {
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
                header('Location: default.php');
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
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ChefAI</title>
    <style>
        /* Make padding/borders not increase element width */
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --primary-color: #C1856D;
            --secondary-color: #FFDBB5;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --neutral-color: #6C4E31;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
            --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
            background-color: #F6F1DE;
        }
        
        .container {
            display: flex;
            width: 900px;
            max-width: 100%;
            box-shadow: var(--card-shadow);
            border-radius: 15px;
            overflow: hidden;
            background: white;
        }
        
        .left-panel {
            flex: 1;
            background-image: url('final-logo-txt.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 30px;
            position: relative;
        }
        
        .left-panel::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        
        .brand-text {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .brand-text h1 {
            font-size: 2.8rem;
            margin: 0 0 15px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            color: white;
        }
        
        .brand-text .tagline {
            font-size: 1.2rem;
            margin: 0;
            opacity: 0.9;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .right-panel {
            flex: 1;
            background: white;
            padding: 40px 35px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: var(--primary-color);
            margin: 0 0 8px;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .login-header p {
            color: var(--dark-color);
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.8;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--light-color);
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(193, 133, 109, 0.2);
        }

        /* Password group styles */
        .password-group { 
            position: relative; 
            width: 100%; 
        }
        
        .password-group input {
            width: 100%;
            padding-right: 44px;
            box-sizing: border-box;
        }

        .toggle-visibility {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            color: var(--neutral-color);
        }

        .toggle-visibility:focus { outline: none; }
        .toggle-visibility:focus-visible { 
            outline: 2px solid var(--primary-color); 
            outline-offset: 2px; 
        }

        .toggle-visibility .icon-eye-off { display: none; }
        .toggle-visibility[aria-pressed="true"] .icon-eye { display: none; }
        .toggle-visibility[aria-pressed="true"] .icon-eye-off { display: inline-block; }
        
        button.toggle-visibility { width: auto !important; }

        /* Submit button styling */
        .login-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .login-btn:hover {
            background-color: var(--neutral-color);
            transform: translateY(-2px);
        }

        .error {
            color: #d32f2f;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 6px;
            font-size: 0.9rem;
            border-left: 4px solid #d32f2f;
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: var(--transition);
            background: white;
        }
        
        .google-btn:hover {
            background-color: #f8f8f8;
            border-color: #ccc;
            transform: translateY(-2px);
        }
        
        .google-btn img {
            height: 20px;
            margin-right: 10px;
        }
        
        .qr-section {
            margin-top: 25px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .qr-section p {
            font-weight: 500;
            color: var(--neutral-color);
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .qr-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: var(--transition);
            padding: 8px 15px;
            border-radius: 6px;
            background-color: rgba(193, 133, 109, 0.1);
        }
        
        .qr-link:hover {
            color: var(--neutral-color);
            background-color: rgba(193, 133, 109, 0.2);
            transform: translateY(-2px);
        }
        
        .signup-link {
            margin-top: 25px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--dark-color);
        }
        
        .signup-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .signup-link a:hover {
            color: var(--neutral-color);
            text-decoration: underline;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            padding: 25px;
            box-shadow: var(--card-hover-shadow);
            text-align: center;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-color);
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            background-color: rgba(193, 133, 109, 0.1);
            color: var(--primary-color);
        }
        
        .modal h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.4rem;
        }
        
        .modal p {
            color: var(--dark-color);
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .qr-code {
            width: 180px;
            height: 180px;
            margin: 0 auto 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .download-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
        
        .download-btn {
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            transition: var(--transition);
        }
        
        .app-store {
            background-color: var(--dark-color);
            color: white;
        }
        
        .app-store:hover {
            background-color: #3a424b;
            transform: translateY(-2px);
        }
        
        .google-play {
            background-color: var(--primary-color);
            color: white;
        }
        
        .google-play:hover {
            background-color: var(--neutral-color);
            transform: translateY(-2px);
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 100%;
            }
            
            .left-panel {
                padding: 30px 20px;
                min-height: 200px;
            }
            
            .brand-text h1 {
                font-size: 2.2rem;
            }
            
            .brand-text .tagline {
                font-size: 1rem;
            }
            
            .right-panel {
                padding: 30px 25px;
            }
        }
        
        .success {
            color: #198754;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #d1f2eb;
            border-radius: 6px;
            font-size: 0.9rem;
            border-left: 4px solid #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="brand-text">
            </div>
        </div>
        
        <div class="right-panel">
            <div class="login-header">
                <h2>Login</h2>
                <p>Welcome back! Please login to your account.</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                </div>

                <div class="form-group password-group">
                    <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button"
                            class="toggle-visibility"
                            aria-label="Show password"
                            aria-pressed="false"
                            title="Show/Hide password">
                        <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                          <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                          <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                          <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                          <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                          <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <a href="google-login.php" class="google-btn">
                <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo">
                Continue with Google
            </a>
            
            <!-- QR code download section -->
            <div class="qr-section">
                <p>📱 <span class="qr-link" id="show-qr-modal">Get ChefAI mobile app</span></p>
            </div>
            
            <!-- Signup links -->
            <div class="signup-link">
                <p>New User? <a href="signup.php">Sign up here</a></p>
                <p style="margin-top:8px;"><a href="forgot-password-web.php">Forgot password?</a></p>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal" id="qr-modal">
        <div class="modal-content">
            <button class="close-modal" id="close-modal">&times;</button>
            <h3>Get ChefAI Mobile App</h3>
            <p>Scan the QR code or download directly</p>
            
            <div class="qr-code">
                <img src="qr-new.png" alt="ChefAI App QR Code">
            </div>
            
            <div class="download-options">
                <a href="https://www.upload-apk.com/AclUyj0flGe79d2" class="download-btn google-play">Get it on upload-apk</a>
            </div>
        </div>
    </div>

    <script>
        // Show/Hide password script
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.querySelector('.toggle-visibility');
            if (!btn) return;
            const input = document.getElementById('password');

            btn.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
            
            // QR Modal functionality
            const qrModal = document.getElementById('qr-modal');
            const showQrBtn = document.getElementById('show-qr-modal');
            const closeModalBtn = document.getElementById('close-modal');
            
            showQrBtn.addEventListener('click', function() {
                qrModal.style.display = 'flex';
            });
            
            closeModalBtn.addEventListener('click', function() {
                qrModal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === qrModal) {
                    qrModal.style.display = 'none';
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    qrModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>