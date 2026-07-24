<?php 
include 'db_connect.php';

// Fetch pending recipes joined with user data
$pendingSql = "SELECT uploaded_recipe.*, users.username 
              FROM uploaded_recipe 
              JOIN users ON uploaded_recipe.user_id = users.id
              WHERE (uploaded_recipe.status IS NULL OR uploaded_recipe.status = 'pending')
              ORDER BY uploaded_recipe.created_at DESC";
$pendingResult = $conn->query($pendingSql);

// Group pending recipes by category and separate edit requests
$groupedRecipes = [];
$editRequestRecipes = [];

while ($row = $pendingResult->fetch_assoc()) {
    // Check if this is an edit request (previously accepted recipe)
    if (!empty($row['previous_status']) && $row['previous_status'] === 'accepted') {
        $editRequestRecipes[] = $row;
    } else {
        // Normalize category names for consistent grouping
        $category = $row['category'];
        if (strpos(strtolower($category), 'beverage') !== false || strpos(strtolower($category), 'drink') !== false) {
            $category = 'Beverages / Drinks';
        }
        $groupedRecipes[$category][] = $row;
    }
}

// Fetch accepted recipes
$acceptedRecipes = $conn->query("SELECT accepted_recipe.*, users.username 
                                FROM accepted_recipe 
                                JOIN users ON accepted_recipe.user_id = users.id
                                ORDER BY accepted_recipe.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch rejected recipes
$rejectedRecipes = $conn->query("SELECT uploaded_recipe.*, users.username 
                                FROM uploaded_recipe 
                                JOIN users ON uploaded_recipe.user_id = users.id
                                WHERE uploaded_recipe.status = 'rejected'
                                ORDER BY uploaded_recipe.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Count recipes
$pendingCount = array_sum(array_map('count', $groupedRecipes));
$editRequestCount = count($editRequestRecipes);
$acceptedCount = count($acceptedRecipes);
$rejectedCount = count($rejectedRecipes);

$categories = ['Main Dish', 'Side Dish', 'Dessert', 'Beverages / Drinks'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
  <style>
    :root {
      --primary-color: #8B5C3E;
      --secondary-color: #5e4646;
      --pending-color: #e74c3c;
      --edit-request-color: #9b59b6;
      --accepted-color: #2ecc71;
      --rejected-color: #7f8c8d;
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
      display: flex; 
      background: var(--light-bg); 
      min-height: 100vh; 
      color: #333;
    }
    
    .main-content { 
      flex: 1; 
      padding: 30px;
      transition: all 0.3s ease;
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

    h3.section-title {
      color: var(--secondary-color);
      margin: 25px 0 15px;
      font-size: 1.4rem;
      position: relative;
      padding-bottom: 10px;
      font-weight: 600;
    }

    h3.section-title:after {
      content: '';
      position: absolute;
      left: 0;
      bottom: 0;
      width: 50px;
      height: 3px;
      background: var(--primary-color);
      border-radius: 3px;
    }

    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 25px;
      margin-bottom: 35px;
    }

    .card {
      background-color: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      border-left: 4px solid var(--primary-color);
      display: flex;
      flex-direction: column;
    }

    .card:hover { 
      transform: translateY(-5px); 
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .card .number {
      font-size: 36px;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 5px;
      line-height: 1;
    }

    .card .label {
      color: var(--secondary-color);
      font-size: 16px;
      font-weight: 500;
      opacity: 0.9;
    }

    /* Pending card specific styles */
    .card-pending {
      border-left-color: var(--pending-color);
    }

    .card-pending .number {
      color: var(--pending-color);
    }

    /* Edit Request card specific styles */
    .card-edit-request {
      border-left-color: var(--edit-request-color);
    }

    .card-edit-request .number {
      color: var(--edit-request-color);
    }

    .notification-badge {
      position: absolute;
      top: 15px;
      right: 15px;
      background-color: var(--pending-color);
      color: white;
      border-radius: 50%;
      width: 26px;
      height: 26px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: bold;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      animation: pulse 2s infinite;
    }

    .edit-request-badge {
      background-color: var(--edit-request-color);
    }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }

    /* Accepted card specific styles */
    .card-accepted {
      border-left-color: var(--accepted-color);
    }

    .card-accepted .number {
      color: var(--accepted-color);
    }

    /* Rejected card specific styles */
    .card-rejected {
      border-left-color: var(--rejected-color);
    }

    .card-rejected .number {
      color: var(--rejected-color);
    }

    /* Category cards */
    .category-card {
      background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%);
      border-left: 4px solid var(--secondary-color);
    }

    .category-card .number {
      color: var(--secondary-color);
    }

    .category-container {
      display: none;
      margin-top: 20px;
      animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .category-container.active {
      display: block;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      margin-top: 15px;
      box-shadow: var(--card-shadow);
    }

    th, td {
      padding: 14px 16px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }

    th {
      background-color: var(--primary-color);
      color: white;
      font-weight: 500;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
    }

    tr:hover {
      background-color: #f8f8f8;
      cursor: pointer;
    }

    tr:last-child td {
      border-bottom: none;
    }

    img.recipe-thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .action-btn {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-right: 5px;
    }

    .action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .delete-btn {
      background-color: #e74c3c;
      color: white;
    }

    .delete-btn:hover {
      background-color: #c0392b;
    }

    .restore-btn {
      background-color: #2ecc71;
      color: white;
    }

    .restore-btn:hover {
      background-color: #27ae60;
    }

    .accept-btn {
      background-color: #2ecc71;
      color: white;
    }

    .accept-btn:hover {
      background-color: #27ae60;
    }

    .reject-btn {
      background-color: #e67e22;
      color: white;
    }

    .reject-btn:hover {
      background-color: #d35400;
    }

    .status-badge {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-block;
    }

    .status-pending {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-edit-request {
      background-color: #e8d4f7;
      color: #6f42c1;
    }

    .status-accepted {
      background-color: #d4edda;
      color: #155724;
    }

    .status-rejected {
      background-color: #f8d7da;
      color: #721c24;
    }

    /* ENHANCED MODAL STYLES */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      margin: 5% auto;
      padding: 0;
      border-radius: 12px;
      width: 90%;
      max-width: 800px;
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      border: 1px solid #e9ecef;
    }

    .modal-header {
      padding: 25px 30px;
      background: linear-gradient(135deg, #C1856D 0%, #6C4E31 100%);
      color: white;
      border-radius: 12px 12px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      color: white;
      font-size: 1.4rem;
      font-weight: 600;
    }

    .close-modal {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: rgba(255,255,255,0.8);
      transition: color 0.3s;
    }

    .close-modal:hover {
      color: white;
    }

    .modal-body {
      padding: 30px;
    }

    .recipe-image-container {
      text-align: center;
      margin-bottom: 25px;
    }

    .recipe-image {
      width: 100%;
      max-height: 300px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .recipe-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 25px;
    }

    .meta-item {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
    }

    .meta-item strong {
      display: block;
      color: #2c3e50;
      margin-bottom: 5px;
      font-size: 0.9rem;
    }

    .meta-item span {
      color: #7f8c8d;
      font-weight: 500;
      font-size: 1rem;
    }

    .modal-section {
      margin: 25px 0;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      border-left: 4px solid #fff3cd;
    }

    .modal-section h4 {
      color: #2c3e50;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 1.1rem;
    }

    .ingredients-list, .instructions-list {
      background: white;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #e9ecef;
    }

    .ingredients-list ul {
      list-style: none;
      padding: 0;
    }

    .ingredients-list li {
      padding: 8px 0;
      border-bottom: 1px solid #f1f1f1;
      display: flex;
      align-items: center;
    }

    .ingredients-list li:before {
      content: "•";
      color: #3498db;
      font-weight: bold;
      margin-right: 10px;
    }

    .instructions-list p {
      padding: 10px 0;
      border-bottom: 1px solid #f1f1f1;
      line-height: 1.6;
    }

    .instructions-list p:last-child {
      border-bottom: none;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      position: relative;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn:active {
      transform: translateY(0);
    }

    .btn-accept { 
      background: var(--accepted-color);
      color: white;
    }
    .btn-accept:hover {
      background: #27ae60;
    }

    .btn-reject { 
      background: var(--pending-color);
      color: white;
    }
    .btn-reject:hover {
      background: #c0392b;
    }

    .btn-delete { 
      background: var(--pending-color);
      color: white;
    }
    .btn-delete:hover {
      background: #c0392b;
    }

    .btn-edit-request { 
      background: var(--edit-request-color);
      color: white;
    }
    .btn-edit-request:hover {
      background: #8e44ad;
    }

    .btn-secondary {
      background: #7f8c8d;
      color: white;
    }
    .btn-secondary:hover {
      background: #6c7a7d;
    }

    .empty-state {
      background: white;
      padding: 40px;
      border-radius: 10px;
      text-align: center;
      box-shadow: var(--card-shadow);
      color: #7f8c8d;
    }

    .empty-state i {
      font-size: 50px;
      color: #ddd;
      margin-bottom: 15px;
    }

    .empty-state p {
      font-size: 16px;
    }

    .edit-request-notice {
      background: linear-gradient(135deg, #f8f4ff 0%, #e8d4f7 100%);
      border: 1px solid #d6bcf2;
      border-radius: 8px;
      padding: 15px;
      margin: 15px 0;
      color: #6f42c1;
    }

    .edit-request-notice h4 {
      color: #6f42c1;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .edit-request-notice p {
      margin: 5px 0;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main-content { 
        margin-left: 0;
        padding: 20px;
      }
      
      .card-grid {
        grid-template-columns: 1fr;
      }
      
      .topbar {
        flex-direction: column;
        gap: 15px;
        text-align: center;
      }
      
      .modal-content {
        width: 95%;
        margin: 10% auto;
      }
      
      .recipe-meta {
        grid-template-columns: 1fr;
      }
      
      .modal-actions {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

<?php include 'side-nav-admin.php'; ?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-title">Recipe Management Dashboard</div>
    <div class="user-info">
      <i class="fas fa-user-shield"></i>
      <span>Admin Panel</span>
    </div>
  </div>

  <h3 class="section-title">Recipe Status Overview</h3>
  <div class="card-grid">
    <div class="card card-pending" onclick="showContent('pending')">
      <?php if ($pendingCount > 0): ?>
        <div class="notification-badge" title="<?= $pendingCount ?> pending recipes">
          <i class="fas fa-bell"></i>
        </div>
      <?php endif; ?>
      <div class="number"><?= $pendingCount ?></div>
      <div class="label">New Recipes</div>
      <div class="subtext">Awaiting approval</div>
    </div>
    <div class="card card-edit-request" onclick="showContent('edit-request')">
      <?php if ($editRequestCount > 0): ?>
        <div class="notification-badge edit-request-badge" title="<?= $editRequestCount ?> edit requests">
          <i class="fas fa-edit"></i>
        </div>
      <?php endif; ?>
      <div class="number"><?= $editRequestCount ?></div>
      <div class="label">Edit Requests</div>
      <div class="subtext">Previously accepted</div>
    </div>
    <div class="card card-accepted" onclick="showContent('accepted')">
      <div class="number"><?= $acceptedCount ?></div>
      <div class="label">Accepted Recipes</div>
      <div class="subtext">Approved recipes</div>
    </div>
    <div class="card card-rejected" onclick="showContent('rejected')">
      <div class="number"><?= $rejectedCount ?></div>
      <div class="label">Rejected Recipes</div>
      <div class="subtext">Not approved</div>
    </div>
  </div>

  <!-- Pending Recipes Content (Categories) -->
  <div id="pending-content" class="category-container">
    <h3 class="section-title">New Recipe Submissions</h3>
    <div class="card-grid">
      <?php foreach ($categories as $cat): ?>
        <?php $count = isset($groupedRecipes[$cat]) ? count($groupedRecipes[$cat]) : 0; ?>
        <div class="card category-card" onclick="showCategory('<?= htmlspecialchars($cat) ?>')">
          <?php if ($count > 0): ?>
            <div class="notification-badge"><?= $count ?></div>
          <?php endif; ?>
          <div class="number"><?= $count ?></div>
          <div class="label"><?= htmlspecialchars($cat) ?></div>
          <div class="subtext">New recipes</div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pending Recipes by Category -->
    <?php foreach ($categories as $cat): ?>
      <div id="category-<?= htmlspecialchars($cat) ?>" class="category-container">
        <h3 class="section-title"><?= htmlspecialchars($cat) ?> Recipes</h3>
        <?php if (!empty($groupedRecipes[$cat])): ?>
          <table>
            <thead>
              <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Prep</th>
                <th>Cook</th>
                <th>Servings</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($groupedRecipes[$cat] as $row): ?>
                <tr onclick='openRecipeModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "pending")'>
                  <td><img src="uploads/<?= htmlspecialchars($row['image_name']) ?>" class="recipe-thumb" onerror="this.src='default-image.jpg';"></td>
                  <td><?= htmlspecialchars($row['recipe_name']) ?></td>
                  <td><?= htmlspecialchars($row['prep_time']) ?> mins</td>
                  <td><?= htmlspecialchars($row['cook_time']) ?> mins</td>
                  <td><?= htmlspecialchars($row['servings']) ?></td>
                  <td>
                    <span class="status-badge status-pending">New</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-utensils"></i>
            <p>No recipes found in this category</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Edit Request Recipes Content -->
  <div id="edit-request-content" class="category-container">
    <h3 class="section-title">Edit Requests</h3>
    <?php if (!empty($editRequestRecipes)): ?>
      <table>
        <thead>
          <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Uploaded By</th>
            <th>Last Updated</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($editRequestRecipes as $row): ?>
            <tr onclick='openRecipeModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "edit-request")'>
              <td><img src="uploads/<?= htmlspecialchars($row['image_name']) ?>" class="recipe-thumb" onerror="this.src='default-image.jpg';"></td>
              <td><?= htmlspecialchars($row['recipe_name']) ?></td>
              <td><?= htmlspecialchars($row['category']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= date('M d, Y', strtotime($row['updated_at'] ?? $row['created_at'])) ?></td>
              <td>
                <span class="status-badge status-edit-request">Edit Request</span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-edit"></i>
        <p>No edit requests found</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Accepted Recipes Content -->
  <div id="accepted-content" class="category-container">
    <h3 class="section-title">Accepted Recipes</h3>
    <?php if (!empty($acceptedRecipes)): ?>
      <table>
        <thead>
          <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Uploaded By</th>
            <th>Date Accepted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($acceptedRecipes as $row): ?>
            <tr onclick='openRecipeModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "accepted")'>
              <td><img src="uploads/<?= htmlspecialchars($row['image_name']) ?>" class="recipe-thumb" onerror="this.src='default-image.jpg';"></td>
              <td><?= htmlspecialchars($row['recipe_name']) ?></td>
              <td><?= htmlspecialchars($row['category']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
              <td>
                <button class="action-btn reject-btn" onclick="rejectAcceptedRecipe(event, <?= $row['id'] ?>)">
                  <i class="fas fa-times"></i> Reject
                </button>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <p>No accepted recipes found</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Rejected Recipes Content -->
  <div id="rejected-content" class="category-container">
    <h3 class="section-title">Rejected Recipes</h3>
    <?php if (!empty($rejectedRecipes)): ?>
      <table>
        <thead>
          <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Uploaded By</th>
            <th>Date Rejected</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rejectedRecipes as $row): ?>
            <tr onclick='openRecipeModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, "rejected")'>
              <td><img src="uploads/<?= htmlspecialchars($row['image_name']) ?>" class="recipe-thumb" onerror="this.src='default-image.jpg';"></td>
              <td><?= htmlspecialchars($row['recipe_name']) ?></td>
              <td><?= htmlspecialchars($row['category']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
              <td>
                <button class="action-btn accept-btn" onclick="acceptRejectedRecipe(event, <?= $row['id'] ?>)">
                  <i class="fas fa-check"></i> Accept
                </button>
                <button class="action-btn delete-btn" onclick="deleteRejectedRecipe(event, <?= $row['id'] ?>)">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-times-circle"></i>
        <p>No rejected recipes found</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ENHANCED: Professional Recipe Modal -->
<div id="recipeModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Recipe Details</h3>
      <button class="close-modal" onclick="closeRecipeModal()">&times;</button>
    </div>
    <div class="modal-body" id="recipeModalBody">
      <!-- Content will be loaded dynamically -->
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
  <div class="modal-content" style="width: 400px;">
    <div class="modal-header">
      <h3 id="confirmTitle">Confirm Action</h3>
      <button class="close-modal" onclick="closeConfirmModal()">&times;</button>
    </div>
    <div class="modal-body">
      <p id="confirmMessage">Are you sure you want to perform this action?</p>
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closeConfirmModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button class="btn" id="confirmActionBtn">
          <i class="fas fa-check"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let currentRecipeId = null;
let currentRecipeStatus = null;
let currentActionCallback = null;

// Show the appropriate content based on status
function showContent(status) {
  // Hide all content containers
  document.querySelectorAll('.category-container').forEach(div => {
    div.classList.remove('active');
  });
  
  // Show the selected content
  const target = document.getElementById(status + '-content');
  if (target) target.classList.add('active');
  
  // If showing pending, also show the categories grid by default
  if (status === 'pending') {
    document.getElementById('pending-content').classList.add('active');
  }
}

// Show specific category within pending recipes
function showCategory(category) {
  // First show the pending content
  showContent('pending');
  
  // Then show the specific category
  document.querySelectorAll('#pending-content .category-container').forEach(div => {
    div.classList.remove('active');
  });
  
  const target = document.getElementById('category-' + category);
  if (target) target.classList.add('active');
}

function openRecipeModal(recipe, status) {
  currentRecipeId = recipe.id;
  currentRecipeStatus = status;
  
  const modalBody = document.getElementById('recipeModalBody');
  
  let actionButtons = '';
  if (status === 'pending') {
    actionButtons = `
      <div class="modal-actions">
        <button class="btn btn-accept" onclick="handleRecipeAction('accept')">
          <i class="fas fa-check"></i> Accept Recipe
        </button>
        <button class="btn btn-reject" onclick="handleRecipeAction('reject')">
          <i class="fas fa-times"></i> Reject Recipe
        </button>
      </div>
    `;
  } else if (status === 'edit-request') {
    actionButtons = `
      <div class="modal-actions">
        <button class="btn btn-accept" onclick="handleRecipeAction('accept_edit')">
          <i class="fas fa-check"></i> Accept Changes
        </button>
        <button class="btn btn-reject" onclick="handleRecipeAction('reject_edit')">
          <i class="fas fa-times"></i> Reject Changes
        </button>
      </div>
    `;
  } else if (status === 'accepted') {
    actionButtons = `
      <div class="modal-actions">
        <button class="btn btn-reject" onclick="rejectAcceptedRecipeFromModal()">
          <i class="fas fa-times"></i> Reject Recipe
        </button>
        <button class="btn btn-delete" onclick="deleteRecipeFromModal()">
          <i class="fas fa-trash"></i> Delete Recipe
        </button>
      </div>
    `;
  } else if (status === 'rejected') {
    actionButtons = `
      <div class="modal-actions">
        <button class="btn btn-accept" onclick="acceptRejectedRecipeFromModal()">
          <i class="fas fa-check"></i> Accept Recipe
        </button>
        <button class="btn btn-delete" onclick="deleteRejectedRecipeFromModal()">
          <i class="fas fa-trash"></i> Delete Recipe
        </button>
      </div>
    `;
  }

  const editRequestNotice = (status === 'edit-request' || (recipe.previous_status === 'accepted' && status === 'pending')) ? `
    <div class="edit-request-notice">
      <h4><i class="fas fa-edit"></i> Edit Request</h4>
      <p>This recipe was previously accepted and has been edited by the user.</p>
      <p>Please review the changes and approve or reject the updated version.</p>
    </div>
  ` : '';

  modalBody.innerHTML = `
    ${editRequestNotice}
    
    <div class="recipe-image-container">
      <img src="uploads/${recipe.image_name}" class="recipe-image" onerror="this.src='default-image.jpg';">
    </div>
    
    <div class="recipe-meta">
      <div class="meta-item">
        <strong>Recipe Name</strong>
        <span>${recipe.recipe_name}</span>
      </div>
      <div class="meta-item">
        <strong>Uploaded By</strong>
        <span>${recipe.username}</span>
      </div>
      <div class="meta-item">
        <strong>Category</strong>
        <span>${recipe.category}</span>
      </div>
      <div class="meta-item">
        <strong>Prep Time</strong>
        <span>${recipe.prep_time} minutes</span>
      </div>
      <div class="meta-item">
        <strong>Cook Time</strong>
        <span>${recipe.cook_time} minutes</span>
      </div>
      <div class="meta-item">
        <strong>Servings</strong>
        <span>${recipe.servings}</span>
      </div>
    </div>
    
    <div class="modal-section">
      <h4><i class="fas fa-list-ul"></i> Ingredients</h4>
      <div class="ingredients-list">
        <ul>
          ${recipe.ingredients ? recipe.ingredients.split('\n').map(item => item.trim() !== '' ? `<li>${item}</li>` : '').join('') : '<li>No ingredients listed</li>'}
        </ul>
      </div>
    </div>
    
    <div class="modal-section">
      <h4><i class="fas fa-list-ol"></i> Instructions</h4>
      <div class="instructions-list">
        ${recipe.instructions ? recipe.instructions.split('\n').map(para => para.trim() !== '' ? `<p>${para}</p>` : '').join('') : '<p>No instructions provided</p>'}
      </div>
    </div>

    ${actionButtons}
  `;

  document.getElementById('recipeModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeRecipeModal() {
  document.getElementById('recipeModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

function handleRecipeAction(action) {
  if (!currentRecipeId) return;
  
  let actionText, message, buttonClass;
  
  switch(action) {
    case 'accept':
      actionText = 'Accept';
      message = 'Are you sure you want to accept this recipe?';
      buttonClass = 'btn-accept';
      break;
    case 'reject':
      actionText = 'Reject';
      message = 'Are you sure you want to reject this recipe?';
      buttonClass = 'btn-reject';
      break;
    case 'accept_edit':
      actionText = 'Accept Changes';
      message = 'Are you sure you want to accept these changes? The recipe will be updated and marked as accepted.';
      buttonClass = 'btn-accept';
      break;
    case 'reject_edit':
      actionText = 'Reject Changes';
      message = 'Are you sure you want to reject these changes? The recipe will revert to its previous accepted version.';
      buttonClass = 'btn-reject';
      break;
  }
  
  showConfirmModal(
    `${actionText} Recipe`, 
    message,
    actionText,
    buttonClass,
    () => {
      fetch('process_recipe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `recipe_id=${currentRecipeId}&action=${action}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(`Recipe ${actionText.toLowerCase()}ed successfully!`);
          closeRecipeModal();
          location.reload();
        } else {
          alert(`Error: ${data.error || 'Failed to process recipe'}`);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing the recipe');
      });
    }
  );
}

function showConfirmModal(title, message, actionText, actionClass, callback) {
  document.getElementById('confirmTitle').innerText = title;
  document.getElementById('confirmMessage').innerText = message;
  
  const actionBtn = document.getElementById('confirmActionBtn');
  actionBtn.innerText = actionText;
  actionBtn.className = 'btn ' + actionClass;
  
  currentActionCallback = callback;
  
  document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmModal() {
  document.getElementById('confirmModal').style.display = 'none';
}

document.getElementById('confirmActionBtn').addEventListener('click', function() {
  if (currentActionCallback) {
    currentActionCallback();
  }
  closeConfirmModal();
});

// Initialize by showing pending content
document.addEventListener('DOMContentLoaded', function() {
  showContent('pending');
  
  // Close modals when clicking outside
  document.getElementById('recipeModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeRecipeModal();
    }
  });
  
  document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeConfirmModal();
    }
  });
});

// Table action functions
function deleteRecipe(event, recipeId) {
  event.stopPropagation();
  showConfirmModal(
    'Delete Recipe', 
    'Are you sure you want to delete this recipe?',
    'Delete',
    'btn-reject',
    () => {
      fetch('process_recipe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `recipe_id=${recipeId}&action=delete`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Recipe deleted successfully!');
          location.reload();
        } else {
          alert(`Error: ${data.error || 'Failed to delete recipe'}`);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the recipe.');
      });
    }
  );
}

function deleteRecipeFromModal() {
  deleteRecipe({ stopPropagation: () => {} }, currentRecipeId);
}

function rejectAcceptedRecipe(event, recipeId) {
  event.stopPropagation();
  showConfirmModal(
    'Reject Recipe', 
    'Are you sure you want to reject this accepted recipe? It will be moved to rejected recipes.',
    'Reject',
    'btn-reject',
    () => {
      fetch('process_recipe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `recipe_id=${recipeId}&action=reject_accepted`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Recipe rejected successfully!');
          location.reload();
        } else {
          alert(`Error: ${data.error || 'Failed to reject recipe'}`);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while rejecting the recipe.');
      });
    }
  );
}

function rejectAcceptedRecipeFromModal() {
  rejectAcceptedRecipe({ stopPropagation: () => {} }, currentRecipeId);
}

function acceptRejectedRecipe(event, recipeId) {
  event.stopPropagation();
  showConfirmModal(
    'Accept Recipe', 
    'Are you sure you want to accept this rejected recipe? It will be moved to accepted recipes.',
    'Accept',
    'btn-accept',
    () => {
      fetch('process_recipe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `recipe_id=${recipeId}&action=accept_rejected`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Recipe accepted successfully!');
          location.reload();
        } else {
          alert(`Error: ${data.error || 'Failed to accept recipe'}`);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while accepting the recipe.');
      });
    }
  );
}

function acceptRejectedRecipeFromModal() {
  acceptRejectedRecipe({ stopPropagation: () => {} }, currentRecipeId);
}

function deleteRejectedRecipe(event, recipeId) {
  event.stopPropagation();
  showConfirmModal(
    'Delete Recipe', 
    'Are you sure you want to permanently delete this rejected recipe?',
    'Delete',
    'btn-reject',
    () => {
      fetch('process_recipe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `recipe_id=${recipeId}&action=delete_rejected`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Recipe deleted successfully!');
          location.reload();
        } else {
          alert(`Error: ${data.error || 'Failed to delete recipe'}`);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the recipe.');
      });
    }
  );
}

function deleteRejectedRecipeFromModal() {
  deleteRejectedRecipe({ stopPropagation: () => {} }, currentRecipeId);
}
</script>

</body>
</html>