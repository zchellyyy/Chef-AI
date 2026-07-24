<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bottom Navigation</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: #fff;
    }

    /* Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      background: #fff;
      display: flex;
      justify-content: space-around;
      align-items: center;
      padding: 10px 0;
      box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
    }

    .bottom-nav a {
      text-decoration: none;
      color: #5a4638;
      font-size: 14px;
      text-align: center;
      flex: 1;
    }

    .bottom-nav a i {
      font-size: 24px;
      display: block;
      margin-bottom: 4px;
    }

    /* Center Button */
    .center-btn {
      background: #3a5f40;
      color: #fff;
      border-radius: 50%;
      height: 70px;
      width: 70px;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      top: -30px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      font-size: 28px;
    }
  </style>
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="menu.php">
      <i class="fa-solid fa-utensils"></i>
      <span>Menu</span>
    </a>

    <a href="home.php" class="center-btn">
      <i class="fa-solid fa-fork-knife"></i>
    </a>

    <a href="cookbook.php">
      <i class="fa-solid fa-book"></i>
      <span>Cookbook</span>
    </a>
  </div>

</body>
</html>
