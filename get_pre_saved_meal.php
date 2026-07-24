<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = $_SESSION['user_id'];
$meal_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM `pre-save-meal-plan` WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $meal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $meal = $result->fetch_assoc();
    echo json_encode(['success' => true, 'meal' => $meal]);
} else {
    echo json_encode(['success' => false, 'message' => 'Meal not found']);
}

$stmt->close();
$conn->close();
?>