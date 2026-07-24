<?php
session_start();
include 'db_connect.php';
require_once 'mail_config_signup.php';

// If no pending signup, go back
if (!isset($_SESSION['pending_user'])) {
    header('Location: signup.php');
    exit();
}

$pending = $_SESSION['pending_user'];
$error = '';
$info  = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RESEND OTP
    if (isset($_POST['resend_otp'])) {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = time() + 60;

        $_SESSION['pending_user']['otp']        = $otp;
        $_SESSION['pending_user']['expires_at'] = $expires_at;
        $pending = $_SESSION['pending_user'];

        if (sendOtpEmail($pending['email'], $pending['username'], $otp)) {
            $info = "A new verification code has been sent to your email.";
        } else {
            $error = "Failed to resend verification code. Please try again.";
        }
    }

    // VERIFY OTP
    if (isset($_POST['verify_otp'])) {
        $enteredOtp = trim($_POST['otp'] ?? '');

        if ($enteredOtp === '') {
            $error = "Please enter the 6-digit code.";
        } elseif ($enteredOtp !== $pending['otp']) {
            $error = "Invalid verification code.";
        } elseif (time() > $pending['expires_at']) {
            $error = "Verification code has expired. Please resend a new code.";
        } else {
            // OTP is correct and not expired → create user in DB
            $insert = $conn->prepare("
                INSERT INTO users (username, email, password, email_verified, email_verified_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $insert->bind_param("sss", $pending['username'], $pending['email'], $pending['password']);

            if ($insert->execute()) {
                // Cleanup pending data
                unset($_SESSION['pending_user']);

                // Optional message for login page
                $_SESSION['success_message'] = "Email verified successfully! You can now log in.";
                header('Location: login.php');
                exit();
            } else {
                $error = "Failed to create your account. Please try signing up again.";
            }

            $insert->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email | ChefAI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #C1856D;
            --neutral-color: #6C4E31;
            --light-bg: #F6F1DE;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--light-bg);
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 15px;
            padding: 30px 25px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }
        h2 {
            color: var(--primary-color);
            margin-top: 0;
        }
        p {
            margin: 6px 0;
        }
        .email-label {
            font-weight: 600;
            color: var(--neutral-color);
        }
        .timer {
            margin-top: 10px;
            font-weight: 600;
            color: var(--neutral-color);
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 1rem;
            text-align: center;
            letter-spacing: 6px;
            margin-top: 15px;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(193, 133, 109, 0.2);
        }
        .btn {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            background-color: var(--primary-color);
            color: #fff;
            transition: 0.2s;
        }
        .btn:hover {
            background-color: var(--neutral-color);
        }
        .btn-secondary {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            width: 100%;
        }
        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: #fff;
        }
        .error, .info {
            margin-top: 10px;
            font-size: 0.9rem;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
        }
        .error {
            background: #ffebee;
            color: #b71c1c;
            border-left: 4px solid #b71c1c;
        }
        .info {
            background: #e3f2fd;
            color: #0d47a1;
            border-left: 4px solid #0d47a1;
        }
        .resend-wrapper {
            margin-top: 12px;
        }
        .resend-wrapper form {
            margin: 0;
        }
        .disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Email Verification</h2>
    <p>We sent a 6-digit verification code to:</p>
    <p class="email-label"><?php echo htmlspecialchars($pending['email']); ?></p>
    <p>Enter the code below to complete your Chef-AI registration.</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($info)): ?>
        <div class="info"><?php echo htmlspecialchars($info); ?></div>
    <?php endif; ?>

    <div class="timer">
        Time remaining: <span id="countdown">60</span>s
    </div>

    <form method="POST">
        <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="••••••" required>
        <button type="submit" name="verify_otp" id="verifyBtn" class="btn">Verify Code</button>
    </form>

    <div class="resend-wrapper">
        <form method="POST">
            <button type="submit" name="resend_otp" id="resendBtn" class="btn-secondary disabled" disabled>
                Resend Code
            </button>
        </form>
    </div>
</div>

<script>
// 60-second countdown
let remaining = 60;
const countdownEl = document.getElementById('countdown');
const resendBtn   = document.getElementById('resendBtn');

const timer = setInterval(() => {
    remaining--;
    countdownEl.textContent = remaining;

    if (remaining <= 0) {
        clearInterval(timer);
        countdownEl.textContent = 0;
        // Enable resend when timer finishes
        resendBtn.disabled = false;
        resendBtn.classList.remove('disabled');
    }
}, 1000);
</script>
</body>
</html>
