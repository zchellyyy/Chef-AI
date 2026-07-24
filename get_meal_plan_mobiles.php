<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'No plan ID provided']));
}

$user_id = $_SESSION['user_id'];
$plan_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $plan_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $plan = $result->fetch_assoc();
    echo json_encode($plan);
} else {
    echo json_encode(['success' => false, 'message' => 'Meal plan not found']);
}

$stmt->close();
?>