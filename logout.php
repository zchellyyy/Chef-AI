<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['confirm'])) {
    // Show a simple confirm page (fallback when JS is disabled or direct GET)
    $return_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'homepage.php';
    ?>
    <!doctype html>
    <meta charset="utf-8">
    <title>Confirm Sign Out</title>
    <style>
      body{margin:0;background:#f6f7f8;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial}
      .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center}
      .card{background:#fff;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);width:min(420px,92vw)}
      h3{margin:0 0 14px;font-size:18px}
      .actions{display:flex;gap:10px}
      .btn{border:0;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block}
      .btn-danger{background:#c0392b;color:#fff}
      .btn-secondary{background:#eaeaea;color:#222;margin-left:auto}
    </style>
    <div class="wrap">
      <div class="card">
        <h3>Are you sure you want to sign out?</h3>
        <form method="post" class="actions">
          <input type="hidden" name="confirm" value="1">
          <button type="submit" class="btn btn-danger">Sign out</button>
          <a href="<?php echo htmlspecialchars($return_to, ENT_QUOTES); ?>" class="btn btn-secondary">Cancel</a>
        </form>
      </div>
    </div>
    <?php
    exit;
}

// Proceed with logout
$_SESSION = [];
session_destroy();

// Prevent back button cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect to login
header("Location: login.php");
exit;
