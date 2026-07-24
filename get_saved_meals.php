<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid meal plan ID', 400);
    }

    $planId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];

    // Changed to query meal_plans table instead of saved_meals
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $planId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Meal plan not found', 404);
    }

    $mealPlan = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'meal_plan' => [
            'id' => $mealPlan['id'],
            'name' => $mealPlan['name'],
            'start_date' => $mealPlan['start_date'],
            'end_date' => $mealPlan['end_date'],
            'pax' => $mealPlan['pax'],
            'plan_type' => $mealPlan['plan_type'],
            'custom_meals' => $mealPlan['custom_meals'],
            'food_restrictions' => $mealPlan['food_restrictions'],
            'ai_analysis' => $mealPlan['ai_analysis'], // This is what you wanted
            'meal_purpose' => $mealPlan['meal_purpose'] ?? null,
            'budget' => $mealPlan['budget'] ?? null,
            'meal_times' => $mealPlan['meal_times'] ?? null,
            'created_at' => $mealPlan['created_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>