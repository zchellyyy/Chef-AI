<?php
// TEMP: show errors so we can see what's wrong
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db_connect.php';
require_once 'mail_config_signup.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

$error = '';
$success = '';

// If you want to show a message after redirect from login or others
if (isset($_SESSION['signup_error'])) {
    $error = $_SESSION['signup_error'];
    unset($_SESSION['signup_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if ($username === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check for existing username OR email
        $check = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            if (strcasecmp($existing['username'] ?? '', $username) === 0) {
                $error = "Username already taken.";
            } elseif (strcasecmp($existing['email'] ?? '', $email) === 0) {
                $error = "Email is already registered.";
            } else {
                $error = "Username or Email already in use.";
            }
        } else {
            // Everything is valid → generate OTP and send email

            // 6-digit OTP
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + 60; // 60 seconds from now

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Store pending user data in session (NOT in DB yet)
            $_SESSION['pending_user'] = [
                'username'   => $username,
                'email'      => $email,
                'password'   => $hashed_password,
                'otp'        => $otp,
                'expires_at' => $expires_at,
            ];

            // Send OTP email
            $sent = sendOtpEmail($email, $username, $otp);

            if ($sent) {
                // Redirect to OTP verification page
                header('Location: verify_otp_signup.php');
                exit();
            } else {
                $error = "Failed to send verification email. Please try again later.";
                unset($_SESSION['pending_user']);
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | ChefAI</title>
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
        
        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .signup-header h2 {
            color: var(--primary-color);
            margin: 0 0 8px;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .signup-header p {
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
        .signup-btn {
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
        
        .signup-btn:hover {
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
        
        .login-link {
            margin-top: 25px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--dark-color);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .login-link a:hover {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="brand-text">
            </div>
        </div>
        
        <div class="right-panel">
            <div class="signup-header">
                <h2>Create Account</h2>
                <p>Join ChefAI to access all features.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <div class="login-link">
                    <p><a href="login.php">Go to Login</a></p>
                </div>
            <?php else: ?>
            
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required autocomplete="username" value="">
                    </div>

                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email address" required autocomplete="email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>

                    <!-- Password with show/hide -->
                    <div class="form-group password-group">
                        <input type="password" id="password" name="password" placeholder="Password" required autocomplete="new-password">
                        <button type="button"
                                class="toggle-visibility"
                                aria-label="Show password"
                                aria-pressed="false">
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

                    <!-- Confirm Password with show/hide -->
                    <div class="form-group password-group">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required autocomplete="new-password">
                        <button type="button"
                                class="toggle-visibility"
                                aria-label="Show confirm password"
                                aria-pressed="false">
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

                    <button type="submit" class="signup-btn">Sign Up</button>
                </form>

                
                <!-- QR code download section -->
                
                <!-- Login links -->
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QR Code Modal -->


    <script>
        // Show/Hide password script
        document.addEventListener('DOMContentLoaded', function () {
            // Password toggle functionality
            document.querySelectorAll('.toggle-visibility').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = this.parentElement.querySelector('input');
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    this.setAttribute('aria-pressed', show ? 'true' : 'false');
                    const base = this.getAttribute('aria-label').replace(/^Show|^Hide/, '');
                    this.setAttribute('aria-label', (show ? 'Hide' : 'Show') + base);
                });
            });
          
        });
    </script>
</body>
</html>