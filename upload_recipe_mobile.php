<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Verify user exists in database
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $_SESSION['error'] = "User account not found. Please login again.";
    header("Location: login.php");
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
        $_SESSION['error'] = "All fields are required";
        header("Location: personalizeMobile.php");
        exit();
    }
    
    // Handle file upload
    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['recipe_image'];
        
        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = "Only JPG, PNG, and GIF images are allowed";
            header("Location: personalizeMobile.php");
            exit();
        }
        
        if ($file['size'] > $max_size) {
            $_SESSION['error'] = "Image size must be less than 2MB";
            header("Location: personalizeMobile.php");
            exit();
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $image_name = uniqid('recipe_') . '.' . $ext;
        $upload_path = 'uploads/' . $image_name;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $_SESSION['error'] = "Failed to upload image";
            header("Location: personalizeMobile.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Recipe image is required";
        header("Location: personalizeMobile.php");
        exit();
    }
    
    // Insert into uploaded_recipe table
    $stmt = $conn->prepare("INSERT INTO uploaded_recipe 
                            (user_id, recipe_name, category, prep_time, cook_time, servings, ingredients, instructions, image_name, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issiiisss", $user_id, $recipe_name, $category, $prep_time, $cook_time, $servings, $ingredients, $instructions, $image_name);
    
    if ($stmt->execute()) {
        $_SESSION['success'];
    } else {
        $_SESSION['error'] = $conn->error;
        // Delete the uploaded image if database insert failed
        if (isset($upload_path)) {
            unlink($upload_path);
        }
    }
    
    $stmt->close();
    $conn->close();
    
    header("Location: personalizeMobile.php");
    exit();
} else {
    header("Location: personalizeMobile.php");
    exit();
}
?>