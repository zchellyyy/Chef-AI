<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT message, response, created_at, image_path FROM chat_history 
                       WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($item);
} else {
    header("HTTP/1.1 404 Not Found");
    exit();
}
?>