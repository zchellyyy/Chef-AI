<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Initialize variables
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get current user information from database (not just session)
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$profile_image = $user['profile_image'] ?? '';

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != UPLOAD_ERR_NO_FILE) {
    $target_dir = "uploads/profile_images/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $_SESSION['error_message'] = "Failed to create upload directory.";
            header("Location: settings.php");
            exit();
        }
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $user_id . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error_message'] = "File is not an image.";
        header("Location: settings.php");
        exit();
    } elseif ($_FILES["profile_image"]["size"] > 2000000) {
        $_SESSION['error_message'] = "Sorry, your file is too large (max 2MB).";
        header("Location: settings.php");
        exit();
    } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        header("Location: settings.php");
        exit();
    } else {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            if (!empty($profile_image) && file_exists($profile_image) && strpos($profile_image, 'default-profile.jpg') === false) {
                unlink($profile_image);
            }
            
            $relative_path = "uploads/profile_images/" . $new_filename;
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->bind_param("si", $relative_path, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['profile_image'] = $relative_path;
                $_SESSION['success_message'] = "Profile image updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating profile image: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Sorry, there was an error uploading your file. Check folder permissions.";
        }
        header("Location: settings.php");
        exit();
    }
}

// Handle remove profile image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_image'])) {
    if (!empty($profile_image) && file_exists($profile_image)) {
        unlink($profile_image);
    }
    
    $stmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $_SESSION['profile_image'] = '';
        $_SESSION['success_message'] = "Profile image removed successfully!";
    } else {
        $_SESSION['error_message'] = "Error removing profile image: " . $conn->error;
    }
    $stmt->close();
    header("Location: settings.php");
    exit();
}

// Handle profile update (username)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    
    if (!empty($new_username)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error_message'] = "Username is already taken by another user.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $new_username, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $_SESSION['success_message'] = "Profile updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating profile: " . $conn->error;
            }
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Username cannot be empty.";
    }
    header("Location: settings.php");
    exit();
}

// Get current user preferences
$preferences = [
    'cuisine_style' => 'Filipino',
    'dietary_restrictions' => [],
    'spice_level' => 'medium',
    'cooking_time' => 'any',
    'ingredient_preferences' => [],
    'ai_creativity' => 'balanced',
    'mix_n_match_cuisines' => ['Filipino']
];

$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $preferences = $result->fetch_assoc();
    // Decode JSON fields
    if (!empty($preferences['dietary_restrictions'])) {
        $preferences['dietary_restrictions'] = json_decode($preferences['dietary_restrictions'], true);
    }
    if (!empty($preferences['mix_n_match_cuisines'])) {
        $preferences['mix_n_match_cuisines'] = json_decode($preferences['mix_n_match_cuisines'], true);
    }
    if (!empty($preferences['ingredient_preferences'])) {
        $preferences['ingredient_preferences'] = json_decode($preferences['ingredient_preferences'], true);
    }
}
$stmt->close();

// Handle preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $preferences = [
        'cuisine_style' => $_POST['cuisine_style'] ?? 'Filipino',
        'dietary_restrictions' => isset($_POST['dietary_restrictions']) ? $_POST['dietary_restrictions'] : [],
        'spice_level' => $_POST['spice_level'] ?? 'medium',
        'cooking_time' => $_POST['cooking_time'] ?? 'any',
        'ingredient_preferences' => isset($_POST['ingredient_preferences']) ? explode(',', $_POST['ingredient_preferences']) : [],
        'ai_creativity' => $_POST['ai_creativity'] ?? 'balanced',
        'mix_n_match_cuisines' => isset($_POST['mix_n_match_cuisines']) ? $_POST['mix_n_match_cuisines'] : ['Filipino']
    ];
    
    // Check if preferences exist
    $stmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        // Update existing preferences
        $stmt = $conn->prepare("UPDATE user_preferences SET 
            cuisine_style = ?,
            dietary_restrictions = ?,
            spice_level = ?,
            cooking_time = ?,
            ingredient_preferences = ?,
            ai_creativity = ?,
            mix_n_match_cuisines = ?
            WHERE user_id = ?");
    } else {
        // Insert new preferences
        $stmt = $conn->prepare("INSERT INTO user_preferences (
            cuisine_style,
            dietary_restrictions,
            spice_level,
            cooking_time,
            ingredient_preferences,
            ai_creativity,
            mix_n_match_cuisines,
            user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    }
    
    // Encode arrays to JSON
    $dietary_json = json_encode($preferences['dietary_restrictions']);
    $ingredients_json = json_encode($preferences['ingredient_preferences']);
    $mix_n_match_json = json_encode($preferences['mix_n_match_cuisines']);
    
    $stmt->bind_param("sssssssi", 
        $preferences['cuisine_style'],
        $dietary_json,
        $preferences['spice_level'],
        $preferences['cooking_time'],
        $ingredients_json,
        $preferences['ai_creativity'],
        $mix_n_match_json,
        $user_id
    );
    
    if ($stmt->execute()) {
        $success_message = "Preferences updated successfully!";
    } else {
        $error_message = "Error updating preferences: " . $conn->error;
    }
    $stmt->close();
}

