<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    if (!isset($_POST['recipe_id']) || !is_numeric($_POST['recipe_id'])) {
        throw new Exception('Invalid recipe ID', 400);
    }

    $recipeId = (int)$_POST['recipe_id'];
    $userId = $_SESSION['user_id'];

    // Verify the recipe belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM save_recipe WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $recipeId, $userId);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        throw new Exception('Recipe not found or access denied', 404);
    }
    $checkStmt->close();

    // Delete the recipe
    $stmt = $conn->prepare("DELETE FROM save_recipe WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $recipeId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete recipe', 500);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Recipe not found or already deleted', 404);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recipe deleted successfully'
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