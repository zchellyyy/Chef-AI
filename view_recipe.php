<?php
$conn = new mysqli("localhost", "root", "", "chefai");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$recipe_id = $_GET['id'] ?? 0;
$recipe = $conn->query("SELECT * FROM recipes WHERE id = $recipe_id")->fetch_assoc();
$ingredients = $conn->query("SELECT * FROM recipe_ingredients WHERE recipe_id = $recipe_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($recipe['menu_name']) ?> - Details</title>
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="save_recipe.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Chef-AI</div>
        <ul class="nav-links">
            <li><a href="homepage.php">Home</a></li>
            <li><a href="personalized.php">Personalized</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="services.php">Services</a></li>
            <li><a href="contact.php">Contact</a></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="recipe-container">
        <h2><?= htmlspecialchars($recipe['menu_name']) ?> (<?= htmlspecialchars($recipe['category']) ?>)</h2>
        <?php if (!empty($recipe['featured_image'])): ?>
            <img src="<?= $recipe['featured_image'] ?>" alt="Recipe Image">
        <?php endif; ?>

        <h3>Ingredients</h3>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Cost</th>
                    <th>Mass</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $ingredients->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td>₱<?= number_format($row['cost'], 2) ?></td>
                        <td><?= htmlspecialchars($row['mass']) ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <a href="update_recipe.php?id=<?= $recipe['id'] ?>" class="update-btn">Update Recipe</a>
    </div>
</body>
</html>
