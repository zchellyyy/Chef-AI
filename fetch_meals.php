<?php
$conn = new mysqli('localhost', 'username', 'password', 'chefai_db');
$result = $conn->query("SELECT * FROM meal_plans ORDER BY id DESC");

while ($row = $result->fetch_assoc()) {
    echo '<div class="saved-meal-card">';
    echo '<h5>' . $row['serving_place'] . '</h5>';
    echo '<p>Days: ' . $row['days_serving'] . ' | People: ' . $row['people_count'] . '</p>';
    echo '</div>';
}

$conn->close();
?>