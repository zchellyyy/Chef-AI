<?php
// DB connection
$conn = new mysqli("localhost", "root", "", "chefai");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all recipes
$recipes = $conn->query("SELECT * FROM recipes ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compiled Recipes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .card { background: white; border: 1px solid #ccc; border-radius: 10px; padding: 15px; text-align: center; }
        .card img { width: 100%; height: 150px; object-fit: cover; border-radius: 6px; }
        .card h3 { margin: 10px 0 5px; }
        .card p { font-size: 14px; color: #666; }
        .view-btn {
            margin-top: 10px;
            background: #20c997;
            color: white;
            padding: 8px 14px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <h1>Compiled Recipes</h1>
    <div class="grid">
        <?php while ($recipe = $recipes->fetch_assoc()): ?>
            <div class="card">
                <?php if (!empty($recipe['featured_image'])): ?>
                    <img src="<?= $recipe['featured_image'] ?>" alt="Image">
                <?php else: ?>
                    <div style="height:150px;background:#eee;border-radius:6px;"></div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($recipe['menu_name']) ?></h3>
                <p><?= htmlspecialchars($recipe['category']) ?></p>
                <a class="view-btn" href="view_recipe.php?id=<?= $recipe['id'] ?>">View</a>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
