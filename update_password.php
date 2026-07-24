<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Initialize variables
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } else {
        // Get current password hash from database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = "Password updated successfully!";
                    
                    // Send email notification (optional)
                    // $to = $_SESSION['email'];
                    // $subject = "Password Changed";
                    // $message = "Your password has been successfully changed.";
                    // mail($to, $subject, $message);
                    
                    // Redirect back to settings with success message
                    $_SESSION['password_update_success'] = $success;
                    header("Location: settings.php");
                    exit();
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
                
                $update_stmt->close();
            } else {
                $error = "Current password is incorrect!";
            }
        } else {
            $error = "User not found!";
        }
        
        $stmt->close();
    }
    
    // If there was an error, redirect back with error message
    if (!empty($error)) {
        $_SESSION['password_update_error'] = $error;
        header("Location: settings.php");
        exit();
    }
} else {
    // If someone tries to access this page directly
    header("Location: settings.php");
    exit();
}
?>