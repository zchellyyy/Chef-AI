<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    $name = $_POST['plan_name'];
    $date = $_POST['plan_date'];
    $days_serving = $_POST['days_serving'];
    $people_count = $_POST['people_count'];
    $main_dishes = $_POST['main_dishes'];
    $side_dishes = $_POST['side_dishes'];
    $drinks = $_POST['drinks'];
    $desserts = $_POST['desserts'];
    $dietary_preferences = $_POST['dietary_preferences'];

    $stmt = $conn->prepare("UPDATE meal_plans SET 
        name = ?, 
        date = ?, 
        days_serving = ?, 
        people_count = ?, 
        main_dishes = ?, 
        side_dishes = ?, 
        drinks = ?, 
        desserts = ?, 
        dietary_preferences = ? 
        WHERE id = ? AND user_id = ?");

    $stmt->bind_param("ssiiiiiissi", 
        $name, 
        $date, 
        $days_serving, 
        $people_count, 
        $main_dishes, 
        $side_dishes, 
        $drinks, 
        $desserts, 
        $dietary_preferences, 
        $plan_id, 
        $user_id);

    if ($stmt->execute()) {
        header("Location: personalize.php");
    } else {
        die("Error updating meal plan: " . $stmt->error);
    }
    
    $stmt->close();
} else {
    die("Invalid request method");
}
?>