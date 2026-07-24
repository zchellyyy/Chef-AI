<?php
// ===== DEBUG (enable only while testing) =====
// ini_set('display_errors',1);
// ini_set('display_startup_errors',1);
// error_reporting(E_ALL);

// ===== SETUP =====

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';        // must define $conn (mysqli)
require_once 'mailer-config-web.php';     // must define make_mailer()

// config
$OTP_LEN         = 4;    // 4-digit code
$OTP_EXP_MIN     = 10;   // expires in 10 minutes
$MAX_ATTEMPTS    = 5;

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function gen4(){ return (string)random_int(1000, 9999); }

// Ensure password_resets table exists (safe if already created)
$conn->query("
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$step  = $_GET['step'] ?? 'email';
$error = $info  = '';

// ===== STEP 1: EMAIL =====
if ($step === 'email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email.';
    } else {
        // Look up user
        $stmt = $conn->prepare("SELECT id, username, oauth_provider, password FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Generic message (avoid email enumeration)
        $info = 'If this email is registered, a code has been sent. Please check your inbox.';

        if ($user) {
            // Optional: block Google-only accounts (no local password)
            if ($user['oauth_provider'] === 'google' && is_null($user['password'])) {
                $info = 'This account uses Google Sign-In. Please use "Continue with Google" on the login page.';
            } else {
                // Create OTP
                $otp     = gen4(); // 4 digits
                $hash    = password_hash($otp, PASSWORD_DEFAULT);
                $expires = (new DateTime("+{$OTP_EXP_MIN} minutes"))->format('Y-m-d H:i:s');

                // Remove pending codes
                $del = $conn->prepare("DELETE FROM password_resets WHERE user_id=? AND used_at IS NULL");
                $del->bind_param('i', $user['id']);
                $del->execute(); $del->close();

                // Save new code
                $ins = $conn->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, ?)");
                $ins->bind_param('iss', $user['id'], $hash, $expires);
                $ins->execute(); $ins->close();

                // Send via PHPMailer
                try {
                    $m = make_mailer();
                    $m->addAddress($email);
                    $m->Subject = 'Your ChefAI password reset code';
                    $m->Body = "
                        <p>Hello ".h($user['username']).",</p>
                        <p>Your one-time code is <strong style='font-size:18px'>{$otp}</strong>.</p>
                        <p>This code expires in {$OTP_EXP_MIN} minutes. If you didn't request this, you can ignore this email.</p>
                    ";
                    $m->send();
                } catch (Throwable $e) {
                    // You can log $e->getMessage() server-side if needed
                }

                // Persist for next steps
                $_SESSION['pw_reset_email'] = $email;
                header('Location: forgot-password-web.php?step=verify');
                exit;
            }
        }
    }
}

// ===== STEP 2: VERIFY =====
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['pw_reset_email'] ?? '';
    $code  = trim(($_POST['d1'] ?? '').($_POST['d2'] ?? '').($_POST['d3'] ?? '').($_POST['d4'] ?? ''));

    if ($email === '') { header('Location: forgot-password-web.php?step=email'); exit; }

    // Fetch user
    $u = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $u->bind_param('s', $email);
    $u->execute();
    $user = $u->get_result()->fetch_assoc();
    $u->close();

    if ($user) {
        $q = $conn->prepare("
            SELECT id, code_hash, expires_at, attempts
            FROM password_resets
            WHERE user_id=? AND used_at IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $q->bind_param('i', $user['id']);
        $q->execute();
        $reset = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$reset) {
            $error = 'Code not found. Please request a new one.';
        } elseif (new DateTime() > new DateTime($reset['expires_at'])) {
            $error = 'Code expired. Please request a new one.';
        } elseif ((int)$reset['attempts'] >= $MAX_ATTEMPTS) {
            $error = 'Too many attempts. Please request a new code.';
        } elseif (!password_verify($code, $reset['code_hash'])) {
            $upd = $conn->prepare("UPDATE password_resets SET attempts=attempts+1 WHERE id=?");
            $upd->bind_param('i', $reset['id']);
            $upd->execute(); $upd->close();
            $error = 'Invalid code.';
        } else {
            // Mark used and go to reset
            $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE id={$reset['id']}")->execute();
            $_SESSION['pw_reset_user'] = $user['id'];
            header('Location: forgot-password-web.php?step=reset');
            exit;
        }
    } else {
        $error = 'Invalid session. Start over.';
        unset($_SESSION['pw_reset_email']);
        $step = 'email';
    }
}

