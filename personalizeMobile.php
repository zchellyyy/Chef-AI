<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("User not logged in. Current session: " . print_r($_SESSION, true));
}

$user_id = $_SESSION['user_id'];

// Handle bookmark removal
if (isset($_POST['bookmark_history'])) {
    $history_id = $_POST['bookmark_history'];
    $stmt = $conn->prepare("UPDATE chat_history SET is_bookmarked = FALSE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $history_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle saved image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bookmark'])) {
    $history_id = $_POST['delete_bookmark'];
    
    $stmt = $conn->prepare("DELETE FROM saved_images WHERE history_id = ?");
    $stmt->bind_param("i", $history_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Saved image deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting saved image: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle meal plan deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $plan_id = $_POST['delete_plan'];
    $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $plan_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle uploaded recipe deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_uploaded_recipe'])) {
    $recipe_id = $_POST['delete_uploaded_recipe'];
    
    $stmt = $conn->prepare("SELECT image_name FROM uploaded_recipe WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $recipe_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        $image_name = $recipe['image_name'];
        
        if (file_exists("uploads/" . $image_name)) {
            unlink("uploads/" . $image_name);
        }
        
        $stmt2 = $conn->prepare("DELETE FROM uploaded_recipe WHERE id = ? AND user_id = ?");
        $stmt2->bind_param("ii", $recipe_id, $user_id);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['success'] = "Recipe deleted successfully!";
    }
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle uploaded recipe update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_uploaded_recipe'])) {
    $recipe_id = $_POST['edit_recipe_id'];
    $recipe_name = $_POST['edit_recipe_name'];
    $category = $_POST['edit_category'];
    $prep_time = $_POST['edit_prep_time'];
    $cook_time = $_POST['edit_cook_time'];
    $servings = $_POST['edit_servings'];
    $ingredients = $_POST['edit_ingredients'];
    $instructions = $_POST['edit_instructions'];
    
    $check_stmt = $conn->prepare("SELECT id, status, image_name FROM uploaded_recipe WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $recipe_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $recipe = $check_result->fetch_assoc();
        $current_status = $recipe['status'];
        $current_image_name = $recipe['image_name'];
        
        $image_name = $current_image_name;
        $previous_image = null;
        
        if (isset($_FILES['edit_recipe_image']) && $_FILES['edit_recipe_image']['error'] === 0) {
            $image = $_FILES['edit_recipe_image'];
            $image_name = time() . '_' . basename($image['name']);
            $target_path = "uploads/" . $image_name;
            
            if (!move_uploaded_file($image['tmp_name'], $target_path)) {
                $_SESSION['error'] = "Error uploading image.";
                header("Location: personalizeMobile.php");
                exit();
            }
            
            $previous_image = $current_image_name;
        }
        
        $new_status = $current_status;
        $previous_status = null;
        $needs_approval = false;
        
        if ($current_status === 'accepted') {
            $new_status = 'pending';
            $previous_status = 'accepted';
            $needs_approval = true;
        }
        
        if ($previous_status) {
            if ($image_name !== $current_image_name) {
                $update_stmt = $conn->prepare("UPDATE uploaded_recipe SET 
                    recipe_name = ?, category = ?, prep_time = ?, cook_time = ?, servings = ?, 
                    ingredients = ?, instructions = ?, image_name = ?, status = ?, 
                    previous_status = ?, previous_image = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("ssiiissssssii", 
                    $recipe_name, $category, $prep_time, $cook_time, $servings, 
                    $ingredients, $instructions, $image_name, $new_status,
                    $previous_status, $current_image_name, $recipe_id, $user_id);
            } else {
                $update_stmt = $conn->prepare("UPDATE uploaded_recipe SET 
                    recipe_name = ?, category = ?, prep_time = ?, cook_time = ?, servings = ?, 
                    ingredients = ?, instructions = ?, status = ?, 
                    previous_status = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("ssiiissssii", 
                    $recipe_name, $category, $prep_time, $cook_time, $servings, 
                    $ingredients, $instructions, $new_status,
                    $previous_status, $recipe_id, $user_id);
            }
        } else {
            if ($image_name !== $current_image_name) {
                $update_stmt = $conn->prepare("UPDATE uploaded_recipe SET 
                    recipe_name = ?, category = ?, prep_time = ?, cook_time = ?, servings = ?, 
                    ingredients = ?, instructions = ?, image_name = ?, status = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("ssiiissssii", 
                    $recipe_name, $category, $prep_time, $cook_time, $servings, 
                    $ingredients, $instructions, $image_name, $new_status, 
                    $recipe_id, $user_id);
            } else {
                $update_stmt = $conn->prepare("UPDATE uploaded_recipe SET 
                    recipe_name = ?, category = ?, prep_time = ?, cook_time = ?, servings = ?, 
                    ingredients = ?, instructions = ?, status = ?, updated_at = NOW() 
                    WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("ssiiisssii", 
                    $recipe_name, $category, $prep_time, $cook_time, $servings, 
                    $ingredients, $instructions, $new_status, 
                    $recipe_id, $user_id);
            }
        }
        
        if ($update_stmt->execute()) {
            if ($needs_approval) {
                $_SESSION['success'] = "Recipe updated successfully! Since this recipe was previously accepted, it has been sent for admin approval again.";
            } else {
                $_SESSION['success'] = "Recipe updated successfully!";
            }
        } else {
            $_SESSION['error'] = "Error updating recipe: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = "Recipe not found or you don't have permission to edit it.";
    }
    
    $check_stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle meal plan update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meal_plan'])) {
    $plan_id = $_POST['edit_plan_id'];
    $name = $_POST['edit_name'];
    $start_date = $_POST['edit_start_date'];
    $end_date = $_POST['edit_end_date'];
    $duration_type = $_POST['edit_duration_type'];
    $pax = $_POST['edit_pax'];
    $plan_type = $_POST['edit_plan_type'];
    $custom_meals = $_POST['edit_custom_meals'] ?? '';
    $food_restrictions = $_POST['edit_food_restrictions'] ?? '';
    $ai_analysis = $_POST['edit_ai_analysis'] ?? '';
    $meal_times = isset($_POST['edit_meal_times']) ? json_encode($_POST['edit_meal_times']) : '[]';
    $estimated_budget = $_POST['edit_estimated_budget'] ?? '';
    
    $stmt = $conn->prepare("UPDATE meal_plans SET name = ?, start_date = ?, end_date = ?, duration_type = ?, pax = ?, plan_type = ?, custom_meals = ?, food_restrictions = ?, ai_analysis = ?, meal_times = ?, estimated_budget = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssssissssssii", $name, $start_date, $end_date, $duration_type, $pax, $plan_type, $custom_meals, $food_restrictions, $ai_analysis, $meal_times, $estimated_budget, $plan_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Meal plan updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating meal plan: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle meal plan save with AI analysis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal_plan'])) {
    $required_fields = ['name', 'start_date', 'duration_type', 'pax', 'plan_type'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['error'] = "Please fill in all required fields: " . implode(', ', $missing_fields);
        header("Location: personalizeMobile.php");
        exit();
    }
    
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $duration_type = $_POST['duration_type'];
    $pax = $_POST['pax'];
    $plan_type = $_POST['plan_type'];
    $custom_meals = $_POST['custom_meals'] ?? '';
    $food_restrictions = $_POST['food_restrictions'] ?? '';
    $ai_analysis = $_POST['ai_analysis'] ?? '';
    $meal_times = isset($_POST['meal_times']) ? json_encode($_POST['meal_times']) : '[]';
    $estimated_budget = $_POST['estimated_budget'] ?? '';
    
    // Calculate end date based on duration type
    $end_date = $start_date;
    if ($duration_type === '2_days') {
        $end_date = date('Y-m-d', strtotime($start_date . ' +1 day'));
    } elseif ($duration_type === '3_days') {
        $end_date = date('Y-m-d', strtotime($start_date . ' +2 days'));
    } elseif ($duration_type === 'custom' && !empty($_POST['end_date'])) {
        $end_date = $_POST['end_date'];
    }
    
    $stmt = $conn->prepare("INSERT INTO meal_plans (user_id, name, start_date, end_date, duration_type, pax, plan_type, custom_meals, food_restrictions, ai_analysis, meal_times, estimated_budget) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssissssss", $user_id, $name, $start_date, $end_date, $duration_type, $pax, $plan_type, $custom_meals, $food_restrictions, $ai_analysis, $meal_times, $estimated_budget);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Meal plan saved successfully!";
    } else {
        $_SESSION['error'] = "Error saving meal plan: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Handle AI response update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ai_response'])) {
    $history_id = $_POST['edit_history_id'];
    $new_response = $_POST['edit_ai_response'];
    
    $stmt = $conn->prepare("UPDATE chat_history SET response = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_response, $history_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "AI response updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating AI response: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: personalizeMobile.php");
    exit();
}

// Fetch data
$savedRecipes = [];
$stmt = $conn->prepare("SELECT * FROM save_recipe WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$savedRecipesResult = $stmt->get_result();
while ($row = $savedRecipesResult->fetch_assoc()) {
    $savedRecipes[] = $row;
}
$stmt->close();

$uploadedRecipes = [];
$stmt = $conn->prepare("SELECT ur.*, 
                       CASE 
                           WHEN ur.status = 'accepted' THEN 'accepted'
                           WHEN ur.status = 'rejected' THEN 'rejected' 
                           ELSE 'pending' 
                       END as recipe_status 
                       FROM uploaded_recipe ur 
                       WHERE ur.user_id = ? 
                       ORDER BY ur.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$uploadedResult = $stmt->get_result();
while ($row = $uploadedResult->fetch_assoc()) {
    $uploadedRecipes[] = $row;
}
$stmt->close();

$bookmarkedRecipes = [];
$stmt = $conn->prepare("SELECT * FROM chat_history WHERE user_id = ? AND is_bookmarked = TRUE ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookmarked = $stmt->get_result();
while ($row = $bookmarked->fetch_assoc()) {
    $bookmarkedRecipes[] = $row;
}
$stmt->close();

$mealPlans = [];
$stmt = $conn->prepare("SELECT * FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$plans = $stmt->get_result();
while ($row = $plans->fetch_assoc()) {
    $mealPlans[] = $row;
}
$stmt->close();

$savedImages = [];
$stmt = $conn->prepare("
    SELECT ch.*, si.saved_at, si.id as saved_image_id
    FROM chat_history ch 
    INNER JOIN saved_images si ON ch.id = si.history_id 
    WHERE ch.user_id = ? 
    ORDER BY si.saved_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $savedImages[] = $row;
}
$stmt->close();

// Pagination
$recipes_per_page = 6;
$total_recipes = count($savedRecipes);
$total_recipe_pages = ceil($total_recipes / $recipes_per_page);
$current_recipe_page = isset($_GET['recipe_page']) ? max(1, min($total_recipe_pages, intval($_GET['recipe_page']))) : 1;
$recipe_offset = ($current_recipe_page - 1) * $recipes_per_page;
$current_recipes = array_slice($savedRecipes, $recipe_offset, $recipes_per_page);

$uploaded_per_page = 6;
$total_uploaded = count($uploadedRecipes);
$total_uploaded_pages = ceil($total_uploaded / $uploaded_per_page);
$current_uploaded_page = isset($_GET['uploaded_page']) ? max(1, min($total_uploaded_pages, intval($_GET['uploaded_page']))) : 1;
$uploaded_offset = ($current_uploaded_page - 1) * $uploaded_per_page;
$current_uploaded = array_slice($uploadedRecipes, $uploaded_offset, $uploaded_per_page);

$bookmarks_per_page = 6;
$total_bookmarks = count($bookmarkedRecipes);
$total_bookmark_pages = ceil($total_bookmarks / $bookmarks_per_page);
$current_bookmark_page = isset($_GET['bookmark_page']) ? max(1, min($total_bookmark_pages, intval($_GET['bookmark_page']))) : 1;
$bookmark_offset = ($current_bookmark_page - 1) * $bookmarks_per_page;
$current_bookmarks = array_slice($bookmarkedRecipes, $bookmark_offset, $bookmarks_per_page);

$plans_per_page = 5;
$total_plans = count($mealPlans);
$total_plan_pages = ceil($total_plans / $plans_per_page);
$current_plan_page = isset($_GET['plan_page']) ? max(1, min($total_plan_pages, intval($_GET['plan_page']))) : 1;
$plan_offset = ($current_plan_page - 1) * $plans_per_page;
$current_plans = array_slice($mealPlans, $plan_offset, $plans_per_page);

$images_per_page = 6;
$total_images = count($savedImages);
$total_image_pages = ceil($total_images / $images_per_page);
$current_image_page = isset($_GET['image_page']) ? max(1, min($total_image_pages, intval($_GET['image_page']))) : 1;
$image_offset = ($current_image_page - 1) * $images_per_page;
$current_images = array_slice($savedImages, $image_offset, $images_per_page);

// Function to build pagination URL
function buildPaginationUrl($page_type, $page_number) {
    $params = $_GET;
    $params[$page_type] = $page_number;
    
    // Remove page parameters for other sections
    $other_pages = ['recipe_page', 'uploaded_page', 'bookmark_page', 'plan_page', 'image_page'];
    foreach ($other_pages as $other_page) {
        if ($other_page !== $page_type && isset($params[$other_page])) {
            unset($params[$other_page]);
        }
    }
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Personalized Meal | ChefAi</title>
    <style>
        :root {
            --primary-color: #C1856D;
            --secondary-color: #4ECDC4;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --neutral-color: #6C4E31;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
            --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #fff6ed !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-color);
            line-height: 1.2;
            overflow-x: hidden;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
        }
        
        .main-content {
            flex: 1;
            padding: 15px;
            width: 100%;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 15px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            width: 100%;
        }
        
        .section-title {
            color: var(--neutral-color);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .btn {
            background-color: #AB886D;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn:hover {
            background-color: #8A6D56;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            width: 100%;
        }
        
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            background: white;
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        .card-img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            cursor: pointer;
        }

        .card-body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            width: 100%;
        }

        .card-title {
            font-weight: 600;
            color: #6C4E31;
            margin-bottom: 5px;
            font-size: 0.85rem;
            line-height: 1.3;
            word-wrap: break-word;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 8px;
        }

        .card-content {
            max-height: 50px;
            overflow: hidden;
            margin-bottom: 8px;
            line-height: 1.4;
            font-size: 0.75rem;
            flex-grow: 1;
            word-wrap: break-word;
        }

        .card-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: auto;
        }
        
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-view {
            background-color: #FFDBB5;
            color: #6C4E31;
        }
        
        .btn-edit {
            background-color: #0d6efd;
            color: white;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge {
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .alert {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            width: 100%;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark-color);
            font-size: 0.75rem;
        }
        
        .page-link.active {
            background-color: var(--neutral-color);
            color: white;
            border-color: var(--neutral-color);
        }
        
        .page-link:hover:not(.active) {
            background-color: #f8f9fa;
        }
        
        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            overflow-y: auto;
            padding: 0;
            margin: 0;
            overscroll-behavior: contain;
        }

        .modal-content {
            background-color: #fff6ed;
            margin: 20px auto;
            border-radius: 15px;
            width: 95%;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
            border: 2px solid #C1856D;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, rgba(193, 133, 109, 0.9) 0%, rgba(108, 78, 49, 0.9) 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 13px 13px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1;
            min-height: 60px;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            word-wrap: break-word;
        }

        .close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 2;
            flex-shrink: 0;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 20px;
            max-height: calc(90vh - 60px);
            overflow-y: auto;
            overflow-x: hidden;
            background: #fff6ed;
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 15px;
            width: 100%;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--neutral-color);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            background: white;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(193, 133, 109, 0.2);
        }
        
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 10px;
            width: 100%;
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
            width: 100%;
        }
        
        /* Enhanced Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            overflow: hidden;
            padding: 0;
            margin: 0;
        }
        
        .modal-image-content {
            margin: auto;
            display: block;
            max-width: 95vw;
            max-height: 95vh;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #f1f1f1;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10001;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        /* Meal Plan Specific Styles */
        .meal-plan-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            width: 100%;
        }
        
        .meal-plan-details {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 8px;
            width: 100%;
        }
        
        .analysis-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            border-left: 4px solid var(--secondary-color);
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
        }
        
        .formatted-content {
            font-family: inherit;
            line-height: 1.6;
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            width: 100%;
        }
        
        /* Step Form */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .step {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .step.active {
            background-color: var(--neutral-color);
            color: white;
        }
        
        .step-line {
            flex: 1;
            height: 2px;
            background-color: #ddd;
            margin: 0 3px;
            max-width: 50px;
        }
        
        .step-line.active {
            background-color: var(--neutral-color);
        }
        
        .form-step {
            display: none;
            width: 100%;
        }
        
        .form-step.active {
            display: block;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            width: 100%;
            gap: 10px;
        }
        
        /* Duration Selection - Mobile Optimized */
        .duration-selection {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .duration-option {
            display: none;
        }
        
        .duration-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 8px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
            width: 100%;
        }
        
        .duration-label:hover {
            border-color: var(--primary-color);
        }
        
        .duration-option:checked + .duration-label {
            border-color: var(--neutral-color);
            background-color: rgba(108, 78, 49, 0.1);
            box-shadow: 0 5px 15px rgba(108, 78, 49, 0.1);
        }
        
        .duration-icon {
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: var(--neutral-color);
        }
        
        .duration-label h5 {
            margin: 3px 0;
            font-size: 0.85rem;
        }
        
        .duration-label p {
            font-size: 0.7rem;
            color: #666;
            margin: 0;
        }
        
        /* Meal Time Selection */
        .meal-time-selection {
            margin-bottom: 15px;
            width: 100%;
        }
        
        .meal-time-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
            width: 100%;
        }
        
        .meal-time-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meal-time-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .meal-time-option label {
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
        }
        
        .meal-time-select-all {
            font-size: 0.8rem;
            color: var(--primary-color);
            cursor: pointer;
            text-decoration: underline;
            margin-top: 5px;
        }
        
        /* Budget Field */
        .budget-field {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        
        .budget-field .form-control {
            flex: 1;
        }
        
        .budget-currency {
            font-weight: bold;
            color: var(--neutral-color);
            font-size: 0.9rem;
        }
        
        .plan-type-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .plan-type-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .plan-type-card:hover {
            border-color: var(--neutral-color);
        }
        
        .plan-type-card.selected {
            border-color: var(--neutral-color);
            background-color: rgba(108, 78, 49, 0.1);
        }
        
        .plan-type-card i {
            font-size: 1.3rem;
            margin-bottom: 6px;
            color: var(--neutral-color);
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 30px;
            width: 100%;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Alert Container */
        .alert-container {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1050;
            max-width: 300px;
            width: 90%;
        }
        
        /* Date field toggle */
        .date-field-toggle {
            display: none;
            animation: fadeIn 0.3s ease;
            width: 100%;
        }
        
        .date-field-toggle.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Expandable Analysis Overlay */
        .analysis-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
            backdrop-filter: blur(10px);
        }
        
        .analysis-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .expanded-analysis {
            background: white;
            border-radius: 10px;
            padding: 20px;
            max-width: 95%;
            max-height: 90%;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            width: 100%;
            position: relative;
        }
        
        .close-expanded {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--neutral-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            z-index: 10001;
        }
        
        /* Responsive adjustments */
        @media (min-width: 768px) {
            .grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
            
            .modal-content {
                width: 90%;
                max-width: 700px;
            }
            
            .duration-selection {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .plan-type-selector {
                flex-direction: row;
            }
            
            .form-row {
                flex-direction: row;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 0.85rem;
            }
            
            .card-img {
                height: 160px;
            }
        }
        
        @media (min-width: 1024px) {
            .grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .modal-content {
                max-width: 800px;
            }
            
            .main-content {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .modal-content {
                margin: 10px auto;
                width: 98%;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .duration-selection {
                grid-template-columns: 1fr;
            }
            
            .navigation-buttons {
                flex-direction: column;
            }
            
            .navigation-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'side-nav-mobile.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <!-- Alert messages container -->
            <div class="alert-container">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Bookmarked Recipes Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-star"></i> Bookmarked AI Recipes</h2>
                </div>
                
                <?php if (!empty($bookmarkedRecipes)): ?>
                    <div class="grid">
                        <?php foreach ($current_bookmarks as $item): 
                            $message = htmlspecialchars($item['message']);
                            $truncated_message = strlen($message) > 80 ? substr($message, 0, 80) . '...' : $message;
                            
                            $response_preview = !empty($item['response']) ? 
                                (strlen($item['response']) > 80 ? substr($item['response'], 0, 80) . '...' : $item['response']) : 
                                'No response content';
                            
                            $clean_preview = strip_tags($response_preview);
                        ?>
                            <div class="card">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                         class="card-img" 
                                         alt="Recipe image"
                                         onclick="openImageModal(this.src)"
                                         onerror="this.style.display='none'">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $truncated_message; ?></h5>
                                    <div class="card-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                                    </div>
                                    <div class="card-content">
                                        <?php echo nl2br(htmlspecialchars($clean_preview)); ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="action-btn btn-view" onclick="viewBookmarkedAI(<?php echo $item['id']; ?>)">View</button>
                                        <button class="action-btn btn-edit" onclick="editBookmarkedAI(<?php echo $item['id']; ?>)">Edit</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="bookmark_history" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_bookmark_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_bookmark_page > 1): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('bookmark_page', $current_bookmark_page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_bookmark_pages; $i++): ?>
                            <a class="page-link <?php echo $i == $current_bookmark_page ? 'active' : ''; ?>" href="<?php echo buildPaginationUrl('bookmark_page', $i); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($current_bookmark_page < $total_bookmark_pages): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('bookmark_page', $current_bookmark_page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">No bookmarked recipes yet. Click the star icon in chat history to bookmark recipes.</div>
                <?php endif; ?>
            </div>

            <!-- Saved Images Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-image"></i> Saved AI Generated Images</h2>
                </div>
                
                <?php if (!empty($savedImages)): ?>
                    <div class="grid">
                        <?php foreach ($current_images as $image): 
                            $description = !empty($image['description']) ? 
                                (strlen($image['description']) > 80 ? substr($image['description'], 0, 80) . '...' : $image['description']) : 
                                'No description available';
                        ?>
                            <div class="card">
                                <img src="uploads/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     class="card-img" 
                                     alt="Generated image"
                                     onclick="openImageModal(this.src)">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars(str_replace('Generate image: ', '', $image['message'])); ?></h5>
                                    <div class="card-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($image['saved_at'])); ?></span>
                                    </div>
                                    <div class="card-content">
                                        <?php echo nl2br(htmlspecialchars($description)); ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="action-btn btn-view" onclick="viewSavedImage(<?php echo $image['id']; ?>)">View</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_bookmark" value="<?php echo $image['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this saved image?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_image_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_image_page > 1): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('image_page', $current_image_page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_image_pages; $i++): ?>
                            <a class="page-link <?php echo $i == $current_image_page ? 'active' : ''; ?>" href="<?php echo buildPaginationUrl('image_page', $i); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($current_image_page < $total_image_pages): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('image_page', $current_image_page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        You haven't saved any images yet. Go to the chat and generate some images to save them here!
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Saved Recipes Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-bookmark"></i> Saved Recipes</h2>
                </div>
                
                <?php if (!empty($savedRecipes)): ?>
                    <div class="grid">
                        <?php foreach ($current_recipes as $recipe): ?>
                            <div class="card">
                                <img src="uploads/<?php echo htmlspecialchars($recipe['image_name']); ?>" class="card-img" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" onclick="openImageModal(this.src)">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                                    <div class="card-meta">
                                        <span><?php echo htmlspecialchars($recipe['category']); ?></span>
                                        <span><?php echo htmlspecialchars($recipe['prep_time'] + $recipe['cook_time']); ?> mins</span>
                                    </div>
                                    <div class="card-actions">
                                        <button class="action-btn btn-view" onclick="viewSavedRecipe(<?php echo $recipe['id']; ?>)">View</button>
                                        <button class="action-btn btn-delete" onclick="deleteSavedRecipe(<?php echo $recipe['id']; ?>)">Delete</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_recipe_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_recipe_page > 1): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('recipe_page', $current_recipe_page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_recipe_pages; $i++): ?>
                            <a class="page-link <?php echo $i == $current_recipe_page ? 'active' : ''; ?>" href="<?php echo buildPaginationUrl('recipe_page', $i); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($current_recipe_page < $total_recipe_pages): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('recipe_page', $current_recipe_page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">No saved recipes yet. Save recipes from the home page to see them here.</div>
                <?php endif; ?>
            </div>
            
            <!-- Uploaded Recipes Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-cloud-upload-alt"></i> Uploaded Recipes</h2>
                    <button class="btn" onclick="openModal('recipeUploadModal')">
                        <i class="fas fa-plus"></i> Upload
                    </button>
                </div>
                
                <?php if (!empty($uploadedRecipes)): ?>
                    <div class="grid">
                        <?php foreach ($current_uploaded as $recipe): ?>
                            <div class="card">
                                <img src="uploads/<?php echo htmlspecialchars($recipe['image_name']); ?>" class="card-img" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" onclick="openImageModal(this.src)">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                                    <div class="card-meta">
                                        <span><?php echo htmlspecialchars($recipe['category']); ?></span>
                                        <span><?php echo htmlspecialchars($recipe['prep_time'] + $recipe['cook_time']); ?> mins</span>
                                    </div>
                                    <div class="status-badge <?php echo 'status-' . $recipe['recipe_status']; ?>">
                                        <?php echo ucfirst($recipe['recipe_status']); ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="action-btn btn-view" onclick="viewUploadedRecipe(<?php echo $recipe['id']; ?>)">View</button>
                                        <button class="action-btn btn-edit" onclick="editUploadedRecipe(<?php echo $recipe['id']; ?>)">Edit</button>
                                        <button class="action-btn btn-delete" onclick="deleteUploadedRecipe(<?php echo $recipe['id']; ?>)">Delete</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_uploaded_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_uploaded_page > 1): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('uploaded_page', $current_uploaded_page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_uploaded_pages; $i++): ?>
                            <a class="page-link <?php echo $i == $current_uploaded_page ? 'active' : ''; ?>" href="<?php echo buildPaginationUrl('uploaded_page', $i); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($current_uploaded_page < $total_uploaded_pages): ?>
                            <a class="page-link" href="<?php echo buildPaginationUrl('uploaded_page', $current_uploaded_page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">No uploaded recipes yet. Upload your own recipes to see them here.</div>
                <?php endif; ?>
            </div>
            
            <!-- Meal Planner Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Meal Planner</h2>
                    <button class="btn" onclick="openModal('mealPlannerModal')">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                
                <div id="saved-meal-plans">
                    <?php if (!empty($mealPlans)): ?>
                        <?php foreach ($current_plans as $plan): 
                            $meal_times = !empty($plan['meal_times']) ? json_decode($plan['meal_times'], true) : [];
                            $budget = !empty($plan['estimated_budget']) ? '₱' . number_format($plan['estimated_budget'], 2) : 'Not specified';
                        ?>
                            <div class="meal-plan-card">
                                <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                <div class="meal-plan-details">
                                    <div><b>Start Date:</b> <?php echo date('M j, Y', strtotime($plan['start_date'])); ?></div>
                                    <?php if ($plan['end_date'] && $plan['end_date'] != $plan['start_date']): ?>
                                        <div><b>End Date:</b> <?php echo date('M j, Y', strtotime($plan['end_date'])); ?></div>
                                    <?php else: ?>
                                        <div><b>Duration:</b> Single Day</div>
                                    <?php endif; ?>
                                    <div><b>Duration:</b> <?php 
                                        switch($plan['duration_type']) {
                                            case '1_day': echo '1 Day'; break;
                                            case '2_days': echo '2 Days'; break;
                                            case '3_days': echo '3 Days'; break;
                                            case 'custom': echo 'Custom'; break;
                                            default: echo $plan['duration_type'];
                                        }
                                    ?></div>
                                    <div><b>Pax:</b> <?php echo $plan['pax']; ?></div>
                                    <div><b>Plan Type:</b> <?php echo htmlspecialchars($plan['plan_type']); ?></div>
                                    <?php if (!empty($meal_times)): ?>
                                        <div><b>Meal Times:</b> <?php echo implode(', ', $meal_times); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($plan['estimated_budget'])): ?>
                                        <div><b>Estimated Budget:</b> ₱<?php echo number_format($plan['estimated_budget'], 2); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($plan['food_restrictions'])): ?>
                                        <div><b>Food Restrictions:</b> <?php echo htmlspecialchars($plan['food_restrictions']); ?></div>
                                    <?php endif; ?>
                                    <div><small>Created: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></small></div>
                                </div>
                                <div class="card-actions">
                                    <button class="action-btn btn-view" onclick="viewMealPlan(<?php echo $plan['id']; ?>)">View</button>
                                    <button class="action-btn btn-edit" onclick="editMealPlan(<?php echo $plan['id']; ?>)">Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_plan" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" class="action-btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_plan_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_plan_page > 1): ?>
                                <a class="page-link" href="<?php echo buildPaginationUrl('plan_page', $current_plan_page - 1); ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_plan_pages; $i++): ?>
                                <a class="page-link <?php echo $i == $current_plan_page ? 'active' : ''; ?>" href="<?php echo buildPaginationUrl('plan_page', $i); ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($current_plan_page < $total_plan_pages): ?>
                                <a class="page-link" href="<?php echo buildPaginationUrl('plan_page', $current_plan_page + 1); ?>">Next</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No meal plans saved yet. Add one to get started!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expandable AI Analysis Overlay -->
    <div class="analysis-overlay" id="analysisOverlay">
        <div class="expanded-analysis" id="expandedAnalysisContent">
            <button class="close-expanded" onclick="closeExpandedAnalysis()">&times;</button>
            <div class="modal-content-text" id="expandedAnalysisText"></div>
        </div>
    </div>

    <!-- Image Modal for Full Size Viewing -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-image-content" id="modalImage">
    </div>

    <!-- Bookmarked AI View Modal -->
    <div id="bookmarkedAIModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-star"></i> Bookmarked AI Recipe</h3>
                <button class="close" onclick="closeModal('bookmarkedAIModal')">&times;</button>
            </div>
            <div class="modal-body" id="bookmarkedAIBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Bookmarked AI Edit Modal -->
    <div id="editBookmarkedAIModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit AI Response</h3>
                <button class="close" onclick="closeModal('editBookmarkedAIModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editBookmarkedAIForm" method="POST">
                    <input type="hidden" name="edit_history_id" id="editHistoryId">
                    <input type="hidden" name="update_ai_response" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Your Question</label>
                        <textarea class="form-control" id="editUserQuestion" rows="3" readonly></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">AI Response</label>
                        <textarea class="form-control" id="editAIResponse" name="edit_ai_response" rows="10" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Update Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Saved Image View Modal -->
    <div id="savedImageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-image"></i> Saved Image</h3>
                <button class="close" onclick="closeModal('savedImageModal')">&times;</button>
            </div>
            <div class="modal-body" id="savedImageBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Recipe Upload Modal -->
    <div id="recipeUploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-cloud-upload-alt"></i> Upload New Recipe</h3>
                <button class="close" onclick="closeModal('recipeUploadModal')">&times;</button>
            </div>
            <div class="modal-body">
               <form id="uploadRecipeForm" action="upload_recipe_mobile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="recipeName" class="form-label">Recipe Name</label>
                            <input type="text" class="form-control" id="recipeName" name="recipe_name" required>
                        </div>
                        <div class="form-group">
                            <label for="recipeCategory" class="form-label">Category</label>
                            <select class="form-control" id="recipeCategory" name="category" required>
                                <option value="Main Dish">Main Dish</option>
                                <option value="Side Dish">Side Dish</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Beverage">Beverage</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prepTime" class="form-label">Prep Time (minutes)</label>
                            <input type="number" class="form-control" id="prepTime" name="prep_time" required>
                        </div>
                        <div class="form-group">
                            <label for="cookTime" class="form-label">Cook Time (minutes)</label>
                            <input type="number" class="form-control" id="cookTime" name="cook_time" required>
                        </div>
                        <div class="form-group">
                            <label for="servings" class="form-label">Servings</label>
                            <input type="number" class="form-control" id="servings" name="servings" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ingredients" class="form-label">Ingredients</label>
                        <textarea class="form-control" id="ingredients" name="ingredients" rows="4" required></textarea>
                        <small style="color: #666;">Enter one ingredient per line</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions" class="form-label">Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipeImage" class="form-label">Recipe Image</label>
                        <input type="file" class="form-control" id="recipeImage" name="recipe_image" accept="image/*" required>
                    </div>
                    
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Recipe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Uploaded Recipe Modal -->
    <div id="editRecipeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Recipe</h3>
                <button class="close" onclick="closeModal('editRecipeModal')">&times;</button>
            </div>
            <div class="modal-body">
               <form id="editRecipeForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="edit_recipe_id" id="editRecipeId">
                    <input type="hidden" name="update_uploaded_recipe" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editRecipeName" class="form-label">Recipe Name</label>
                            <input type="text" class="form-control" id="editRecipeName" name="edit_recipe_name" required>
                        </div>
                        <div class="form-group">
                            <label for="editRecipeCategory" class="form-label">Category</label>
                            <select class="form-control" id="editRecipeCategory" name="edit_category" required>
                                <option value="Main Dish">Main Dish</option>
                                <option value="Side Dish">Side Dish</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Beverage">Beverage</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editPrepTime" class="form-label">Prep Time (minutes)</label>
                            <input type="number" class="form-control" id="editPrepTime" name="edit_prep_time" required>
                        </div>
                        <div class="form-group">
                            <label for="editCookTime" class="form-label">Cook Time (minutes)</label>
                            <input type="number" class="form-control" id="editCookTime" name="edit_cook_time" required>
                        </div>
                        <div class="form-group">
                            <label for="editServings" class="form-label">Servings</label>
                            <input type="number" class="form-control" id="editServings" name="edit_servings" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editIngredients" class="form-label">Ingredients</label>
                        <textarea class="form-control" id="editIngredients" name="edit_ingredients" rows="4" required></textarea>
                        <small style="color: #666;">Enter one ingredient per line</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editInstructions" class="form-label">Instructions</label>
                        <textarea class="form-control" id="editInstructions" name="edit_instructions" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRecipeImage" class="form-label">Recipe Image</label>
                        <input type="file" class="form-control" id="editRecipeImage" name="edit_recipe_image" accept="image/*">
                        <small style="color: #666;">Leave empty to keep current image</small>
                        <div id="currentImagePreview" class="mt-2"></div>
                    </div>

                    <div id="editRequestNotice" class="alert alert-info" style="display: none;">
                        <h4><i class="fas fa-info-circle"></i> Edit Request Notice</h4>
                        <p>This recipe was previously accepted by admin.</p>
                        <p>After editing, it will be sent for admin approval again.</p>
                        <p>Your changes will be reviewed before being published.</p>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Update Recipe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Saved Recipe View Modal -->
    <div id="savedRecipeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="savedRecipeTitle"></h3>
                <button class="close" onclick="closeModal('savedRecipeModal')">&times;</button>
            </div>
            <div class="modal-body" id="savedRecipeBody">
                <!-- Recipe details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Uploaded Recipe View Modal -->
    <div id="uploadedRecipeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="uploadedRecipeTitle"></h3>
                <button class="close" onclick="closeModal('uploadedRecipeModal')">&times;</button>
            </div>
            <div class="modal-body" id="uploadedRecipeBody">
                <!-- Recipe details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Meal Planner Modal -->
    <div id="mealPlannerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-calendar-plus"></i> Create Meal Plan</h3>
                <button class="close" onclick="closeModal('mealPlannerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="step-indicator">
                    <div class="step active">1</div>
                    <div class="step-line"></div>
                    <div class="step">2</div>
                    <div class="step-line"></div>
                    <div class="step">3</div>
                </div>
                
                <form id="mealPlannerForm" method="POST">
                    <input type="hidden" name="save_meal_plan" value="1">
                    <input type="hidden" name="duration_type" id="durationType" value="1_day">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-step active" id="step1">
                        <div class="form-group">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Family Weekend Meals">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required id="startDateInput">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Select Duration</label>
                            <div class="duration-selection">
                                <input type="radio" name="duration_radio" id="duration_1_day" value="1_day" class="duration-option" checked>
                                <label for="duration_1_day" class="duration-label">
                                    <div class="duration-icon">📅</div>
                                    <h5>1 Day</h5>
                                    <p>Single day plan</p>
                                </label>
                                
                                <input type="radio" name="duration_radio" id="duration_2_days" value="2_days" class="duration-option">
                                <label for="duration_2_days" class="duration-label">
                                    <div class="duration-icon">📅📅</div>
                                    <h5>2 Days</h5>
                                    <p>Weekend plans</p>
                                </label>
                                
                                <input type="radio" name="duration_radio" id="duration_3_days" value="3_days" class="duration-option">
                                <label for="duration_3_days" class="duration-label">
                                    <div class="duration-icon">📅📅📅</div>
                                    <h5>3 Days</h5>
                                    <p>Short trips</p>
                                </label>
                                
                                <input type="radio" name="duration_radio" id="duration_custom" value="custom" class="duration-option">
                                <label for="duration_custom" class="duration-label">
                                    <div class="duration-icon">📝</div>
                                    <h5>Custom</h5>
                                    <p>Choose dates</p>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Custom Date Range -->
                        <div class="date-field-toggle" id="customDateFields">
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" id="endDateInput">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pax (Number of People)</label>
                            <input type="number" name="pax" class="form-control" required min="1" placeholder="e.g., 4">
                        </div>
                        
                        <!-- Meal Time Selection -->
                        <div class="form-group meal-time-selection">
                            <label class="form-label">When will meals be served?</label>
                            <div class="meal-time-options">
                                <div class="meal-time-option">
                                    <input type="checkbox" id="meal_breakfast" name="meal_times[]" value="Breakfast">
                                    <label for="meal_breakfast">Breakfast</label>
                                </div>
                                <div class="meal-time-option">
                                    <input type="checkbox" id="meal_lunch" name="meal_times[]" value="Lunch">
                                    <label for="meal_lunch">Lunch</label>
                                </div>
                                <div class="meal-time-option">
                                    <input type="checkbox" id="meal_snack" name="meal_times[]" value="Snack">
                                    <label for="meal_snack">Snack</label>
                                </div>
                                <div class="meal-time-option">
                                    <input type="checkbox" id="meal_dinner" name="meal_times[]" value="Dinner">
                                    <label for="meal_dinner">Dinner</label>
                                </div>
                                <div class="meal-time-select-all" id="selectAllMealTimes">Select All</div>
                            </div>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('mealPlannerModal')">Cancel</button>
                            <button type="button" class="btn btn-primary next-step" data-next="step2">Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Plan Type Selection -->
                    <div class="form-step" id="step2">
                        <div class="form-group">
                            <label class="form-label">Select Plan Type</label>
                            <div class="plan-type-selector">
                                <div class="plan-type-card" data-type="custom">
                                    <i class="fas fa-pencil-alt"></i>
                                    <h5>Custom Meal</h5>
                                    <p>Input your meals and AI will assist you for arrangement</p>
                                </div>
                                <div class="plan-type-card" data-type="ai">
                                    <i class="fas fa-robot"></i>
                                    <h5>Ask with AI</h5>
                                    <p>Let AI create a meal plan for you</p>
                                </div>
                            </div>
                            <input type="hidden" name="plan_type" id="planType" required>
                        </div>
                        
                        <!-- Custom Meal Options -->
                        <div id="customMealOptions" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Your Custom Meals (5-10 recipes)</label>
                                <textarea class="form-control" name="custom_meals" rows="5" placeholder="List your preferred meals, one per line (e.g., Menudo, Adobo, Sinigang)"></textarea>
                                <small style="color: #666;">Enter at least 5 meals for a balanced plan</small>
                            </div>
                        </div>
                        
                        <!-- Food Restrictions -->
                        <div class="form-group">
                            <label class="form-label">Food Restrictions</label>
                            <textarea class="form-control" name="food_restrictions" rows="3" placeholder="List any allergies, religious restrictions, or dietary limitations"></textarea>
                        </div>
                        
                        <!-- Estimated Budget Field -->
                        <div class="form-group">
                            <label class="form-label">Estimated Budget (Optional)</label>
                            <div class="budget-field">
                                <span class="budget-currency">₱</span>
                                <input type="number" class="form-control" name="estimated_budget" placeholder="e.g., 1000" min="0" step="0.01">
                            </div>
                            <small style="color: #666;">Optional: Enter your estimated budget</small>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary prev-step" data-prev="step1">Back</button>
                            <button type="button" class="btn btn-primary next-step" data-next="step3">Next</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: AI Analysis -->
                    <div class="form-step" id="step3">
                        <div id="loadingSpinner" class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Generating meal plan analysis...</p>
                        </div>
                        
                        <div id="aiAnalysisContent" style="display: none;">
                            <div class="analysis-container" id="analysisTextContainer" onclick="expandAnalysis()">
                                <!-- AI analysis will be displayed here -->
                            </div>
                            
                            <textarea name="ai_analysis" id="hiddenAnalysisText" style="display: none;"></textarea>
                            
                            <div class="navigation-buttons">
                                <button type="button" class="btn btn-secondary prev-step" data-prev="step2">Back</button>
                                <button type="button" class="btn btn-danger" id="cancelPlanBtn">Cancel Plan</button>
                                <button type="submit" class="btn btn-success" name="save_meal_plan" id="savePlanBtn">Save Plan</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Meal Plan Modal -->
    <div id="editMealPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Meal Plan</h3>
                <button class="close" onclick="closeModal('editMealPlanModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editMealPlanForm" method="POST">
                    <input type="hidden" name="edit_plan_id" id="editPlanId">
                    <input type="hidden" name="update_meal_plan" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="edit_name" id="editPlanName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="edit_start_date" id="editStartDate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="edit_end_date" id="editEndDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration Type</label>
                        <select name="edit_duration_type" id="editDurationType" class="form-control" required>
                            <option value="1_day">1 Day</option>
                            <option value="2_days">2 Days</option>
                            <option value="3_days">3 Days</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pax</label>
                        <input type="number" name="edit_pax" id="editPax" class="form-control" required min="1">
                    </div>
                    
                    <!-- Edit Meal Time Selection -->
                    <div class="form-group meal-time-selection">
                        <label class="form-label">When will meals be served?</label>
                        <div class="meal-time-options" id="editMealTimeOptions">
                            <div class="meal-time-option">
                                <input type="checkbox" id="edit_meal_breakfast" name="edit_meal_times[]" value="Breakfast">
                                <label for="edit_meal_breakfast">Breakfast</label>
                            </div>
                            <div class="meal-time-option">
                                <input type="checkbox" id="edit_meal_lunch" name="edit_meal_times[]" value="Lunch">
                                <label for="edit_meal_lunch">Lunch</label>
                            </div>
                            <div class="meal-time-option">
                                <input type="checkbox" id="edit_meal_snack" name="edit_meal_times[]" value="Snack">
                                <label for="edit_meal_snack">Snack</label>
                            </div>
                            <div class="meal-time-option">
                                <input type="checkbox" id="edit_meal_dinner" name="edit_meal_times[]" value="Dinner">
                                <label for="edit_meal_dinner">Dinner</label>
                            </div>
                            <div class="meal-time-select-all" id="editSelectAllMealTimes">Select All</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Plan Type</label>
                        <select name="edit_plan_type" id="editPlanType" class="form-control" required>
                            <option value="custom">Custom Meal</option>
                            <option value="ai">Ask with AI</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="editCustomMealOptions">
                        <label class="form-label">Your Custom Meals (5-10 recipes)</label>
                        <textarea class="form-control" name="edit_custom_meals" id="editCustomMeals" rows="5" placeholder="List your preferred meals, one per line"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Food Restrictions</label>
                        <textarea class="form-control" name="edit_food_restrictions" id="editFoodRestrictions" rows="3" placeholder="List any allergies, restrictions, or dietary limitations"></textarea>
                    </div>
                    
                    <!-- Edit Estimated Budget Field -->
                    <div class="form-group">
                        <label class="form-label">Estimated Budget</label>
                        <div class="budget-field">
                            <span class="budget-currency">₱</span>
                            <input type="number" class="form-control" name="edit_estimated_budget" id="editEstimatedBudget" placeholder="e.g., 1000" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">AI Analysis</label>
                        <textarea class="form-control" name="edit_ai_analysis" id="editAiAnalysis" rows="10"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">Update Meal Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Meal Plan Modal -->
    <div id="viewMealPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="viewPlanTitle">Meal Plan Details</h3>
                <button class="close" onclick="closeModal('viewMealPlanModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewPlanBody">
                <div id="viewPlanDetails"></div>
                
                <div class="analysis-container" id="viewAiAnalysisContainer" onclick="expandAnalysis()">
                    <!-- AI analysis will be displayed here -->
                </div>
            </div>
        </div>
    </div>

<script>
    // Enhanced Modal functionality
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // Image Modal functionality
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close image modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && document.getElementById('imageModal').style.display === 'block') {
            closeImageModal();
        }
    });

    // Meal Time Selection - Select All functionality
    document.getElementById('selectAllMealTimes').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="meal_times[]"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        this.textContent = allChecked ? 'Select All' : 'Deselect All';
    });

    // Edit Meal Time Selection - Select All functionality
    document.getElementById('editSelectAllMealTimes').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('#editMealTimeOptions input[type="checkbox"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        this.textContent = allChecked ? 'Select All' : 'Deselect All';
    });

    // Duration selection functionality
    document.querySelectorAll('.duration-option').forEach(option => {
        option.addEventListener('change', function() {
            const durationType = this.value;
            document.getElementById('durationType').value = durationType;
            
            const customDateFields = document.getElementById('customDateFields');
            const endDateInput = document.getElementById('endDateInput');
            
            if (durationType === 'custom') {
                customDateFields.classList.add('active');
                endDateInput.required = true;
            } else {
                customDateFields.classList.remove('active');
                endDateInput.required = false;
                
                // Auto-set end date based on duration
                const startDate = document.getElementById('startDateInput').value;
                if (startDate) {
                    const start = new Date(startDate);
                    let endDate = new Date(start);
                    
                    if (durationType === '2_days') {
                        endDate.setDate(start.getDate() + 1);
                    } else if (durationType === '3_days') {
                        endDate.setDate(start.getDate() + 2);
                    } else {
                        endDate = start;
                    }
                    
                    document.getElementById('endDateInput').value = endDate.toISOString().split('T')[0];
                }
            }
        });
    });

    // Set today's date as default start date
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        
        if (startDateInput) startDateInput.value = today;
        if (endDateInput) endDateInput.value = today;
        
        // Set default meal times to Breakfast, Lunch, Dinner
        const breakfastCheckbox = document.getElementById('meal_breakfast');
        const lunchCheckbox = document.getElementById('meal_lunch');
        const dinnerCheckbox = document.getElementById('meal_dinner');
        
        if (breakfastCheckbox) breakfastCheckbox.checked = true;
        if (lunchCheckbox) lunchCheckbox.checked = true;
        if (dinnerCheckbox) dinnerCheckbox.checked = true;
    });

    // Plan type selection
    document.querySelectorAll('.plan-type-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.plan-type-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            const planType = this.getAttribute('data-type');
            document.getElementById('planType').value = planType;
            
            if (planType === 'custom') {
                document.getElementById('customMealOptions').style.display = 'block';
            } else {
                document.getElementById('customMealOptions').style.display = 'none';
            }
        });
    });

    // Multi-step form functionality
    document.querySelectorAll('.next-step').forEach(button => {
        button.addEventListener('click', function() {
            const nextStep = this.getAttribute('data-next');
            const currentStep = this.closest('.form-step').id;
            const currentStepNum = parseInt(currentStep.replace('step', ''));
            const nextStepNum = parseInt(nextStep.replace('step', ''));
            
            // Validate current step
            if (currentStep === 'step1') {
                if (!validateStep1()) return;
            }
            
            if (currentStep === 'step2') {
                if (!validateStep2()) return;
            }
            
            // If going to step 3, generate AI analysis
            if (nextStep === 'step3') {
                generateAnalysis();
            }
            
            // Update step indicators
            updateStepIndicators(currentStepNum, nextStepNum);
            
            // Show next step
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
            });
            document.getElementById(nextStep).classList.add('active');
        });
    });

    document.querySelectorAll('.prev-step').forEach(button => {
        button.addEventListener('click', function() {
            const prevStep = this.getAttribute('data-prev');
            const currentStep = this.closest('.form-step').id;
            const currentStepNum = parseInt(currentStep.replace('step', ''));
            const prevStepNum = parseInt(prevStep.replace('step', ''));
            
            // Update step indicators
            updateStepIndicators(currentStepNum, prevStepNum, false);
            
            // Show previous step
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
            });
            document.getElementById(prevStep).classList.add('active');
        });
    });

    // Function to update step indicators
    function updateStepIndicators(fromStep, toStep, forward = true) {
        const steps = document.querySelectorAll('.step');
        const stepLines = document.querySelectorAll('.step-line');
        
        if (forward) {
            // Moving forward
            for (let i = 0; i < toStep; i++) {
                if (i < toStep - 1) {
                    stepLines[i]?.classList.add('active');
                }
                
                if (i < toStep) {
                    steps[i]?.classList.remove('active');
                }
            }
            
            steps[toStep - 1]?.classList.add('active');
        } else {
            // Moving backward
            for (let i = toStep; i < steps.length; i++) {
                steps[i]?.classList.remove('active');
                if (i < stepLines.length) {
                    stepLines[i]?.classList.remove('active');
                }
            }
            
            steps[toStep - 1]?.classList.add('active');
        }
    }

    // Validation functions
    function validateStep1() {
        const form = document.getElementById('mealPlannerForm');
        const inputs = form.querySelectorAll('#step1 input[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value) {
                isValid = false;
                input.style.borderColor = 'red';
            } else {
                input.style.borderColor = '';
            }
        });
        
        // Validate at least one meal time is selected
        const mealTimeCheckboxes = form.querySelectorAll('input[name="meal_times[]"]');
        const atLeastOneChecked = Array.from(mealTimeCheckboxes).some(cb => cb.checked);
        
        if (!atLeastOneChecked) {
            isValid = false;
            alert('Please select at least one meal time');
            return false;
        }
        
        const startDate = new Date(document.querySelector('input[name="start_date"]').value);
        const endDate = new Date(document.querySelector('input[name="end_date"]').value);
        
        if (endDate < startDate) {
            isValid = false;
            alert('End date cannot be before start date');
            document.querySelector('input[name="end_date"]').style.borderColor = 'red';
        }
        
        if (!isValid) {
            alert('Please fill in all required fields correctly');
            return false;
        }
        
        return true;
    }

    function validateStep2() {
        if (!document.getElementById('planType').value) {
            alert('Please select a plan type');
            return false;
        }
        
        if (document.getElementById('planType').value === 'custom') {
            const customMeals = document.querySelector('textarea[name="custom_meals"]').value;
            const mealCount = customMeals.split('\n').filter(meal => meal.trim().length > 0).length;
            
            if (mealCount < 5) {
                alert('Please enter at least 5 meals for your custom plan');
                return false;
            }
        }
        
        return true;
    }

    // Cancel plan button
    document.getElementById('cancelPlanBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel this meal plan? All progress will be lost.')) {
            closeModal('mealPlannerModal');
            resetMealPlanForm();
        }
    });

    // Generate AI analysis
    function generateAnalysis() {
        const form = document.getElementById('mealPlannerForm');
        const formData = new FormData(form);
        
        document.getElementById('loadingSpinner').style.display = 'block';
        document.getElementById('aiAnalysisContent').style.display = 'none';
        
        const startDate = new Date(formData.get('start_date'));
        const endDate = new Date(formData.get('end_date'));
        const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        const durationType = formData.get('duration_type');
        const planType = formData.get('plan_type');
        const pax = formData.get('pax');
        
        // Get selected meal times
        const selectedMealTimes = [];
        document.querySelectorAll('input[name="meal_times[]"]:checked').forEach(cb => {
            selectedMealTimes.push(cb.value);
        });
        
        // Get estimated budget
        const estimatedBudget = formData.get('estimated_budget') || 0;
        
        // Get custom meals if any
        const customMealsArray = planType === 'custom' && formData.get('custom_meals') 
            ? formData.get('custom_meals').split('\n').filter(meal => meal.trim())
            : [];
        
        // Build dynamic prompt based on user inputs
        let prompt = `Create a detailed ${daysDiff}-day meal plan for ${pax} people with the following requirements:
- Plan name: ${formData.get('name')}
- Start date: ${formData.get('start_date')}
- End date: ${formData.get('end_date')}
- Duration: ${getDurationText(durationType)} (${daysDiff} day${daysDiff > 1 ? 's' : ''})
- Number of people (Pax): ${pax}
- Plan type: ${planType === 'custom' ? 'Custom meals provided by user' : 'AI-generated meal suggestions'}
- Meals to include: ${selectedMealTimes.join(', ')}`;
        
        // Add budget information if provided
        if (estimatedBudget > 0) {
            prompt += `\n- Estimated budget: ₱${parseFloat(estimatedBudget).toFixed(2)}`;
        }
        
        // Add custom meals if user selected custom plan
        if (planType === 'custom' && customMealsArray.length > 0) {
            prompt += `\n- User's preferred meals: ${customMealsArray.join(', ')}`;
            
            if (daysDiff === 1) {
                prompt += `\n- IMPORTANT: This is a SINGLE DAY buffet-style plan for ${pax} people.`;
                prompt += `\n- Create ONE meal entry for each selected meal time (${selectedMealTimes.join(', ')}).`;
                prompt += `\n- For example, if user selects "Lunch", create ONE "Lunch" entry with ALL ${customMealsArray.length} meals listed.`;
            } else {
                prompt += `\n- IMPORTANT: This is a ${daysDiff}-DAY plan. Distribute the ${customMealsArray.length} meals evenly across ${daysDiff} days.`;
                prompt += `\n- Divide meals evenly across selected meal times: ${selectedMealTimes.join(', ')}`;
            }
        } else if (planType === 'ai') {
            prompt += `\n- IMPORTANT: Suggest balanced Filipino meals for each day considering nutritional needs for the selected meal times (${selectedMealTimes.join(', ')}).`;
        }
        
        // Add food restrictions if provided
        if (formData.get('food_restrictions')) {
            prompt += `\n- Food restrictions: ${formData.get('food_restrictions')}`;
        }
        
        // Add specific instructions based on duration and budget
        if (estimatedBudget > 0) {
            prompt += `\n- The total cost should be within ₱${parseFloat(estimatedBudget).toFixed(2)}.`;
        }
        
        // Formatting instructions
        prompt += `\n\nCRITICAL FORMATTING RULES:

1. MEAL BREAKDOWN FORMAT:
   For EACH meal, use this EXACT format:
   [Meal Name]
   - [Ingredient 1]: [Quantity for ${pax} people]
   - [Ingredient 2]: [Quantity for ${pax} people]
   - [Ingredient 3]: [Quantity for ${pax} people]

2. INGREDIENT FORMATTING:
   - Use bullet points (hyphens) for all ingredients
   - Each ingredient on a new line
   - Format: "[Ingredient]: [Quantity]"
   - Example: "Chicken: 2.5 kg, cut into pieces"

3. SHOPPING LIST FORMAT:
   Group ingredients by category with CLEAN formatting:
   
   🛒 SHOPPING LIST:
   📦 Pantry Items:
   - Item 1: Quantity
   - Item 2: Quantity
   
   🥬 Fresh Produce:
   - Item 1: Quantity
   - Item 2: Quantity
   
   🥩 Meat/Protein:
   - Item 1: Quantity
   - Item 2: Quantity`;

        prompt += `\n\nPlease structure your response in this exact format:

<b>📋 MEAL PLAN OVERVIEW:</b>
[Provide a brief overview of the meal plan based on user requirements]

<b>📅 DAILY MEAL BREAKDOWN:</b>
${generateDayHeaders(daysDiff, startDate, selectedMealTimes, customMealsArray)}

<b>🛒 SHOPPING LIST:</b>
[Organize by category with CLEAN, UNIFORM formatting]

<b>📦 Pantry Items:</b>
- Item 1: Quantity needed for ${pax} people
- Item 2: Quantity needed for ${pax} people

<b>🥬 Fresh Produce:</b>
- Item 1: Quantity needed for ${pax} people
- Item 2: Quantity needed for ${pax} people

<b>🥩 Meat/Protein:</b>
- Item 1: Quantity needed for ${pax} people
- Item 2: Quantity needed for ${pax} people

<b>🥛 Dairy:</b>
- Item 1: Quantity needed for ${pax} people
- Item 2: Quantity needed for ${pax} people

<b>💰 MARKET ANALYSIS & PRICING:</b>
[Provide detailed market analysis including price trends and cost-saving tips]

<b>💡 PREPARATION TIPS:</b>
1. [Tip 1]
2. [Tip 2]

<b>⏰ MEAL DISTRIBUTION SCHEDULE:</b>
[Suggest optimal times for each meal type: ${selectedMealTimes.join(', ')}]

<b>💰 TOTAL ESTIMATED COST:</b> ₱[Calculate total amount based on ${pax} pax, ${daysDiff} days, and market prices]`;

        // Call the AI API with the dynamic prompt
        fetchAIResponse(prompt);
    }

    // Generate day headers
    function generateDayHeaders(daysDiff, startDate, mealTimes, customMeals) {
        let headers = '';
        const isCustomPlan = customMeals && customMeals.length > 0;
        
        for (let i = 1; i <= daysDiff; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + (i - 1));
            const formattedDate = currentDate.toISOString().split('T')[0];
            
            headers += `Day ${i} - ${formattedDate}\n`;
            
            if (daysDiff === 1 && isCustomPlan) {
                // SINGLE DAY: All meals listed
                headers += `\nBUFFET STYLE ${mealTimes.join(', ').toUpperCase()}:\n\n`;
                
                customMeals.forEach((meal, index) => {
                    headers += `${index + 1}. ${meal}\n`;
                    headers += `- Main Ingredients will be listed in shopping list section\n`;
                    headers += `- Estimated Cost: ₱[Cost for this meal]\n\n`;
                });
            } else if (isCustomPlan) {
                // MULTIPLE DAYS: Distribute meals evenly
                const mealsPerDay = Math.ceil(customMeals.length / daysDiff);
                const startIdx = (i - 1) * mealsPerDay;
                const endIdx = Math.min(startIdx + mealsPerDay, customMeals.length);
                const dayMeals = customMeals.slice(startIdx, endIdx);
                
                mealTimes.forEach(mealTime => {
                    headers += `\n${mealTime.toUpperCase()}:\n`;
                    dayMeals.forEach((meal, index) => {
                        headers += `${index + 1}. ${meal}\n`;
                    });
                    headers += `- Estimated Cost: ₱[amount]\n`;
                });
            } else {
                // AI-GENERATED
                mealTimes.forEach(mealTime => {
                    headers += `\n${mealTime.toUpperCase()}:\n`;
                    headers += `[Meal Name]\n`;
                    headers += `- Description: [Brief description]\n`;
                    headers += `- Estimated Cost: ₱[amount]\n`;
                });
            }
            
            headers += '\n---\n\n';
        }
        return headers;
    }

    function getDurationText(durationType) {
        switch(durationType) {
            case '1_day': return '1 Day';
            case '2_days': return '2 Days';
            case '3_days': return '3 Days';
            case 'custom': return 'Custom';
            default: return durationType;
        }
    }

    // Function to call the AI API
    function fetchAIResponse(prompt) {
        const apiKey = 'AIzaSyBLgKBfTIRzVFn-aj0riJjIMHubHKepYRs';
        const apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' + apiKey;
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contents: [{
                    parts: [{
                        text: prompt
                    }]
                }],
                generationConfig: {
                    temperature: 0.7,
                    maxOutputTokens: 2500,
                }
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('loadingSpinner').style.display = 'none';
            
            if (data.candidates && data.candidates[0].content.parts[0].text) {
                const analysisText = data.candidates[0].content.parts[0].text;
                document.getElementById('hiddenAnalysisText').value = analysisText;
                document.getElementById('analysisTextContainer').innerHTML = formatAIAnalysis(analysisText);
                document.getElementById('aiAnalysisContent').style.display = 'block';
            } else {
                throw new Error('Invalid response from AI');
            }
        })
        .catch(error => {
            console.error('Error fetching AI response:', error);
            document.getElementById('loadingSpinner').style.display = 'none';
            
            // Fallback response
            const form = document.getElementById('mealPlannerForm');
            const formData = new FormData(form);
            
            let fallbackResponse = `
<b>📋 MEAL PLAN OVERVIEW:</b>
This is a personalized meal plan created based on your requirements.

<b>📅 DAILY MEAL BREAKDOWN:</b>
[Day 1 - ${formData.get('start_date')}]
- Lunch: Buffet Lunch with Multiple Dishes
  - Description: A variety of dishes served buffet-style
  - Estimated Cost: ₱${formData.get('pax') * 200}

<b>🛒 SHOPPING LIST:</b>
<b>📦 Pantry Items:</b>
- Rice (2kg)
- Cooking oil (500ml)
- Soy sauce (250ml)

<b>🥬 Fresh Produce:</b>
- Onions (4 pieces)
- Garlic (1 bulb)
- Tomatoes (4 pieces)

<b>🥩 Meat/Protein:</b>
- Chicken or pork (1.5kg)

<b>💰 MARKET ANALYSIS & PRICING:</b>
<b>Current Market Trends:</b>
- Rice prices stable at ₱45-50/kg
- Chicken prices: ₱160-180/kg
- Pork prices: ₱220-250/kg

<b>💡 PREPARATION TIPS:</b>
1. Prepare ingredients in advance
2. Cook in larger batches

<b>⏰ MEAL DISTRIBUTION SCHEDULE:</b>
Lunch: 12-2 PM (buffet style)

<b>💰 TOTAL ESTIMATED COST:</b> ₱${formData.get('pax') * 200} for ${formData.get('pax')} people`;
            
            document.getElementById('hiddenAnalysisText').value = fallbackResponse;
            document.getElementById('analysisTextContainer').innerHTML = formatAIAnalysis(fallbackResponse);
            document.getElementById('aiAnalysisContent').style.display = 'block';
        });
    }

    // Helper function to format AI analysis text
    function formatAIAnalysis(text) {
        let formatted = text.replace(/\\r\\n/g, '\n')
                            .replace(/\\n/g, '\n')
                            .replace(/\\'/g, "'")
                            .replace(/\\"/g, '"');
        
        formatted = formatted.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        
        // Clean up formatting
        formatted = formatted.replace(/•/g, '-');
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
        formatted = formatted.replace(/\n/g, '<br>');
        formatted = formatted.replace(/^- (.*?)(<br>|$)/g, '&nbsp;&nbsp;&nbsp;&nbsp;- $1<br>');
        formatted = formatted.replace(/(🛒|📦|🥬|🥩|🥛|💰|💡|⏰) (.*?):/g, '<b>$1 $2:</b>');
        
        return formatted;
    }

    // Expandable AI Analysis functionality
    function expandAnalysis() {
        const analysisContainer = event.currentTarget;
        const analysisText = analysisContainer.innerHTML;
        
        document.getElementById('expandedAnalysisText').innerHTML = analysisText;
        document.getElementById('analysisOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeExpandedAnalysis() {
        document.getElementById('analysisOverlay').classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Reset meal plan form
    function resetMealPlanForm() {
        document.getElementById('mealPlannerForm').reset();
        document.querySelectorAll('.form-step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step1').classList.add('active');
        
        // Reset step indicators
        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.remove('active');
            if (index === 0) step.classList.add('active');
        });
        
        document.querySelectorAll('.step-line').forEach(line => {
            line.classList.remove('active');
        });
        
        // Reset plan type selection
        document.querySelectorAll('.plan-type-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.getElementById('planType').value = '';
        document.getElementById('customMealOptions').style.display = 'none';
        
        // Reset duration selection
        document.getElementById('duration_1_day').checked = true;
        document.getElementById('durationType').value = '1_day';
        document.getElementById('customDateFields').classList.remove('active');
        
        // Reset meal time selection
        document.querySelectorAll('input[name="meal_times[]"]').forEach(cb => {
            cb.checked = false;
        });
        document.getElementById('meal_breakfast').checked = true;
        document.getElementById('meal_lunch').checked = true;
        document.getElementById('meal_dinner').checked = true;
        document.getElementById('selectAllMealTimes').textContent = 'Select All';
        
        // Reset AI analysis
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('aiAnalysisContent').style.display = 'none';
        document.getElementById('analysisTextContainer').innerHTML = '';
        document.getElementById('hiddenAnalysisText').value = '';
    }

    // View Bookmarked AI Recipe
    function viewBookmarkedAI(historyId) {
        fetch('get_bookmarked_ai_mobile.php?id=' + historyId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const item = data.item;
                    let content = `
                        <div class="modal-content-text">
                            <h1>Bookmarked AI Recipe</h1>
                            
                            <div class="form-group">
                                <h2>Your Question:</h2>
                                <div class="alert alert-info">${item.message}</div>
                            </div>
                    `;
                    
                    if (item.image_path) {
                        content += `
                            <div class="form-group">
                                <h2>Generated Image:</h2>
                                <img src="uploads/${item.image_path}" class="card-img" alt="Generated image" onclick="openImageModal(this.src)" onerror="this.style.display='none'">
                            </div>
                        `;
                    }
                    
                    content += `
                            <div class="form-group">
                                <h2>AI Response:</h2>
                                <div class="formatted-content">${formatAIRecipeDetails(item.response || 'No response available')}</div>
                            </div>
                    `;
                    
                    if (item.description) {
                        content += `
                            <div class="form-group">
                                <h2>Image Description:</h2>
                                <div>${item.description}</div>
                            </div>
                        `;
                    }
                    
                    content += `</div>`;
                    
                    document.getElementById('bookmarkedAIBody').innerHTML = content;
                    openModal('bookmarkedAIModal');
                } else {
                    alert('Error loading bookmarked recipe: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading bookmarked recipe details. Please try again.');
            });
    }

    // Edit Bookmarked AI Recipe
    function editBookmarkedAI(historyId) {
        fetch('get_bookmarked_ai_mobile.php?id=' + historyId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const item = data.item;
                    document.getElementById('editHistoryId').value = item.id;
                    document.getElementById('editUserQuestion').value = item.message;
                    
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = item.response || '';
                    const plainTextResponse = tempDiv.textContent || tempDiv.innerText || '';
                    
                    document.getElementById('editAIResponse').value = plainTextResponse;
                    openModal('editBookmarkedAIModal');
                } else {
                    alert('Error loading bookmarked recipe for editing: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading bookmarked recipe for editing. Please try again.');
            });
    }

    // View Saved Image
    function viewSavedImage(historyId) {
        fetch('get_saved_image_mobile.php?id=' + historyId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const item = data.item;
                    let content = `
                        <div class="modal-content-text">
                            <h1>Saved Image</h1>
                            
                            <div class="form-group">
                                <h2>Image Prompt:</h2>
                                    <div class="alert alert-info">${item.message}</div>
                            </div>
                            
                            <div class="form-group">
                                <h2>Generated Image:</h2>
                                <img src="uploads/${item.image_path}" class="card-img" alt="Generated image" onclick="openImageModal(this.src)" onerror="this.style.display='none'">
                            </div>
                    `;
                    
                    if (item.description) {
                        content += `
                            <div class="form-group">
                                <h2>Image Description:</h2>
                                <div class="ai-image-description">${formatAIImageDescription(item.description)}</div>
                            </div>
                        `;
                    }
                    
                    content += `</div>`;
                    
                    document.getElementById('savedImageBody').innerHTML = content;
                    openModal('savedImageModal');
                } else {
                    alert('Error loading saved image: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading saved image details. Please try again.');
            });
    }

    // Helper function to format AI image description
    function formatAIImageDescription(text) {
        let formatted = text.replace(/\\r\\n/g, '\n')
                            .replace(/\\n/g, '\n')
                            .replace(/\\'/g, "'")
                            .replace(/\\"/g, '"');
        
        formatted = formatted.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
        formatted = formatted.replace(/\n/g, '<br>');
        
        return formatted;
    }

    // View saved recipe details
    function viewSavedRecipe(recipeId) {
        fetch('get_saved_recipe_mobile.php?id=' + recipeId)
            .then(response => response.json())
            .then(recipe => {
                if (recipe.success) {
                    document.getElementById('savedRecipeTitle').textContent = recipe.recipe_name;
                    
                    const ingredientsList = recipe.ingredients.split('\n').map(ing => 
                        `<li>${ing.trim()}</li>`).join('');
                    
                    const recipeContent = `
                        <div class="modal-content-text">
                            <h1>${recipe.recipe_name}</h1>
                            
                            <div class="form-group">
                                <img src="uploads/${recipe.image_name}" class="card-img" alt="${recipe.recipe_name}" onclick="openImageModal(this.src)">
                                <div class="form-group">
                                    <h2>Details</h2>
                                    <p><b>Category:</b> ${recipe.category}</p>
                                    <p><b>Prep Time:</b> ${recipe.prep_time} mins</p>
                                    <p><b>Cook Time:</b> ${recipe.cook_time} mins</p>
                                    <p><b>Servings:</b> ${recipe.servings}</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <h2>Ingredients</h2>
                                <ul>${ingredientsList}</ul>
                            </div>
                            <div class="form-group">
                                <h2>Instructions</h2>
                                <div>${recipe.instructions.replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('savedRecipeBody').innerHTML = recipeContent;
                    openModal('savedRecipeModal');
                } else {
                    alert('Error loading recipe: ' + recipe.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading recipe details. Please try again.');
            });
    }

    // View uploaded recipe details
    function viewUploadedRecipe(recipeId) {
        fetch('get_uploaded_recipe_mobile.php?id=' + recipeId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.recipe) {
                    const recipe = data.recipe;
                    document.getElementById('uploadedRecipeTitle').textContent = recipe.recipe_name;
                    
                    const ingredientsList = recipe.ingredients.split('\n')
                        .filter(ing => ing.trim() !== '')
                        .map(ing => `<li>${ing.trim()}</li>`)
                        .join('');
                    
                    let statusText = recipe.recipe_status || recipe.status || 'pending';
                    let statusClass = 'status-' + statusText;
                    let statusDisplay = statusText.charAt(0).toUpperCase() + statusText.slice(1);
                    
                    const statusBadge = `<span class="status-badge ${statusClass}">${statusDisplay}</span>`;
                    
                    const recipeContent = `
                        <div class="modal-content-text">
                            <h1>${recipe.recipe_name}</h1>
                            
                            <div class="form-group">
                                <img src="uploads/${recipe.image_name}" class="card-img" alt="${recipe.recipe_name}" onclick="openImageModal(this.src)" onerror="this.src='https://via.placeholder.com/400x200?text=Image+Not+Found'">
                                <div class="form-group">
                                    <h2>Details</h2>
                                    <p><b>Status:</b> ${statusBadge}</p>
                                    <p><b>Category:</b> ${recipe.category}</p>
                                    <p><b>Prep Time:</b> ${recipe.prep_time} mins</p>
                                    <p><b>Cook Time:</b> ${recipe.cook_time} mins</p>
                                    <p><b>Servings:</b> ${recipe.servings}</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <h2>Ingredients</h2>
                                ${ingredientsList ? `<ul>${ingredientsList}</ul>` : '<p>No ingredients listed</p>'}
                            </div>
                            <div class="form-group">
                                <h2>Instructions</h2>
                                <div>${recipe.instructions ? recipe.instructions.replace(/\n/g, '<br>') : 'No instructions provided'}</div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('uploadedRecipeBody').innerHTML = recipeContent;
                    openModal('uploadedRecipeModal');
                } else {
                    alert('Error loading recipe: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error fetching recipe:', error);
                alert('Error loading recipe details. Please check your connection and try again.');
            });
    }

    // Edit uploaded recipe
    function editUploadedRecipe(recipeId) {
        fetch('get_uploaded_recipe_mobile.php?id=' + recipeId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.recipe) {
                    const recipe = data.recipe;
                    
                    document.getElementById('editRecipeId').value = recipe.id;
                    document.getElementById('editRecipeName').value = recipe.recipe_name;
                    document.getElementById('editRecipeCategory').value = recipe.category;
                    document.getElementById('editPrepTime').value = recipe.prep_time;
                    document.getElementById('editCookTime').value = recipe.cook_time;
                    document.getElementById('editServings').value = recipe.servings;
                    document.getElementById('editIngredients').value = recipe.ingredients;
                    document.getElementById('editInstructions').value = recipe.instructions;
                    
                    const currentImagePreview = document.getElementById('currentImagePreview');
                    currentImagePreview.innerHTML = `
                        <p><b>Current Image:</b></p>
                        <img src="uploads/${recipe.image_name}" class="card-img" style="max-height: 150px;" alt="Current image" onclick="openImageModal(this.src)">
                    `;
                    
                    const editRequestNotice = document.getElementById('editRequestNotice');
                    let statusText = recipe.recipe_status || recipe.status || 'pending';
                    
                    if (statusText === 'accepted') {
                        editRequestNotice.style.display = 'block';
                        currentImagePreview.innerHTML += `
                            <div class="alert alert-info mt-2">
                                <i class="fas fa-exclamation-triangle"></i> 
                                This recipe was previously accepted. After editing, it will need admin approval again.
                            </div>
                        `;
                    } else {
                        editRequestNotice.style.display = 'none';
                    }
                    
                    openModal('editRecipeModal');
                } else {
                    alert('Error loading recipe for editing: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error fetching recipe for editing:', error);
                alert('Error loading recipe for editing. Please check your connection and try again.');
            });
    }

    // Delete saved recipe
    function deleteSavedRecipe(recipeId) {
        if (confirm('Are you sure you want to delete this saved recipe?')) {
            fetch('delete_saved_recipe_mobile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `recipe_id=${recipeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Recipe deleted successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to delete recipe');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error deleting recipe');
            });
        }
    }

    // Delete uploaded recipe
    function deleteUploadedRecipe(recipeId) {
        if (confirm('Are you sure you want to delete this uploaded recipe?')) {
            fetch('delete_uploaded_recipe_mobile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `recipe_id=${recipeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Uploaded recipe deleted successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to delete uploaded recipe');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error deleting uploaded recipe');
            });
        }
    }

    // View meal plan details
    function viewMealPlan(planId) {
        fetch('get_meal_plan_mobiles.php?id=' + planId)
            .then(response => response.json())
            .then(plan => {
                document.getElementById('viewPlanTitle').textContent = plan.name;
                
                let details = `
                    <div class="modal-content-text">
                        <h1>${plan.name}</h1>
                        
                        <div class="form-group">
                            <h2>Plan Details</h2>
                            <p><b>Start Date:</b> ${plan.start_date ? new Date(plan.start_date).toLocaleDateString() : 'Not specified'}</p>
                            <p><b>End Date:</b> ${plan.end_date ? new Date(plan.end_date).toLocaleDateString() : 'Not specified'}</p>
                            <p><b>Duration:</b> ${getDurationText(plan.duration_type)}</p>
                            <p><b>Pax:</b> ${plan.pax}</p>
                            <p><b>Plan Type:</b> ${plan.plan_type}</p>
                            ${plan.meal_times ? `<p><b>Meal Times:</b> ${JSON.parse(plan.meal_times).join(', ')}</p>` : ''}
                            ${plan.estimated_budget ? `<p><b>Estimated Budget:</b> ₱${parseFloat(plan.estimated_budget).toFixed(2)}</p>` : ''}
                            ${plan.food_restrictions ? `<p><b>Food Restrictions:</b> ${plan.food_restrictions}</p>` : ''}
                        </div>
                `;
                
                document.getElementById('viewPlanDetails').innerHTML = details;
                
                if (plan.ai_analysis) {
                    let aiAnalysis = plan.ai_analysis.replace(/\\r\\n/g, '\n')
                                                     .replace(/\\n/g, '\n')
                                                     .replace(/\\'/g, "'")
                                                     .replace(/\\"/g, '"')
                                                     .replace(/\r\n/g, '\n')
                                                     .replace(/\r/g, '\n');
                    document.getElementById('viewAiAnalysisContainer').innerHTML = `
                        <div class="modal-content-text">
                            <h2>AI Analysis</h2>
                            <div class="formatted-content">${formatAIAnalysis(aiAnalysis)}</div>
                        </div>
                    `;
                } else {
                    document.getElementById('viewAiAnalysisContainer').innerHTML = '<p>No AI analysis available for this plan.</p>';
                }
                
                openModal('viewMealPlanModal');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading meal plan details. Please try again.');
            });
    }
    
    // Edit meal plan
    function editMealPlan(planId) {
        fetch('get_meal_plan_mobiles.php?id=' + planId)
            .then(response => response.json())
            .then(plan => {
                document.getElementById('editPlanId').value = plan.id;
                document.getElementById('editPlanName').value = plan.name;
                document.getElementById('editStartDate').value = plan.start_date;
                document.getElementById('editEndDate').value = plan.end_date;
                document.getElementById('editDurationType').value = plan.duration_type;
                document.getElementById('editPax').value = plan.pax;
                document.getElementById('editPlanType').value = plan.plan_type;
                document.getElementById('editCustomMeals').value = plan.custom_meals;
                document.getElementById('editFoodRestrictions').value = plan.food_restrictions;
                document.getElementById('editEstimatedBudget').value = plan.estimated_budget || '';
                
                // Set meal times checkboxes
                const mealTimes = plan.meal_times ? JSON.parse(plan.meal_times) : [];
                document.querySelectorAll('#editMealTimeOptions input[type="checkbox"]').forEach(cb => {
                    cb.checked = mealTimes.includes(cb.value);
                });
                
                let aiAnalysis = plan.ai_analysis || '';
                aiAnalysis = aiAnalysis.replace(/\\r\\n/g, '\n')
                                      .replace(/\\n/g, '\n')
                                      .replace(/\\'/g, "'")
                                      .replace(/\\"/g, '"')
                                      .replace(/\r\n/g, '\n')
                                      .replace(/\r/g, '\n');
                
                document.getElementById('editAiAnalysis').value = aiAnalysis;
                
                if (plan.plan_type === 'custom') {
                    document.getElementById('editCustomMealOptions').style.display = 'block';
                } else {
                    document.getElementById('editCustomMealOptions').style.display = 'none';
                }
                
                openModal('editMealPlanModal');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading meal plan for editing. Please try again.');
            });
    }

    // Show/hide custom meal options when plan type changes in edit modal
    document.getElementById('editPlanType').addEventListener('change', function() {
        if (this.value === 'custom') {
            document.getElementById('editCustomMealOptions').style.display = 'block';
        } else {
            document.getElementById('editCustomMealOptions').style.display = 'none';
        }
    });

    // Helper function to format AI recipe details
    function formatAIRecipeDetails(text) {
        let formatted = text.replace(/\\r\\n/g, '\n')
                            .replace(/\\n/g, '\n')
                            .replace(/\\'/g, "'")
                            .replace(/\\"/g, '"');
        
        formatted = formatted.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
        formatted = formatted.replace(/\* Cooking Difficulty/g, '• <b>Cooking Difficulty</b>');
        formatted = formatted.replace(/\* Spice Level/g, '• <b>Spice Level</b>');
        formatted = formatted.replace(/\n/g, '<br>');
        formatted = formatted.replace(/<br><br>/g, '<br><br>');
        
        return formatted;
    }
</script>
</body>
</html>