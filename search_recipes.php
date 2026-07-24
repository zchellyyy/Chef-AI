<?php
// search_recipes.php
include 'db_connect.php'; // <-- make sure this points to your database connection file

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$q = "%" . trim($_GET['q']) . "%";

// Prepare SQL query
$stmt = $conn->prepare("SELECT id, recipe_name, category, prep_time, cook_time, servings, image_name 
                        FROM accepted_recipe
                        WHERE recipe_name LIKE ?");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

$recipes = [];
while ($row = $result->fetch_assoc()) {
    $recipes[] = $row;
}

echo json_encode($recipes);
$stmt->close();
$conn->close();
?>
