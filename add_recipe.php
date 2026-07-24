<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Recipe</title>
    <link rel="stylesheet" href="add_recipe.css">
</head>
<body>
    <form method="POST" action="save_recipe.php" enctype="multipart/form-data">
        <div class="container">
            <h2>Add Recipe</h2>
            <div class="tabs">
                <div class="tab active">General Info</div>
            </div>
            
            <div class="form-row">
                <div class="form-fields">
                    <div class="form-group">
                        <input type="text" name="menu_name" placeholder="Menu Item Name" required>
                    </div>
                    <div class="form-group">
                        <select name="category" required>
                            <option value="">Select category</option>
                            <option value="Beef">Beef</option>
                            <option value="Seafood">Seafood</option>
                            <option value="Pork">Pork</option>
                            <option value="Vegetables">Vegetables</option>
                            <option value="Chicken">Chicken</option>
                            <option value="Pasta/Noodles">Pasta/Noodles</option>
                            <option value="Dessert">Dessert</option>
                        </select>
                    </div>
                </div>
                
                <div class="image-upload-container">
                    <label for="file-input" class="featured-image" id="drop-area">
                        <div class="upload-icon">📷</div>
                        <div class="upload-text">Click to upload featured image</div>
                        <img id="image-preview">
                        <input type="file" name="featured_image" id="file-input" accept="image/*">
                    </label>
                </div>
            </div>
            
            <div class="ingredients-section">
                <h2>Ingredients</h2>
                <p>Add any additional ingredients & their cost</p>
                <div class="ingredient-list">
                    <div class="ingredient-row">
                        <input type="text" name="ingredient_description[]" placeholder="Ingredient(s) description" required>
                        <input type="text" name="ingredient_cost[]" placeholder="Cost" style="width: 120px;" required>
                        <input type="text" name="ingredient_mass[]" placeholder="Mass" style="width: 120px;" required>
                        <select name="ingredient_unit[]" required>
                            <option value="Kilograms">Kilograms</option>
                            <option value="Grams">Grams</option>
                            <option value="Pound">Pound</option>
                            <option value="Ounce">Ounce</option>
                        </select>
                    </div>
                </div>
                <button class="add-btn">+ Add Ingredient</button>
            </div>
            
            <button class="create-btn" type="submit">Create</button>
        </div>
    </form>

    <script>
        const fileInput = document.getElementById('file-input');
        const dropArea = document.getElementById('drop-area');
        const imagePreview = document.getElementById('image-preview');
        const uploadText = document.querySelector('.upload-text');
        const uploadIcon = document.querySelector('.upload-icon');

        dropArea.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) handleImageUpload(file);
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.style.borderColor = '#20c997';
                dropArea.style.backgroundColor = 'rgba(32, 201, 151, 0.1)';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.style.borderColor = '#ccc';
                dropArea.style.backgroundColor = 'transparent';
            });
        });

        dropArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            if (file && file.type.match('image.*')) handleImageUpload(file);
        });

        function handleImageUpload(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                uploadText.style.display = 'none';
                uploadIcon.style.display = 'none';
            }
            reader.readAsDataURL(file);
        }

        document.querySelector('.add-btn').addEventListener('click', function(e) {
            e.preventDefault();
            const list = document.querySelector('.ingredient-list');
            const row = list.querySelector('.ingredient-row');
            const clone = row.cloneNode(true);
            clone.querySelectorAll('input, select').forEach(el => el.value = '');
            list.appendChild(clone);
        });
    </script>
</body>
</html>