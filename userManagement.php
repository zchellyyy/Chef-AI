<?php
// db connection
include 'db_connect.php';

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "edit") {
    $id = $_POST["id"];
    $username = $_POST["username"];

    if (!empty($_POST["password"])) {
        $password = password_hash($_POST["password"], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE id=?");
        $stmt->bind_param("ssi", $username, $password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=? WHERE id=?");
        $stmt->bind_param("si", $username, $id);
    }
    $stmt->execute();
    header("Location: userManagement.php");
    exit();
}

// Handle Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "delete") {
    $id = $_POST["id"];
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: userManagement.php");
    exit();
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
    <style>
        :root {
            --primary-color: #8B5C3E;
            --secondary-color: #5e4646;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--light-bg);
            display: flex;
            min-height: 100vh;
        }

        .topbar {
            background-color: #dcbab5;
            padding: 15px 25px;
            margin-bottom: 25px;
            text-align: right;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--secondary-color);
    }

        .user-info {
            background: var(--secondary-color);
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .content-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        h2 { 
            margin-bottom: 25px;
            color: #ffffff;
            position: relative;
            padding-bottom: 10px;
            font-size: 1.8rem;
        }


        table { 
            width: 100%; 
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th { 
            background: var(--primary-color); 
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background-color: #f8f8f8;
        }

        tr:last-child td {
            border-bottom: none;
        }

        img.profile { 
            width: 45px; 
            height: 45px; 
            border-radius: 50%; 
            object-fit: cover;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 2px solid white;
        }

        .avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 2px solid white;
        }

        .btn {
            padding: 8px 14px;
            margin: 2px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn:active {
            transform: translateY(0);
        }

        .view { background: var(--info-color); }
        .edit { background: var(--warning-color); }
        .delete { background: var(--danger-color); }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background: white;
            padding: 25px;
            width: 450px;
            max-width: 90%;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            cursor: pointer;
            font-size: 24px;
            color: #aaa;
            transition: all 0.2s ease;
        }

        .close:hover {
            color: #777;
            transform: rotate(90deg);
        }

        .modal h3 {
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-size: 1.4rem;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        input, select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 92, 62, 0.1);
        }

        .user-details {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }

        .user-details img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .user-info-box {
            flex: 1;
        }

        .user-info-box p {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .user-info-box strong {
            color: var(--secondary-color);
            min-width: 100px;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 6px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .user-details {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <?php include 'side-nav-admin.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Users Management Dashboard</div>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <span>Admin Panel</span>
            </div>
        </div>

        <div class="content-box">
            <h2>User Management</h2>
            
            <?php if ($users->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Profile</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row["id"] ?></td>
                        <td>
                            <?php 
                            $hasProfileImage = !empty($row["profile_image"]) && 
                                             $row["profile_image"] !== 'default' && 
                                             file_exists($row["profile_image"]);
                            ?>
                            
                            <?php if ($hasProfileImage): ?>
                                <img src="<?= $row["profile_image"] ?>" 
                                     class="profile" 
                                     alt="<?= htmlspecialchars($row["username"]) ?>'s profile picture">
                            <?php else: ?>
                                <?php
                                $colors = ['#8B5C3E', '#5e4646', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f39c12', '#1abc9c'];
                                $colorIndex = $row["id"] % count($colors);
                                $initials = strtoupper(substr($row["username"], 0, 2));
                                ?>
                                <div class="avatar-placeholder" style="background-color: <?= $colors[$colorIndex] ?>">
                                    <?= $initials ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row["username"]) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn view" onclick="viewUser(<?= htmlspecialchars(json_encode($row)) ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
    
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $row["id"] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No users found in the system</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h3><i class="fas fa-user-edit"></i> Edit User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="editPassword">New Password</label>
                    <input type="password" id="editPassword" name="password" placeholder="Leave blank to keep current password">
                </div>
                
                <button type="submit" class="btn edit" style="width: 100%;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
            <h3><i class="fas fa-user-circle"></i> User Details</h3>
            
            <div class="user-details">
                <div id="viewAvatarContainer">
                    <!-- Avatar will be inserted here by JavaScript -->
                </div>
                <div class="user-info-box">
                    <p><strong>ID:</strong> <span id="viewId"></span></p>
                    <p><strong>Username:</strong> <span id="viewUsername"></span></p>
                    <p><strong>Profile Image:</strong> <span id="viewImageStatus"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = "flex";
            document.body.style.overflow = "hidden";
        }

        function closeModal(id) {
            document.getElementById(id).style.display = "none";
            document.body.style.overflow = "auto";
        }

        function viewUser(user) {
            document.getElementById("viewId").textContent = user.id;
            document.getElementById("viewUsername").textContent = user.username;
            
            const avatarContainer = document.getElementById("viewAvatarContainer");
            avatarContainer.innerHTML = '';
            
            const hasProfileImage = user.profile_image && 
                                  user.profile_image !== 'default' && 
                                  user.profile_image !== '';
            
            if (hasProfileImage) {
                const img = document.createElement('img');
                img.src = user.profile_image;
                img.alt = user.username + "'s profile picture";
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.borderRadius = '50%';
                img.style.objectFit = 'cover';
                img.style.border = '3px solid white';
                img.style.boxShadow = '0 4px 10px rgba(0,0,0,0.1)';
                avatarContainer.appendChild(img);
                document.getElementById("viewImageStatus").textContent = "Custom image";
            } else {
                const colors = ['#8B5C3E', '#5e4646', '#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f39c12', '#1abc9c'];
                const color = colors[user.id % colors.length];
                const letters = user.username.substring(0, 2).toUpperCase();
                
                const placeholder = document.createElement('div');
                placeholder.style.width = '100px';
                placeholder.style.height = '100px';
                placeholder.style.borderRadius = '50%';
                placeholder.style.display = 'flex';
                placeholder.style.alignItems = 'center';
                placeholder.style.justifyContent = 'center';
                placeholder.style.color = 'white';
                placeholder.style.fontWeight = 'bold';
                placeholder.style.fontSize = '24px';
                placeholder.style.boxShadow = '0 4px 10px rgba(0,0,0,0.1)';
                placeholder.style.border = '3px solid white';
                placeholder.style.backgroundColor = color;
                placeholder.textContent = letters;
                
                avatarContainer.appendChild(placeholder);
                document.getElementById("viewImageStatus").textContent = "Generated avatar";
            }
            
            openModal("viewModal");
        }

        function editUser(user) {
            document.getElementById("editId").value = user.id;
            document.getElementById("editUsername").value = user.username;
            openModal("editModal");
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>