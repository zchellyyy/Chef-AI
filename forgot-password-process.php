<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$username = trim($_POST['username']);
$email = trim($_POST['email']);

if (empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
    exit();
}

// Check if user exists with the provided username and email
$stmt = $conn->prepare("SELECT id, email, username FROM users WHERE username = ? AND email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Username and email combination not found']);
    exit();
}

$user = $result->fetch_assoc();

// Generate a unique reset token
$reset_token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

// Store the token in the database
$stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
$stmt->bind_param("ssi", $reset_token, $expires, $user['id']);

if ($stmt->execute()) {
    // Send email with reset link
    $reset_link = "https://chefaiph.site/reset-password.php?token=" . $reset_token;
    $subject = "ChefAI Password Reset Request";
    $message = "
    <html>
    <head>
        <title>Password Reset Request</title>
    </head>
    <body>
        <h2>ChefAI Password Reset</h2>
        <p>Hello " . htmlspecialchars($user['username']) . ",</p>
        <p>You have requested to reset your password. Click the link below to set a new password:</p>
        <p><a href='" . $reset_link . "' style='background-color: #C9B194; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this reset, please ignore this email.</p>
        <br>
        <p>Best regards,<br>ChefAI Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@chefaiph.site" . "\r\n";
    
    if (mail($user['email'], $subject, $message, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Password reset link sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}

$stmt->close();
$conn->close();
?>