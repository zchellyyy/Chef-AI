<?php
include 'db_connect.php'; // Include your database connection file

header('Content-Type: application/json'); // Set header to indicate JSON response

if (isset($_GET['id'])) {
    $recipeId = $_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM save_recipe WHERE id = ?");
    $stmt->bind_param("i", $recipeId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        echo json_encode($recipe); // Return recipe data as JSON
    } else {
        echo json_encode(['error' => 'Recipe not found.']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'No recipe ID provided.']);
}

$conn->close();
?>