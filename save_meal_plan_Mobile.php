<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'User not logged in']));
}

// Verify user exists
$user_id = $_SESSION['user_id'];
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $user_id);
$check_user->execute();
$check_user->store_result();

if ($check_user->num_rows === 0) {
    $_SESSION['error'] = "User account not found!";
    header("Location: personalizeMobile.php");
    exit();
}
$check_user->close();

// Get form data - using the correct field names from your form
$name = $conn->real_escape_string($_POST['name'] ?? '');
$start_date = $conn->real_escape_string($_POST['start_date'] ?? '');
$end_date = $conn->real_escape_string($_POST['end_date'] ?? '');
$pax = intval($_POST['pax'] ?? 0);
$plan_type = $conn->real_escape_string($_POST['plan_type'] ?? '');
$custom_meals = $conn->real_escape_string($_POST['custom_meals'] ?? '');
$food_restrictions = $conn->real_escape_string($_POST['food_restrictions'] ?? '');
$ai_analysis = $conn->real_escape_string($_POST['ai_analysis'] ?? '');

// Validate required fields
if (empty($name) || empty($start_date) || empty($end_date) || empty($pax) || empty($plan_type)) {
    $_SESSION['error'] = "Please fill in all required fields";
    header("Location: personalize.php");
    exit();
}

// Validate date range
if (strtotime($end_date) <= strtotime($start_date)) {
    $_SESSION['error'] = "End date must be after start date";
    header("Location: personalize.php");
    exit();
}

// Validate custom meals if custom plan is selected
if ($plan_type === 'custom') {
    $meal_count = count(array_filter(array_map('trim', explode("\n", $custom_meals))));
    if ($meal_count < 1) {
        $_SESSION['error'] = "Please enter at least 5 meals for your custom plan";
        header("Location: personalizeMobile.php");
        exit();
    }
}

try {
    $stmt = $conn->prepare("INSERT INTO meal_plans 
        (user_id, name, start_date, end_date, pax, plan_type, custom_meals, food_restrictions, ai_analysis) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "isssissss",
        $user_id,
        $name,
        $start_date,
        $end_date,
        $pax,
        $plan_type,
        $custom_meals,
        $food_restrictions,
        $ai_analysis
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Meal plan saved successfully!";
    } else {
        $_SESSION['error'] = "Error saving meal plan: " . $stmt->error;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

$conn->close();
header("Location: personalizeMobile.php");
exit();
?>