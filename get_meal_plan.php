<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid plan ID', 400);
    }

    $planId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $planId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Meal plan not found', 404);
    }

    $plan = $result->fetch_assoc();
    
    echo json_encode([
        'id' => $plan['id'],
        'name' => $plan['name'],
        'start_date' => $plan['start_date'],
        'end_date' => $plan['end_date'],
        'pax' => $plan['pax'],
        'plan_type' => $plan['plan_type'],
        'custom_meals' => $plan['custom_meals'],
        'food_restrictions' => $plan['food_restrictions'],
        'ai_analysis' => $plan['ai_analysis'],
        'created_at' => $plan['created_at']
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