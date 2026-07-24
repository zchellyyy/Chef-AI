<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Validate required fields
    $requiredFields = ['recipe_id', 'recipe_name', 'category', 'prep_time', 'cook_time', 'servings', 'ingredients', 'image_name'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    $userId = $_SESSION['user_id'];
    $recipeId = (int)$_POST['recipe_id'];

    // Check if recipe already exists in user's saved recipes
    $checkStmt = $conn->prepare("SELECT id FROM save_recipe WHERE user_id = ? AND recipe_name = ?");
    $checkStmt->bind_param("is", $userId, $_POST['recipe_name']);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        throw new Exception('You have already saved this recipe', 409);
    }

    // Insert the recipe into save_recipe table
    $stmt = $conn->prepare("INSERT INTO save_recipe (
        user_id, 
        recipe_name, 
        category, 
        prep_time, 
        cook_time, 
        servings, 
        ingredients, 
        instructions, 
        image_name
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "issiiisss",
        $userId,
        $_POST['recipe_name'],
        $_POST['category'],
        $_POST['prep_time'],
        $_POST['cook_time'],
        $_POST['servings'],
        $_POST['ingredients'],
        $_POST['instructions'],
        $_POST['image_name']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save recipe to database', 500);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recipe saved successfully'
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

if (isset($checkStmt)) $checkStmt->close();
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>