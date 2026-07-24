<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "chefai";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$recipe_id = $_GET['id'] ?? null;
if (!$recipe_id) {
    echo "<script>alert('No recipe ID provided.'); window.location.href = 'personalized.php';</script>";
    exit;
}

$recipe = $conn->query("SELECT * FROM recipes WHERE id = $recipe_id")->fetch_assoc();
$ingredients = $conn->query("SELECT * FROM recipe_ingredients WHERE recipe_id = $recipe_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Recipe</title>
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="add_recipe.css">
    
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Update Recipe</h2>
        <form action="save_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="recipe_id" value="<?= $recipe_id ?>">

            <div class="tabs">
                <div class="tab active">General Info</div>
            </div>

            <div class="form-row">
                <div class="form-fields">
                    <div class="form-group">
                        <input type="text" name="menu_name" value="<?= htmlspecialchars($recipe['menu_name']) ?>" placeholder="Menu Item Name">
                    </div>
                    <div class="form-group">
                        <select name="category">
                            <option value="">Select category</option>
                            <?php
                            $categories = ["Beef", "Seafood", "Pork", "Vegetables", "Chicken", "Pasta/Noodles", "Dessert"];
                            foreach ($categories as $cat) {
                                $selected = $recipe['category'] === $cat ? 'selected' : '';
                                echo "<option value=\"$cat\" $selected>$cat</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="image-upload-container">
                    <label for="file-input" class="featured-image" id="drop-area">
                        <?php if (!empty($recipe['featured_image'])): ?>
                            <img id="image-preview" src="<?= $recipe['featured_image'] ?>">
                        <?php else: ?>
                            <div class="upload-icon">📷</div>
                            <div class="upload-text">Click to upload featured image</div>
                            <img id="image-preview" style="display:none;">
                        <?php endif; ?>
                        <input type="file" name="featured_image" id="file-input" accept="image/*">
                    </label>
                </div>
            </div>

            <div class="ingredients-section">
                <h2>Ingredients</h2>
                <p>Add any additional ingredients & their cost</p>
                <div id="ingredients-wrapper">
                    <?php while ($row = $ingredients->fetch_assoc()): ?>
                        <div class="ingredient-inputs">
                            <input type="text" name="ingredient_description[]" value="<?= htmlspecialchars($row['description']) ?>" placeholder="Ingredient(s) description">
                            <input type="text" name="ingredient_cost[]" value="<?= $row['cost'] ?>" placeholder="Cost" style="width: 120px;">
                            <input type="text" name="ingredient_mass[]" value="<?= $row['mass'] ?>" placeholder="Mass" style="width: 120px;">
                            <select name="ingredient_unit[]">
                                <option value="Kilograms" <?= $row['unit'] == 'Kilograms' ? 'selected' : '' ?>>Kilograms</option>
                                <option value="Grams" <?= $row['unit'] == 'Grams' ? 'selected' : '' ?>>Grams</option>
                                <option value="Pound" <?= $row['unit'] == 'Pound' ? 'selected' : '' ?>>Pound</option>
                                <option value="Ounce" <?= $row['unit'] == 'Ounce' ? 'selected' : '' ?>>Ounce</option>
                            </select>
                        </div>
                    <?php endwhile; ?>
                </div>
                <button type="button" class="add-btn" onclick="addIngredientField()">+ Add Ingredient</button>
            </div>

            <button type="submit" class="create-btn">Save Changes</button>
        </form>
    </div>

    <script>
        function addIngredientField() {
            const wrapper = document.getElementById('ingredients-wrapper');
            const div = document.createElement('div');
            div.className = 'ingredient-inputs';
            div.innerHTML = `
                <input type="text" name="ingredient_description[]" placeholder="Ingredient(s) description">
                <input type="text" name="ingredient_cost[]" placeholder="Cost" style="width: 120px;">
                <input type="text" name="ingredient_mass[]" placeholder="Mass" style="width: 120px;">
                <select name="ingredient_unit[]">
                    <option value="Kilograms">Kilograms</option>
                    <option value="Grams">Grams</option>
                    <option value="Pound">Pound</option>
                    <option value="Ounce">Ounce</option>
                </select>`;
            wrapper.appendChild(div);
        }
    </script>
</body>
</html>
