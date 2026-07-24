<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Verify user exists in database
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User account not found. Please login again.']);
    exit();
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $recipe_name = trim($_POST['recipe_name']);
    $category = trim($_POST['category']);
    $prep_time = intval($_POST['prep_time']);
    $cook_time = intval($_POST['cook_time']);
    $servings = intval($_POST['servings']);
    $ingredients = trim($_POST['ingredients']);
    $instructions = trim($_POST['instructions']);
    
    // Validate required fields
    if (empty($recipe_name) || empty($category) || empty($ingredients) || empty($instructions)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Validate numeric fields
    if ($prep_time <= 0 || $cook_time < 0 || $servings <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter valid numbers for prep time, cook time, and servings']);
        exit();
    }
    
    // Handle file upload
    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['recipe_image'];
        
        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WebP images are allowed']);
            exit();
        }
        
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Image size must be less than 10MB']);
            exit();
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $image_name = uniqid('recipe_') . '.' . $ext;
        $upload_path = 'uploads/' . $image_name;
        
        // Create uploads directory if it doesn't exist
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Recipe image is required']);
        exit();
    }
    
    // Insert into uploaded_recipe table
    $stmt = $conn->prepare("INSERT INTO uploaded_recipe 
                            (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issiiisss", $user_id, $recipe_name, $category, $prep_time, $cook_time, $servings, $ingredients, $instructions, $image_name);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Recipe uploaded successfully']);
    } else {
        // Delete the uploaded image if database insert failed
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        echo json_encode(['success' => false, 'message' => 'Error uploading recipe: ' . $conn->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>