// Available options for settings
$cuisine_styles = ['Filipino', 'Asian', 'Western', 'Mediterranean', 'Latin', 'Fusion'];
$dietary_options = ['vegetarian', 'vegan', 'gluten-free', 'dairy-free', 'nut-free', 'halal', 'kosher'];
$spice_levels = ['mild', 'medium', 'spicy', 'very spicy'];
$cooking_times = ['quick (under 30 mins)', 'moderate (30-60 mins)', 'long (over 60 mins)', 'any'];
$ai_creativity_levels = ['conservative', 'balanced', 'creative', 'very creative'];
$mix_n_match_options = ['Filipino', 'Chinese', 'Japanese', 'Korean', 'Thai', 'Italian', 'Mexican', 'American'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | ChefAI</title>
    <link rel="stylesheet" href="home.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color:rgb(221, 134, 41);
            --secondary-color: #4ECDC4;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --neutral-color: #6C4E31;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-color);
        }
        
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        .settings-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(108, 78, 49, 0.1);
        }
        
        .settings-header h1 {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .settings-card {
            margin-bottom: 30px;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .settings-card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .settings-card-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--neutral-color);
        }
        
        .btn-primary {
            background-color: #AB886D;
            border-color: #AB886D;
        }
        
        .btn-primary:hover {
            background-color: #ff5252;
            border-color: #ff5252;
        }
        
        .tag-item {
            display: inline-block;
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .tag-item .remove-tag {
            cursor: pointer;
            margin-left: 5px;
            color: #6c757d;
        }
        
        .tag-item .remove-tag:hover {
            color: var(--primary-color);
        }
        
        .success-message {
            color: #28a745;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #dc3545;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .settings-nav {
            margin-bottom: 30px;
        }
        
        .settings-nav .nav-link {
            color: var(--dark-color);
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .settings-nav .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .settings-nav .nav-link:hover:not(.active) {
            background-color: rgba(255, 107, 107, 0.1);
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .ingredient-tag-input {
            border-radius: 20px;
            padding: 8px 15px;
        }
        
        .chip {
            display: inline-flex;
            align-items: center;
            background-color: #e9ecef;
            padding: 5px 12px;
            border-radius: 20px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .chip-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #6c757d;
            color: white;
            border-radius: 50%;
            margin-left: 8px;
            cursor: pointer;
            font-size: 0.7rem;
        }
        
        .chip-remove:hover {
            background: var(--primary-color);
        }
        
        .preview-box {
            border: 1px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            background-color: #f8f9fa;
        }
        
        .preview-title {
            color: var(--neutral-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .preview-item {
            margin-bottom: 10px;
        }

        .profile-image-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-image-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid var(--primary-color);
        }
        
        .profile-image-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        
        .hidden-file-input {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'side-nav.php' ?>
    
     <div id="main-content">
        <div class="settings-container">
            <div class="settings-header">
                <h1><i class="bi bi-gear-fill"></i> Settings</h1>
                <p class="mb-0">Customize your ChefAI experience</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <ul class="nav settings-nav" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button>
                </li>
                
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="card settings-card">
                        <div class="card-header settings-card-header">
                            <i class="bi bi-person-fill"></i> Profile Information
                        </div>
                        <div class="card-body settings-card-body">
                            <form method="POST" action="settings.php" enctype="multipart/form-data">
                                <div class="profile-image-container">
                                    <?php if (!empty($profile_image)): ?>
                                        <img src="<?php echo htmlspecialchars($profile_image); ?>" class="profile-image-preview" id="profileImagePreview" alt="Profile Image">
                                    <?php else: ?>
                                        <div class="profile-image-preview" id="profileImagePreview" style="background-color: #ddd; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-person" style="font-size: 3rem; color: #666;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="profile-image-actions">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('profileImageInput').click()">
                                            <i class="bi bi-upload"></i> Change Image
                                        </button>
                                        <?php if (!empty($profile_image)): ?>
                                            <button type="submit" name="remove_image" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Remove Image
                                            </button>
                                        <?php endif; ?>
                                        <input type="file" id="profileImageInput" name="profile_image" class="hidden-file-input" accept="image/*" onchange="previewImage(this)">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                                    <small class="text-muted">Contact support to change your email</small>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card settings-card">
                        <div class="card-header settings-card-header" style="background-color: var(--secondary-color);">
                            <i class="bi bi-shield-lock-fill"></i> Security
                        </div>
                        <div class="card-body settings-card-body">
                            <div class="mb-4">
                                <h5>Change Password</h5>
                                <form method="POST" action="update_password.php" id="passwordForm">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Preferences Tab -->
                

    <!-- Bootstrap JS Bundle with Popper -->
    <script>
        // Handle ingredient tags
        document.getElementById('ingredient_input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const ingredient = this.value.trim();
                if (ingredient) {
                    addIngredientTag(ingredient);
                    this.value = '';
                }
            }
        });
        
        function addIngredientTag(ingredient) {
            // Check if already exists
            const currentIngredients = document.getElementById('ingredient_preferences').value.split(',');
            if (currentIngredients.includes(ingredient)) return;
            
            // Add to hidden field
            if (document.getElementById('ingredient_preferences').value) {
                document.getElementById('ingredient_preferences').value += ',' + ingredient;
            } else {
                document.getElementById('ingredient_preferences').value = ingredient;
            }
            
            // Add visual tag
            const tag = document.createElement('span');
            tag.className = 'chip';
            tag.innerHTML = `
                ${ingredient}
                <span class="chip-remove" onclick="removeIngredientTag('${ingredient}')">&times;</span>
            `;
            document.getElementById('ingredient_tags').appendChild(tag);
        }
        
        function removeIngredientTag(ingredient) {
            // Remove from hidden field
            const currentIngredients = document.getElementById('ingredient_preferences').value.split(',');
            const index = currentIngredients.indexOf(ingredient);
            if (index > -1) {
                currentIngredients.splice(index, 1);
                document.getElementById('ingredient_preferences').value = currentIngredients.join(',');
            }
            
            // Remove visual tag
            const tags = document.getElementById('ingredient_tags').getElementsByClassName('chip');
            for (let tag of tags) {
                if (tag.textContent.trim().replace('×', '') === ingredient) {
                    tag.remove();
                    break;
                }
            }
        }
        
        // Initialize any existing tags
        document.addEventListener('DOMContentLoaded', function() {
            const initialIngredients = document.getElementById('ingredient_preferences').value;
            if (initialIngredients) {
                initialIngredients.split(',').forEach(ingredient => {
                    if (ingredient.trim()) {
                        const tag = document.createElement('span');
                        tag.className = 'chip';
                        tag.innerHTML = `
                            ${ingredient}
                            <span class="chip-remove" onclick="removeIngredientTag('${ingredient}')">&times;</span>
                        `;
                        document.getElementById('ingredient_tags').appendChild(tag);
                    }
                });
            }
        });
        
        // Profile image preview
        function previewImage(input) {
            const preview = document.getElementById('profileImagePreview');
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    // If it's a div (default avatar), replace with an img
                    const newPreview = document.createElement('img');
                    newPreview.src = e.target.result;
                    newPreview.className = 'profile-image-preview';
                    newPreview.id = 'profileImagePreview';
                    newPreview.alt = 'Profile Image';
                    preview.parentNode.replaceChild(newPreview, preview);
                }
            }
            
            if (file) {
                reader.readAsDataURL(file);
            }
        }
        
        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>