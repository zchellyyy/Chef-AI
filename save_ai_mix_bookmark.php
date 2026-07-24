<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get form data
$user_id = $_SESSION['user_id'];
$message = $_POST['message'] ?? '';
$response = $_POST['response'] ?? '';
$is_bookmarked = $_POST['is_bookmarked'] ?? '1';

// Validate required fields
if (empty($message) || empty($response)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Prepare SQL statement to insert into chat_history table
    $stmt = $conn->prepare("INSERT INTO chat_history (user_id, message, response, is_bookmarked, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("issi", $user_id, $message, $response, $is_bookmarked);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'AI Mix Match saved to bookmarks successfully!',
            'bookmark_id' => $stmt->insert_id
        ]);
    } else {
        throw new Exception('Failed to execute database query');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error saving AI mix match: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving to bookmarks: ' . $e->getMessage()
    ]);
}

$conn->close();
?>