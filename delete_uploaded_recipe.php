<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if (!isset($_POST['recipe_id']) || empty($_POST['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recipe ID is required']);
    exit();
}

$recipe_id = intval($_POST['recipe_id']);
$user_id = $_SESSION['user_id'];

try {
    // First get the image name to delete the file
    $stmt = $conn->prepare("SELECT image_name FROM uploaded_recipe WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $recipe_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        $image_name = $recipe['image_name'];
        
        // Delete the image file
        if (file_exists("uploads/" . $image_name)) {
            unlink("uploads/" . $image_name);
        }
        
        // Delete the recipe from database
        $stmt2 = $conn->prepare("DELETE FROM uploaded_recipe WHERE id = ? AND user_id = ?");
        $stmt2->bind_param("ii", $recipe_id, $user_id);
        
        if ($stmt2->execute()) {
            echo json_encode(['success' => true, 'message' => 'Recipe deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete recipe from database']);
        }
        $stmt2->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Recipe not found or you do not have permission to delete it']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>