// ===== STEP 3: RESET =====
if ($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['pw_reset_user'] ?? null;
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';

    if (!$user_id) { header('Location: forgot-password-web.php?step=email'); exit; }
    if (strlen($p1) < 8)            $error = 'Password must be at least 8 characters.';
    elseif ($p1 !== $p2)            $error = 'Passwords do not match.';
    else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $u->bind_param('si', $hash, $user_id);
        $u->execute(); $u->close();

        unset($_SESSION['pw_reset_user'], $_SESSION['pw_reset_email']);
        header('Location: login.php');
        exit;
    }
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
    <title>Forgot Password | ChefAI</title>
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
            height: 500px;
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
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            color: var(--primary-color);
            margin: 0 0 8px;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .header p {
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

        /* Button styling */
        .btn {
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
        
        .btn:hover {
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

        .info {
            color: #1976d2;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #e3f2fd;
            border-radius: 6px;
            font-size: 0.9rem;
            border-left: 4px solid #1976d2;
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

        .otp-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .otp-row input {
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #ddd;
            background-color: var(--light-color);
        }
        
        .otp-row input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(193, 133, 109, 0.2);
        }

        .link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            width: 100%;
        }
        
        .link:hover {
            color: var(--neutral-color);
            text-decoration: underline;
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
            
            .otp-row input {
                width: 50px;
                height: 50px;
                font-size: 20px;
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
            <?php if ($step === 'email'): ?>
                <div class="header">
                    <h2>Reset Your Password</h2>
                    <p>Enter your email and we'll send a verification code</p>
                </div>
                
                <?php if ($info): ?><div class="info"><?=h($info)?></div><?php endif; ?>
                <?php if ($error): ?><div class="error"><?=h($error)?></div><?php endif; ?>
                
                <form method="post" action="?step=email">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email address" required value="<?=h($_POST['email'] ?? '')?>">
                    </div>
                    <button class="btn" type="submit">Send Verification Code</button>
                </form>
                <a class="link" href="login.php">Back to Login</a>

            <?php elseif ($step === 'verify'): ?>
                <div class="header">
                    <h2>Enter Verification Code</h2>
                    <p>We sent a 4-digit code to <strong><?=h($_SESSION['pw_reset_email'] ?? '')?></strong></p>
                </div>
                
                <?php if ($error): ?><div class="error"><?=h($error)?></div><?php endif; ?>
                
                <form method="post" action="?step=verify" id="otpForm">
                    <div class="otp-row">
                        <input inputmode="numeric" maxlength="1" name="d1" required autofocus>
                        <input inputmode="numeric" maxlength="1" name="d2" required>
                        <input inputmode="numeric" maxlength="1" name="d3" required>
                        <input inputmode="numeric" maxlength="1" name="d4" required>
                    </div>
                    <button class="btn" type="submit">Verify Code</button>
                </form>
                <a class="link" href="forgot-password-web.php?step=email">Resend code</a>

            <?php elseif ($step === 'reset'): ?>
                <div class="header">
                    <h2>Set New Password</h2>
                    <p>Create a strong password for your account</p>
                </div>
                
                <?php if ($error): ?><div class="error"><?=h($error)?></div><?php endif; ?>
                
                <form method="post" action="?step=reset">
                    <!-- New password with show/hide -->
                    <div class="form-group password-group">
                        <input type="password" id="password" name="password" placeholder="New password" required autocomplete="new-password">
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

                    <!-- Confirm password with show/hide -->
                    <div class="form-group password-group">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required autocomplete="new-password">
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

                    <button class="btn" type="submit">Update Password</button>
                </form>
                <a class="link" href="login.php">Back to Login</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Auto-advance OTP inputs & backspace behavior
    const boxes=[...document.querySelectorAll('.otp-row input')];
    boxes.forEach((b,i)=>{
        b.addEventListener('input',e=>{
            e.target.value=e.target.value.replace(/\D/g,'').slice(0,1);
            if(e.target.value && i<boxes.length-1){ boxes[i+1].focus(); }
        });
        b.addEventListener('keydown',e=>{
            if(e.key==='Backspace' && !b.value && i>0){ boxes[i-1].focus(); }
        });
    });
    if(boxes.length) boxes[0].focus();

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