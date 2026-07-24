<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'], $_POST['analysis'])) {
    $plan_id = $_POST['plan_id'];
    $analysis = $_POST['analysis'];

    $stmt = $conn->prepare("UPDATE meal_plans SET ai_analysis = ? WHERE id = ?");
    $stmt->bind_param("si", $analysis, $plan_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save analysis']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
