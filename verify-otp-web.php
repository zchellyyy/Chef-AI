<?php
session_start();
require 'db_connect.php';

$email = $_SESSION['pw_reset_email'] ?? null;
if (!$email) { header('Location: forgot-password-web.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    // Get user
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

        if (!$reset)                         $err = 'Code not found or already used.';
        elseif (new DateTime() > new DateTime($reset['expires_at'])) $err = 'Code expired.';
        elseif ($reset['attempts'] >= 5)     $err = 'Too many attempts. Request a new code.';
        elseif (!password_verify($code, $reset['code_hash'])) {
            $upd = $conn->prepare("UPDATE password_resets SET attempts=attempts+1 WHERE id=?");
            $upd->bind_param('i', $reset['id']);
            $upd->execute();
            $upd->close();
            $err = 'Invalid code.';
        } else {
            $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE id={$reset['id']}")->execute();
            $_SESSION['pw_reset_user'] = $user['id'];
            header('Location: reset-password-web.php');
            exit;
        }
    } else {
        $err = 'Invalid request.';
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Verify Code</title></head>
<body>
  <h2>Verify OTP</h2>
  <p>We sent a 6-digit code to <strong><?=htmlspecialchars($email)?></strong>.</p>
  <?php if ($err): ?><p style="color:red"><?=$err?></p><?php endif; ?>
  <form method="post">
    <input type="text" name="code" pattern="\d{6}" maxlength="6" placeholder="123456" required>
    <button type="submit">Verify</button>
  </form>
  <p><a href="forgot-password-web.php">Resend code</a></p>
</body></html>
