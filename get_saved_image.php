<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid ID', 400);
    }

    $historyId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT ch.*, si.saved_at, si.id as saved_image_id 
        FROM chat_history ch 
        INNER JOIN saved_images si ON ch.id = si.history_id 
        WHERE ch.id = ? AND ch.user_id = ?
    ");
    $stmt->bind_param("ii", $historyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Saved image not found', 404);
    }

    $item = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'item' => [
            'id' => $item['id'],
            'message' => $item['message'],
            'response' => $item['response'],
            'image_path' => $item['image_path'],
            'description' => $item['description'],
            'saved_at' => $item['saved_at'],
            'created_at' => $item['created_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>