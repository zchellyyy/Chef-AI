<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ==========================
       FLOATING NAVIGATION STYLES
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
        padding-top: 70px; /* This should match navbar height */
        min-height: 50vh;
        font-family: "Poppins", sans-serif;
        overflow-x: hidden;
        background: #fff6edff; /* Match your main background */
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

    .floating-navbar {
        background: linear-gradient(135deg, #C1856D 0%, #6C4E31 100%);
        color: #F7FFF7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 5%;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        width: 100%;
        height: 70px;
        box-sizing: border-box;
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .floating-navbar.scrolled {
        background: linear-gradient(135deg, rgba(193, 133, 109, 0.95) 0%, rgba(108, 78, 49, 0.95) 100%);
        height: 60px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }

    .navbar-logo {
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

    .navbar-logo:hover {
        transform: translateY(-2px) scale(1.05);
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 25px rgba(255, 230, 109, 0.3);
    }

    .floating-navbar ul {
        display: flex;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
        align-items: center;
    }

    .floating-navbar ul li a {
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

    .floating-navbar ul li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease;
    }

    .floating-navbar ul li a:hover::before {
        left: 100%;
    }

    .floating-navbar ul li a:hover {
        color: #FFE66D;
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
        border-color: rgba(255, 230, 109, 0.3);
        box-shadow: 0 6px 20px rgba(255, 230, 109, 0.2);
    }

    .floating-navbar ul li a.active {
        color: #FFE66D;
        background: linear-gradient(135deg, rgba(255, 230, 109, 0.2) 0%, rgba(193, 133, 109, 0.3) 100%);
        border: 1px solid rgba(255, 230, 109, 0.4);
        box-shadow: 0 4px 15px rgba(255, 230, 109, 0.3);
        transform: translateY(-2px);
    }

    .floating-navbar ul li a.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 30px;
        height: 3px;
        background: #FFE66D;
        border-radius: 2px;
        animation: pulse 2s infinite;
    }

    .floating-navbar ul li a i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .floating-navbar ul li a:hover i {
        transform: scale(1.2);
    }

    .floating-navbar ul li a.active i {
        transform: scale(1.2);
        color: #FFE66D;
    }

    /* Logout button specific styles */
    .logout-btn {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .logout-btn:hover {
        background: linear-gradient(135deg, #ff5252 0%, #d32f2f 100%);
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(255, 82, 82, 0.4);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    /* Mobile menu button */
    .mobile-menu-btn {
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

    .mobile-menu-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: scale(1.1);
    }

    /* Modal Styles */
    .modal-overlay {
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

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
             background: #fff6edff !important;
         margin: 20px;
            padding: 25px;
             margin: 2% auto;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 96vh;
            overflow-y: hidden;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0px;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: white;
        margin: 0;
    }

    .close-btn {
        background: none;
        border: none;
        color: #FFE66D;
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

    .close-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: rotate(90deg);
    }

    .modal-body {
        margin-bottom: 25px;
    }

    .modal-text {
        font-size: 1.1rem;
        line-height: 1.5;
        margin: 0;
        text-align: center;
    }

    .modal-footer {
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .btn {
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

    .btn-danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #ff5252 0%, #d32f2f 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 82, 82, 0.4);
    }

    .btn-secondary {
        background: #C1856D;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 255, 255, 0.2);
    }

    /* Hidden logout form */
    .logout-form {
        display: none;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .floating-navbar {
            padding: 12px 30px;
        }
        
        .floating-navbar ul li a {
            padding: 10px 16px;
            min-width: 110px;
            font-size: 14px;
        }
    }

    @media (max-width: 768px) {
        body {
            padding-top: 80px;
        }

        .floating-navbar {
            padding: 10px 20px;
            height: auto;
            min-height: 70px;
            flex-wrap: wrap;
        }

        .floating-navbar.scrolled {
            height: auto;
            min-height: 60px;
        }

        .mobile-menu-btn {
            display: block;
        }

        .floating-navbar ul {
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

        .floating-navbar ul.show {
            display: flex;
        }

        .floating-navbar ul li {
            width: 100%;
        }

        .floating-navbar ul li a {
            width: 100%;
            justify-content: flex-start;
            min-width: auto;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .navbar-logo {
            font-size: 24px;
            padding: 6px 12px;
        }

        .modal-content {
            margin: 20px;
            padding: 25px;
        }

        .modal-footer {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .floating-navbar {
            padding: 8px 15px;
        }
        
        .floating-navbar ul li a {
            padding: 10px 12px;
            font-size: 14px;
        }
        
        .navbar-logo {
            font-size: 22px;
        }
    }
  </style>
</head>
<body>
    <nav class="floating-navbar" id="navbar">
        <a href="home.php" class="navbar-logo">
            <i class="fas fa-utensils"></i> ChefAI
        </a>
        
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul id="navMenu">
            <li><a href="home.php" id="nav-home"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="personalize.php" id="nav-personalize"><i class="fas fa-calendar-heart"></i> Personalized Meal</a></li>
            <li><a href="AIgenerate_recipe.php" id="nav-ai"><i class="fas fa-robot"></i> Chef-AI</a></li>
            <li><a href="settings.php" id="nav-settings"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="#" class="logout-btn" id="logoutLink"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </nav>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </h3>
                <button class="close-btn" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="modal-text">Are you sure you want to sign out?</p>
                <p class="modal-text" style="font-size: 0.9rem; margin-top: 10px; color: #FFE66D;">
                    <i class="fas fa-exclamation-triangle"></i> 
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" id="confirmLogout">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </button>
                <button class="btn btn-secondary" id="cancelLogout">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden logout form -->
    <form id="logoutForm" action="logout.php" method="POST" class="logout-form">
        <input type="hidden" name="confirm" value="1">
    </form>

    <script>
        // Highlight current page
        function highlightCurrentPage() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = {
                'home.php': 'nav-home',
                'personalize.php': 'nav-personalize',
                'AIgenerate_recipe.php': 'nav-ai',
                'settings.php': 'nav-settings'
            };
            
            if (navLinks[currentPage]) {
                const activeLink = document.getElementById(navLinks[currentPage]);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }
        }

        // Scroll effect for navbar
        function handleNavbarScroll() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }

        // Mobile menu toggle
        function setupMobileMenu() {
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const navMenu = document.getElementById('navMenu');
            
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                navMenu.classList.toggle('show');
                const icon = mobileBtn.querySelector('i');
                if (navMenu.classList.contains('show')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Close mobile menu when clicking on a link
            document.querySelectorAll('#navMenu a').forEach(link => {
                link.addEventListener('click', () => {
                    navMenu.classList.remove('show');
                    const icon = mobileBtn.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                });
            });
        }

        // Enhanced logout functionality with back button prevention
        function setupLogout() {
            const logoutLink = document.getElementById('logoutLink');
            const logoutModal = document.getElementById('logoutModal');
            const closeModal = document.getElementById('closeModal');
            const cancelLogout = document.getElementById('cancelLogout');
            const confirmLogout = document.getElementById('confirmLogout');
            const logoutForm = document.getElementById('logoutForm');

            // Open logout modal
            logoutLink.addEventListener('click', (e) => {
                e.preventDefault();
                logoutModal.classList.add('active');
            });

            // Close modal functions
            function closeLogoutModal() {
                logoutModal.classList.remove('active');
            }

            closeModal.addEventListener('click', closeLogoutModal);
            cancelLogout.addEventListener('click', closeLogoutModal);

            // Enhanced logout function with back button prevention
            function performLogout() {
                // Set a flag in sessionStorage to indicate logout
                sessionStorage.setItem('userLoggedOut', 'true');
                
                // Clear any existing navigation history
                window.history.replaceState(null, null, window.location.href);
                
                // Add a new state to prevent back navigation
                window.history.pushState(null, null, window.location.href);
                
                // Submit the form to logout
                logoutForm.submit();
            }

            // Confirm logout
            confirmLogout.addEventListener('click', performLogout);

            // Close modal when clicking outside
            logoutModal.addEventListener('click', (e) => {
                if (e.target === logoutModal) {
                    closeLogoutModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                    closeLogoutModal();
                }
            });
        }

        // Back button prevention after logout
        function setupBackButtonPrevention() {
            // Check if user was logged out
            if (sessionStorage.getItem('userLoggedOut') === 'true') {
                // Clear the flag
                sessionStorage.removeItem('userLoggedOut');
                
                // Redirect to login page or home page
                window.location.replace('login.php'); // Change to your login page
                return;
            }

            // Prevent back button navigation after logout
            window.history.pushState(null, null, window.location.href);
            window.addEventListener('popstate', function(event) {
                // Check if we're trying to go back after logout
                if (sessionStorage.getItem('userLoggedOut') === 'true') {
                    sessionStorage.removeItem('userLoggedOut');
                    window.location.replace('login.php'); // Change to your login page
                } else {
                    // Allow normal back navigation for other cases
                    window.history.go(1);
                }
            });
        }

        // Clear navigation history on page load
        function clearNavigationHistory() {
            // Replace current history state to prevent back navigation to previous session
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const mobileBtn = document.getElementById('mobileMenuBtn');
            
            if (!navMenu.contains(event.target) && !mobileBtn.contains(event.target)) {
                navMenu.classList.remove('show');
                const icon = mobileBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            highlightCurrentPage();
            setupMobileMenu();
            setupLogout();
            setupBackButtonPrevention();
            clearNavigationHistory();
            window.addEventListener('scroll', handleNavbarScroll);
            
            // Initial check for scroll position
            handleNavbarScroll();
        });

        // Additional protection against browser caching
        window.addEventListener('pageshow', function(event) {
            // If page was loaded from cache, check logout status
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                if (sessionStorage.getItem('userLoggedOut') === 'true') {
                    sessionStorage.removeItem('userLoggedOut');
                    window.location.replace('login.php'); // Change to your login page
                }
            }
        });
    </script>
</body>
</html>