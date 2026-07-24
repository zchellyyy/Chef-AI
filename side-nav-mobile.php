<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ==========================
       STATIC NAVIGATION STYLES - RENAMED CLASSES
    ========================== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        scrollbar-width: thin;
        scrollbar-color: #C1856D transparent;
    }

    body {
        margin: 0;
        font-family: "Poppins", sans-serif;
        overflow-x: hidden;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    /* Custom scrollbar for webkit browsers */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: transparent;
    }

    ::-webkit-scrollbar-thumb {
        background: #C1856D;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #6C4E31;
    }

    .nav-static-navbar {
        background: linear-gradient(135deg, #C1856D 0%, #6C4E31 100%);
        color: #F7FFF7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: static;
        z-index: 100;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        width: 100%;
        height: 70px;
        box-sizing: border-box;
        padding: 0 40px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-navbar-logo {
        font-size: 28px;
        font-weight: 700;
        color: #FFE66D;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        white-space: nowrap;
        padding: 8px 16px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .nav-navbar-logo:hover {
        transform: translateY(-2px) scale(1.05);
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 25px rgba(255, 230, 109, 0.3);
    }

    .nav-static-navbar ul {
        display: flex;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
        align-items: center;
    }

    .nav-static-navbar ul li a {
        text-decoration: none;
        color: #F7FFF7;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 12px;
        white-space: nowrap;
        font-size: 15px;
        position: relative;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid transparent;
        text-align: center;
        justify-content: center;
        min-width: 120px;
    }

    .nav-static-navbar ul li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease;
    }

    .nav-static-navbar ul li a:hover::before {
        left: 100%;
    }

    .nav-static-navbar ul li a:hover {
        color: #FFE66D;
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
        border-color: rgba(255, 230, 109, 0.3);
        box-shadow: 0 6px 20px rgba(255, 230, 109, 0.2);
    }

    .nav-static-navbar ul li a.nav-active {
        color: #FFE66D;
        background: linear-gradient(135deg, rgba(255, 230, 109, 0.2) 0%, rgba(193, 133, 109, 0.3) 100%);
        border: 1px solid rgba(255, 230, 109, 0.4);
        box-shadow: 0 4px 15px rgba(255, 230, 109, 0.3);
        transform: translateY(-2px);
    }

    .nav-static-navbar ul li a.nav-active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 30px;
        height: 3px;
        background: #FFE66D;
        border-radius: 2px;
        animation: nav-pulse 2s infinite;
    }

    .nav-static-navbar ul li a i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .nav-static-navbar ul li a:hover i {
        transform: scale(1.2);
    }

    .nav-static-navbar ul li a.nav-active i {
        transform: scale(1.2);
        color: #FFE66D;
    }

    /* Logout button specific styles */
    .nav-logout-btn {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-logout-btn:hover {
        background: linear-gradient(135deg, #ff5252 0%, #d32f2f 100%);
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(255, 82, 82, 0.4);
    }

    @keyframes nav-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    /* Mobile menu button */
    .nav-mobile-menu-btn {
        display: none;
        background: none;
        border: none;
        color: #FFE66D;
        font-size: 24px;
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .nav-mobile-menu-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: scale(1.1);
    }

    /* Content area */
    .nav-content {
        padding: 30px;
        min-height: calc(100vh - 70px);
    }

    .nav-content h1 {
        color: #6C4E31;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .nav-content p {
        color: #555;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    /* Modal Styles - RENAMED TO AVOID CONFLICTS */
    .nav-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        backdrop-filter: blur(5px);
    }

    .nav-modal-overlay.nav-active {
        display: flex;
    }

    .nav-modal-content {
        background: #fff6ed !important;
        border-radius: 12px;
        border: none;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        animation: nav-modalSlideIn 0.3s ease-out;
    }

    @keyframes nav-modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .nav-modal-header {
        background: #fff6ed !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .nav-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #6C4E31;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-close-btn {
        background: none;
        border: none;
        color: #6C4E31;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.3s ease;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-close-btn:hover {
        background: rgba(108, 78, 49, 0.1);
        transform: rotate(90deg);
    }

    .nav-modal-body {
        margin-bottom: 25px;
    }

    .nav-modal-text {
        font-size: 1.1rem;
        line-height: 1.5;
        margin: 0;
        text-align: center;
        color: #333;
    }

    .nav-modal-footer {
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .nav-btn {
        padding: 12px 30px;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 120px;
    }

    .nav-btn-danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-btn-danger:hover {
        background: linear-gradient(135deg, #ff5252 0%, #d32f2f 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 82, 82, 0.4);
    }

    .nav-btn-secondary {
        background: #C1856D;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-btn-secondary:hover {
        background: #8A6D56;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(193, 133, 109, 0.4);
    }

    /* Hidden logout form */
    .nav-logout-form {
        display: none;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .nav-static-navbar {
            padding: 0 30px;
        }
        
        .nav-static-navbar ul li a {
            padding: 10px 16px;
            min-width: 110px;
            font-size: 14px;
        }
    }

    @media (max-width: 768px) {
        .nav-static-navbar {
            padding: 0 20px;
            height: auto;
            min-height: 70px;
            flex-wrap: wrap;
        }

        .nav-mobile-menu-btn {
            display: block;
        }

        .nav-static-navbar ul {
            display: none;
            flex-direction: column;
            gap: 5px;
            width: 100%;
            margin-top: 15px;
            background: rgba(108, 78, 49, 0.95);
            border-radius: 12px;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-static-navbar ul.nav-show {
            display: flex;
        }

        .nav-static-navbar ul li {
            width: 100%;
        }

        .nav-static-navbar ul li a {
            width: 100%;
            justify-content: flex-start;
            min-width: auto;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .nav-navbar-logo {
            font-size: 24px;
            padding: 6px 12px;
        }

        .nav-modal-content {
            margin: 20px;
            padding: 25px;
        }

        .nav-modal-footer {
            flex-direction: column;
        }

        .nav-btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .nav-static-navbar {
            padding: 0 15px;
        }
        
        .nav-static-navbar ul li a {
            padding: 10px 12px;
            font-size: 14px;
        }
        
        .nav-navbar-logo {
            font-size: 22px;
        }
        
        .nav-content {
            padding: 20px;
        }

        .nav-modal-content {
            padding: 20px;
        }
    }
  </style>
</head>
<body>
    <nav class="nav-static-navbar" id="navNavbar">
        <a href="" class="nav-navbar-logo">
            <i class="fas fa-utensils"></i> ChefAI
        </a>
        
        <button class="nav-mobile-menu-btn" id="navMobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul id="navNavMenu">
            <li><a href="homeMobile.php" id="nav-home"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="personalizeMobile.php" id="nav-personalize"><i class="fas fa-user-check"></i> Personalized Meal</a></li>
            <li><a href="AIgenerateMobile_recipe.php" id="nav-ai"><i class="fas fa-robot"></i> Chef-AI</a></li>
            <li><a href="settingsMobile.php" id="nav-settings"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="#" class="nav-logout-btn" id="navLogoutLink"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </nav>

    <!-- Logout Confirmation Modal -->
    <div class="nav-modal-overlay" id="navLogoutModal">
        <div class="nav-modal-content">
            <div class="nav-modal-header">
                <h3 class="nav-modal-title">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </h3>
                <button class="nav-close-btn" id="navCloseModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="nav-modal-body">
                <p class="nav-modal-text">Are you sure you want to sign out?</p>
            </div>
            <div class="nav-modal-footer">
                <button class="nav-btn nav-btn-danger" id="navConfirmLogout">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </button>
                <button class="nav-btn nav-btn-secondary" id="navCancelLogout">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden logout form -->
    <form id="navLogoutForm" action="LogoutMobile.php" method="POST" class="nav-logout-form">
        <input type="hidden" name="confirm" value="1">
    </form>
    <script>
        // Highlight current page
        function navHighlightCurrentPage() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = {
                'homeMobile.php': 'nav-home',
                'personalizeMobile.php': 'nav-personalize',
                'AIgenerateMobile_recipe.php': 'nav-ai',
                'settingsMobile.php': 'nav-settings'
            };
            
            if (navLinks[currentPage]) {
                const activeLink = document.getElementById(navLinks[currentPage]);
                if (activeLink) {
                    activeLink.classList.add('nav-active');
                }
            }
        }

        // Mobile menu toggle
        function navSetupMobileMenu() {
            const mobileBtn = document.getElementById('navMobileMenuBtn');
            const navMenu = document.getElementById('navNavMenu');
            
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                navMenu.classList.toggle('nav-show');
                const icon = mobileBtn.querySelector('i');
                if (navMenu.classList.contains('nav-show')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Close mobile menu when clicking on a link
            document.querySelectorAll('#navNavMenu a').forEach(link => {
                link.addEventListener('click', () => {
                    navMenu.classList.remove('nav-show');
                    const icon = mobileBtn.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                });
            });
        }

        // Logout functionality
        function navSetupLogout() {
            const logoutLink = document.getElementById('navLogoutLink');
            const logoutModal = document.getElementById('navLogoutModal');
            const closeModal = document.getElementById('navCloseModal');
            const cancelLogout = document.getElementById('navCancelLogout');
            const confirmLogout = document.getElementById('navConfirmLogout');
            const logoutForm = document.getElementById('navLogoutForm');

            // Open logout modal
            logoutLink.addEventListener('click', (e) => {
                e.preventDefault();
                logoutModal.classList.add('nav-active');
            });

            // Close modal functions
            function navCloseLogoutModal() {
                logoutModal.classList.remove('nav-active');
            }

            closeModal.addEventListener('click', navCloseLogoutModal);
            cancelLogout.addEventListener('click', navCloseLogoutModal);

            // Confirm logout
            confirmLogout.addEventListener('click', () => {
                logoutForm.submit();
            });

            // Close modal when clicking outside
            logoutModal.addEventListener('click', (e) => {
                if (e.target === logoutModal) {
                    navCloseLogoutModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && logoutModal.classList.contains('nav-active')) {
                    navCloseLogoutModal();
                }
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navNavMenu');
            const mobileBtn = document.getElementById('navMobileMenuBtn');
            
            if (!navMenu.contains(event.target) && !mobileBtn.contains(event.target)) {
                navMenu.classList.remove('nav-show');
                const icon = mobileBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            navHighlightCurrentPage();
            navSetupMobileMenu();
            navSetupLogout();
        });
    </script>
</body>
</html>