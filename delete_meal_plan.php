<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

$plan_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$plan_id) {
    header("HTTP/1.1 400 Bad Request");
    exit();
}

$stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $plan_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("HTTP/1.1 200 OK");
} else {
    header("HTTP/1.1 404 Not Found");
}
$stmt->close();
?>