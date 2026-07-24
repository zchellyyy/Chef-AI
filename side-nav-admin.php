<!-- side-nav-admin.php -->
<style>
  .sidebar {
    width: 220px;
    background: linear-gradient(135deg, rgba(193, 133, 109, 0.95) 0%, rgba(108, 78, 49, 0.95) 100%);
    padding: 20px;
    color: white;
    min-height: 100vh;
  }

  .sidebar h2 {
    margin-bottom: 30px;
    font-size: 20px;
  }

  .nav-menu {
    list-style: none;
    padding-left: 0;
  }

  .nav-menu li {
    margin-bottom: 15px;
  }

  .nav-menu a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 10px;
    border-radius: 4px;
    transition: 0.3s;
  }

  .nav-menu a:hover,
  .nav-menu a.active {
    background-color: #6e5656;
  }

  .nav-menu i {
    margin-right: 10px;
  }

  @media (max-width: 768px) {
    .sidebar {
      display: none;
    }
  }
</style>

<?php
  $current_page = basename($_SERVER['PHP_SELF']);
?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<div class="sidebar">
  <h2>ADMIN</h2>
  <ul class="nav-menu">
    <li><a href="admin.php" class="<?= ($current_page == 'admin.php') ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li><a href="userManagement.php" class="<?= ($current_page == 'userManagement.php') ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a></li>
    <li><a href="logout.php" class="<?= ($current_page == 'listRecipe.php') ? 'active' : '' ?>"><i class="fa-solid fa-right-to-bracket"></i> back to login</a></li>
 
  </ul>
</div>
