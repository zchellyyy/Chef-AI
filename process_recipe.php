<?php
include 'db_connect.php';

header('Content-Type: application/json');

$recipeId = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($recipeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid recipe ID']);
    exit;
}

try {
    switch ($action) {
        case 'accept':
            // Accept new recipe submission
            $conn->begin_transaction();
            
            // Get the recipe data from uploaded_recipe
            $stmt = $conn->prepare("SELECT * FROM uploaded_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $recipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$recipe) {
                throw new Exception("Recipe not found");
            }
            
            // Insert into accepted_recipe
            $stmt = $conn->prepare("INSERT INTO accepted_recipe 
                                   (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issiiisss", 
                $recipe['user_id'],
                $recipe['recipe_name'],
                $recipe['category'],
                $recipe['prep_time'],
                $recipe['cook_time'],
                $recipe['servings'],
                $recipe['ingredients'],
                $recipe['instructions'],
                $recipe['image_name']
            );
            $stmt->execute();
            $stmt->close();
            
            // Update status in uploaded_recipe
            $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'accepted' WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'reject':
            // Reject new recipe submission
            $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No rows affected']);
            }
            $stmt->close();
            break;
            
        case 'accept_edit':
            // Accept edit request - update the existing accepted recipe
            $conn->begin_transaction();
            
            // Get the updated recipe data from uploaded_recipe
            $stmt = $conn->prepare("SELECT * FROM uploaded_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $updatedRecipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$updatedRecipe) {
                throw new Exception("Edit request not found");
            }
            
            // Check if this is truly an edit request (has previous_status = 'accepted')
            if (empty($updatedRecipe['previous_status']) || $updatedRecipe['previous_status'] !== 'accepted') {
                throw new Exception("This is not a valid edit request");
            }
            
            // Find the original accepted recipe by user_id and recipe_name
            $stmt = $conn->prepare("SELECT id FROM accepted_recipe WHERE user_id = ? AND recipe_name = ?");
            $stmt->bind_param("is", $updatedRecipe['user_id'], $updatedRecipe['recipe_name']);
            $stmt->execute();
            $acceptedRecipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($acceptedRecipe) {
                // Update existing accepted recipe with new data
                $stmt = $conn->prepare("UPDATE accepted_recipe SET 
                                       category = ?, prep_time = ?, cook_time = ?, servings = ?, 
                                       ingredients = ?, instructions = ?, image_name = ?, created_at = NOW()
                                       WHERE id = ?");
                $stmt->bind_param("siiisssi",
                    $updatedRecipe['category'],
                    $updatedRecipe['prep_time'],
                    $updatedRecipe['cook_time'],
                    $updatedRecipe['servings'],
                    $updatedRecipe['ingredients'],
                    $updatedRecipe['instructions'],
                    $updatedRecipe['image_name'],
                    $acceptedRecipe['id']
                );
                $stmt->execute();
                $stmt->close();
            } else {
                // Fallback: Insert as new accepted recipe if original not found
                $stmt = $conn->prepare("INSERT INTO accepted_recipe 
                                       (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("issiiisss", 
                    $updatedRecipe['user_id'],
                    $updatedRecipe['recipe_name'],
                    $updatedRecipe['category'],
                    $updatedRecipe['prep_time'],
                    $updatedRecipe['cook_time'],
                    $updatedRecipe['servings'],
                    $updatedRecipe['ingredients'],
                    $updatedRecipe['instructions'],
                    $updatedRecipe['image_name']
                );
                $stmt->execute();
                $stmt->close();
            }
            
            // Update the uploaded_recipe record to mark it as accepted and clear previous_status
            $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'accepted', previous_status = NULL WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'reject_edit':
            // Reject edit request - delete the edit request and keep original accepted recipe
            $conn->begin_transaction();
            
            // Get the edit request data
            $stmt = $conn->prepare("SELECT * FROM uploaded_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $editRequest = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$editRequest) {
                throw new Exception("Edit request not found");
            }
            
            // Check if this is truly an edit request
            if (empty($editRequest['previous_status']) || $editRequest['previous_status'] !== 'accepted') {
                throw new Exception("This is not a valid edit request");
            }
            
            // Handle image cleanup if needed
            if (isset($editRequest['previous_image']) && !empty($editRequest['previous_image'])) {
                // If a new image was uploaded for the edit, delete it
                if ($editRequest['image_name'] !== $editRequest['previous_image']) {
                    if (file_exists("uploads/" . $editRequest['image_name'])) {
                        unlink("uploads/" . $editRequest['image_name']);
                    }
                }
            }
            
            // Update the uploaded_recipe to restore it to accepted status
            // We set status to 'accepted' and clear previous_status to indicate it's no longer an edit request
            $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'accepted', previous_status = NULL WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'reject_accepted':
            // Move from accepted_recipe to rejected status in uploaded_recipe
            $conn->begin_transaction();
            
            // Get the recipe data from accepted_recipe
            $stmt = $conn->prepare("SELECT * FROM accepted_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $recipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$recipe) {
                throw new Exception("Recipe not found in accepted recipes");
            }
            
            // Update or insert into uploaded_recipe with rejected status
            // First check if a record already exists in uploaded_recipe
            $stmt = $conn->prepare("SELECT id FROM uploaded_recipe WHERE user_id = ? AND recipe_name = ?");
            $stmt->bind_param("is", $recipe['user_id'], $recipe['recipe_name']);
            $stmt->execute();
            $existingRecipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existingRecipe) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'rejected', previous_status = 'accepted' WHERE id = ?");
                $stmt->bind_param("i", $existingRecipe['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO uploaded_recipe 
                                       (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, status, previous_status, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'rejected', 'accepted', NOW())");
                $stmt->bind_param("issiiisss", 
                    $recipe['user_id'],
                    $recipe['recipe_name'],
                    $recipe['category'],
                    $recipe['prep_time'],
                    $recipe['cook_time'],
                    $recipe['servings'],
                    $recipe['ingredients'],
                    $recipe['instructions'],
                    $recipe['image_name']
                );
                $stmt->execute();
                $stmt->close();
            }
            
            // Delete from accepted_recipe
            $stmt = $conn->prepare("DELETE FROM accepted_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'accept_rejected':
            // Move from rejected to accepted
            $conn->begin_transaction();
            
            // Get the recipe data from uploaded_recipe
            $stmt = $conn->prepare("SELECT * FROM uploaded_recipe WHERE id = ? AND status = 'rejected'");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $recipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$recipe) {
                throw new Exception("Recipe not found or not rejected");
            }
            
            // Insert into accepted_recipe
            $stmt = $conn->prepare("INSERT INTO accepted_recipe 
                                   (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issiiisss", 
                $recipe['user_id'],
                $recipe['recipe_name'],
                $recipe['category'],
                $recipe['prep_time'],
                $recipe['cook_time'],
                $recipe['servings'],
                $recipe['ingredients'],
                $recipe['instructions'],
                $recipe['image_name']
            );
            $stmt->execute();
            $stmt->close();
            
            // Update status in uploaded_recipe
            $stmt = $conn->prepare("UPDATE uploaded_recipe SET status = 'accepted', previous_status = NULL WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            // Move from accepted_recipe to archive_recipe
            $conn->begin_transaction();
            
            // Get the recipe data from accepted_recipe
            $stmt = $conn->prepare("SELECT * FROM accepted_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $recipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$recipe) {
                throw new Exception("Recipe not found");
            }
            
            // Insert into archive_recipe
            $stmt = $conn->prepare("INSERT INTO archive_recipe 
                                   (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, deleted_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issiiisss", 
                $recipe['user_id'],
                $recipe['recipe_name'],
                $recipe['category'],
                $recipe['prep_time'],
                $recipe['cook_time'],
                $recipe['servings'],
                $recipe['ingredients'],
                $recipe['instructions'],
                $recipe['image_name']
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete from accepted_recipe
            $stmt = $conn->prepare("DELETE FROM accepted_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_rejected':
            // Permanently delete a rejected recipe from uploaded_recipe
            $stmt = $conn->prepare("DELETE FROM uploaded_recipe WHERE id = ? AND status = 'rejected'");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No rows affected - recipe not found or not rejected']);
            }
            $stmt->close();
            break;
            
        case 'restore':
            // Move from archive_recipe back to accepted_recipe
            $conn->begin_transaction();
            
            // Get the recipe data from archive_recipe
            $stmt = $conn->prepare("SELECT * FROM archive_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $recipe = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$recipe) {
                throw new Exception("Recipe not found in archive");
            }
            
            // Insert into accepted_recipe
            $stmt = $conn->prepare("INSERT INTO accepted_recipe 
                                   (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issiiisss", 
                $recipe['user_id'],
                $recipe['recipe_name'],
                $recipe['category'],
                $recipe['prep_time'],
                $recipe['cook_time'],
                $recipe['servings'],
                $recipe['ingredients'],
                $recipe['instructions'],
                $recipe['image_name']
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete from archive_recipe
            $stmt = $conn->prepare("DELETE FROM archive_recipe WHERE id = ?");
            $stmt->bind_param("i", $recipeId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>