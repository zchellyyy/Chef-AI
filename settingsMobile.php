<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginMobile.php");
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
            header("Location: settingsMobile.php");
            exit();
        }
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $user_id . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error_message'] = "File is not an image.";
        header("Location: settingsMobile.php");
        exit();
    } elseif ($_FILES["profile_image"]["size"] > 10485760) {
        $_SESSION['error_message'] = "Sorry, your file is too large (max 2MB).";
        header("Location: settingsMobile.php");
        exit();
    } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        header("Location: settingsMobile.php");
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
        header("Location: settingsMobile.php");
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
    header("Location: settingsMobile.php");
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
    header("Location: settingsMobile.php");
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Password changed successfully!";
                } else {
                    $_SESSION['error_message'] = "Error changing password: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Password must be at least 8 characters long!";
            }
        } else {
            $_SESSION['error_message'] = "New passwords do not match!";
        }
    } else {
        $_SESSION['error_message'] = "Current password is incorrect!";
    }
    header("Location: settingsMobile.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings | ChefAI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: rgb(221, 134, 41);
            --primary-light: rgba(221, 134, 41, 0.1);
            --secondary-color: #4ECDC4;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --neutral-color: #6C4E31;
            --gray-light: #f5f5f5;
            --gray: #e0e0e0;
            --gray-dark: #9e9e9e;
            --success: #4caf50;
            --error: #f44336;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --blue-color: #2196F3;
            --blue-light: rgba(33, 150, 243, 0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fff6edff !important;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 100%;
            padding: 16px;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray);
        }
        
        .settings-header h1 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .settings-header p {
            color: var(--gray-dark);
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            background: white;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            padding: 4px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        
        .tabs::-webkit-scrollbar {
            display: none;
        }
        
        .tab-button {
            flex: 1;
            min-width: 120px;
            padding: 12px 16px;
            background: none;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            color: var(--dark-color);
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        
        .tab-button.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--neutral-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray);
            border-radius: var(--radius-sm);
            font-size: 16px;
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        
        /* Password field with toggle button */
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-dark);
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            z-index: 2;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        /* Blue button styling */
        .btn-blue {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--blue-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .btn-blue:hover {
            background-color: #1976D2;
        }
        
        .btn-blue.btn-block {
            display: block;
            width: 100%;
        }
        
        /* FIXED: Specific button styling for Save Preferences */
        .tab-content .btn.btn-block[name="update_preferences"],
        .tab-content button.btn-block[name="update_preferences"] {
            display: block;
            width: 100%;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.2s;
            margin-top: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .tab-content .btn.btn-block[name="update_preferences"]:hover,
        .tab-content button.btn-block[name="update_preferences"]:hover {
            background-color: #F0E68C !important; /* Slightly darker khaki for hover */
            color: #556B2F !important;
        }
        
        /* Regular button styling */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .btn:hover {
            background-color: #e67e22;
        }
        
        .btn-danger {
            background-color: var(--error);
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .profile-image-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .profile-image-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            margin-bottom: 16px;
        }
        
        .profile-image-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 3px solid var(--primary-color);
        }
        
        .profile-image-placeholder i {
            font-size: 48px;
            color: #666;
        }
        
        .profile-image-actions {
            display: flex;
            gap: 12px;
        }
        
        .hidden-file-input {
            display: none;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .chip-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        
        .chip {
            display: inline-flex;
            align-items: center;
            background-color: var(--gray);
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 14px;
        }
        
        .chip-remove {
            margin-left: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: var(--success);
            border: 1px solid #c8e6c9;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: var(--error);
            border: 1px solid #ffcdd2;
        }
        
        .text-muted {
            color: var(--gray-dark);
            font-size: 14px;
            margin-top: 4px;
        }
        
        .preview-box {
            border: 1px dashed var(--gray);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-top: 20px;
            background-color: var(--gray-light);
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--neutral-color);
        }
        
        .preview-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .select-wrapper {
            position: relative;
        }
        
        .select-wrapper::after {
            content: "▼";
            font-size: 12px;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--gray-dark);
        }
        
        .select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        
        @media (min-width: 768px) {
            .container {
                max-width: 600px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'side-nav-mobile.php' ?>
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-button active" data-tab="profile">Profile</button>

        </div>
        
        <!-- Profile Tab -->
        <div class="tab-content active" id="profile-tab">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Profile Information
                </div>
                <div class="card-body">
                    <form method="POST" action="settingsMobile.php" enctype="multipart/form-data">
                        <div class="profile-image-container">
                            <?php if (!empty($profile_image)): ?>
                                <img src="<?php echo htmlspecialchars($profile_image); ?>" class="profile-image-preview" id="profileImagePreview" alt="Profile Image">
                            <?php else: ?>
                                <div class="profile-image-placeholder" id="profileImagePreview">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="profile-image-actions">
                                <button type="button" class="btn btn-sm" onclick="document.getElementById('profileImageInput').click()">
                                    <i class="fas fa-upload"></i> Change
                                </button>
                                <?php if (!empty($profile_image)): ?>
                                    <button type="submit" name="remove_image" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                <?php endif; ?>
                                <input type="file" id="profileImageInput" name="profile_image" class="hidden-file-input" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn-blue btn-block">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="background-color: var(--secondary-color);">
                    <i class="fas fa-lock"></i> Security
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <h3>Change Password</h3>
                        <form method="POST" action="settingsMobile.php" id="passwordForm">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="text-muted">Minimum 8 characters</p>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn-blue btn-block">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Preferences Tab -->
        
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked tab
                button.classList.add('active');
                
                // Show corresponding content
                const tabId = button.getAttribute('data-tab');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Password visibility toggle function
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
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