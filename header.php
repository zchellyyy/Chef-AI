<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Shared Navigation -->
<nav class="navbar">
    <div class="logo">Chef-AI</div>
    <ul class="nav-links">
        <li><a href="homepage.php">Home</a></li>
        <li><a href="personalized.php">Personalized</a></li>
        <li><a href="AIgenerate_recipe.php">ChefAI</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php">Services</a></li>
        <li><a href="contact.php">Contact</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="logout.php" class="logout-link">Logout</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Sign-out Confirm Modal -->
<div id="logoutModal" class="logout-modal" aria-hidden="true" role="dialog" aria-labelledby="logoutTitle" aria-modal="true">
  <div class="logout-card">
    <h3 id="logoutTitle" class="logout-title">Are you sure you want to sign out?</h3>
    <div class="logout-actions">
      <!-- Left = Sign out -->
      <button type="button" id="confirmLogout" class="btn btn-danger">Sign out</button>
      <!-- Right = Cancel -->
      <button type="button" id="cancelLogout" class="btn btn-secondary">Cancel</button>
    </div>
  </div>
</div>

<style>
/* Minimal, move to your CSS file if you prefer */
.logout-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);z-index:2000}
.logout-modal.show{display:flex}
.logout-card{background:#fff;color:#2b2b2b;width:min(420px,92vw);border-radius:14px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.logout-title{margin:0 0 14px;font-size:18px;font-weight:700}
.logout-actions{display:flex;align-items:center;gap:10px}
.btn{border:0;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600}
.btn-danger{background:#c0392b;color:#fff}     /* left button */
.btn-secondary{background:#eaeaea}            /* right button */
.logout-actions .btn-secondary{margin-left:auto} /* pushes Cancel to the right */
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const logoutLinks = document.querySelectorAll('a.logout-link, a[href*="logout.php"]');
  const modal = document.getElementById('logoutModal');
  const cancelBtn = document.getElementById('cancelLogout');
  const confirmBtn = document.getElementById('confirmLogout');
  let targetHref = 'logout.php';

  logoutLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      targetHref = link.href;               // preserve absolute/relative URL
      modal.classList.add('show');
      setTimeout(() => cancelBtn.focus(), 0);
    });
  });

  cancelBtn.addEventListener('click', () => modal.classList.remove('show'));
  confirmBtn.addEventListener('click', () => { window.location.href = targetHref; });

  // close on ESC or clicking backdrop
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('show'); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.classList.remove('show'); });
});
</script>
