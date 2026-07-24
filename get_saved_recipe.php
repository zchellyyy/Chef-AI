<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid recipe ID', 400);
    }

    $recipeId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM save_recipe WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $recipeId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Recipe not found', 404);
    }

    $recipe = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'id' => $recipe['id'],
        'recipe_name' => $recipe['recipe_name'],
        'category' => $recipe['category'],
        'prep_time' => $recipe['prep_time'],
        'cook_time' => $recipe['cook_time'],
        'servings' => $recipe['servings'],
        'ingredients' => $recipe['ingredients'],
        'instructions' => $recipe['instructions'],
        'image_name' => $recipe['image_name'],
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