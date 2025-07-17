<?php
require '../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

// $currentUser = validateSession();
// var_dump($currentUser); // Debug: Check the contents of $currentUser
if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'no-icon.png'; // Fallback to default icon

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Admin</title>
    <link rel="icon" type="image/png" href="../assets/image/system/logo.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --primary-gradient: linear-gradient(#8b5cf6);
            --primary-hover: #2c2c2c;
            --secondary-color: #f43f5e;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #101010;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #e5e7eb;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --blackfont-color: #1a1a1a;
            --whitefont-color: #ffffff;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #2c2c2c;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--blackfont-color);
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Navbar Styling */
        .navbar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100%;
            background: var(--nav-color);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            transition: var(--transition);
            overflow: hidden;
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 100px;
            background: linear-gradient(135deg, rgba(44, 44, 44, 0.1), rgba(26, 26, 26, 0.1));
            border-radius: 0 0 0 100%;
            z-index: 0;
            opacity: 1;
        }

        .logo {
            padding: 24px 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--whitefont-color);
            text-decoration: none;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo img {
            margin-right: 10px;
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            padding: 10px 15px;
            flex-grow: 1;
            position: relative;
            z-index: 10;
            overflow-y: auto;
            overflow-x: hidden;
            max-height: calc(100vh - 200px);
        }

        .nav-links::-webkit-scrollbar {
            width: 6px;
        }

        .nav-links::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
            margin: 5px 0;
        }

        .nav-links::-webkit-scrollbar-thumb {
            background: #f9fafb;
            border-radius: 3px;
            transition: var(--transition);
        }

        .nav-links::-webkit-scrollbar-thumb:hover {
            background: #e5e7eb;
        }

        /* Firefox scrollbar styling */
        .nav-links {
            scrollbar-width: thin;
            scrollbar-color: #f9fafb rgba(255, 255, 255, 0.05);
        }

        .menu-title {
            padding: 0.5rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 10px;
            flex-shrink: 0;
        }

        .menu-title:first-child {
            margin-top: 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            margin-bottom: 5px;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--whitefont-color);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .menu-item i {
            margin-right: 14px;
            font-size: 18px;
            transition: var(--transition);
            width: 24px;
            text-align: center;
            color: var(--whitefont-color);
        }

        .menu-item span {
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            color: var(--whitefont-color);
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: var(--transition);
            z-index: -1;
        }

        .menu-item:hover::before {
            left: 100%;
            transition: 0.7s;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .menu-item.active {
            background: var(--primary-gradient);
            box-shadow: 0 4px 15px rgba(26, 26, 26, 0.4);
            animation: pulse 2s infinite;
        }

        /* Pulse animation for active menu item */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(99, 102, 241, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
            }
        }

        .profile {
            display: flex;
            align-items: center;
            padding: 18px;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 10;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .profile:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 5px;
            margin-right: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }

        .profile:hover img {
            transform: scale(1.08);
        }

        .profile span {
            font-size: 15px;
            font-weight: 500;
            color: var(--whitefont-color);
        }

        .theme-toggle {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--whitefont-color);
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }

        .logout-button {
            position: absolute;
            top: -30px;
            right: 18px;
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(26, 26, 26, 0.3);
            transition: var(--transition);
            opacity: 0;
            transform: translateY(10px);
            z-index: 20;
        }

        .profile:hover .logout-button {
            opacity: 1;
            transform: translateY(0);
        }

        .logout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(26, 26, 26, 0.35);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex-grow: 1;
            padding: 0px;
            transition: var(--transition);
            position: relative;
        }

        .dashboard-body {
            display: flex;
            flex-grow: 1;
            overflow: hidden;
            padding: 0;
            height: 100vh;
        }

        #dashboard-frame {
            flex-grow: 1;
            width: 100%;
            height: 100%;
            border: none;
            overflow: hidden;
        }

        /* Responsive design */
        @media (max-width: 1024px) {
            .navbar {
                width: 240px;
            }

            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                width: 70px;
                overflow: hidden;
            }

            .logo {
                font-size: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px 0;
            }

            .logo::before {
                content: 'LAB';
                font-size: 20px;
                font-weight: 700;
                color: var(--whitefont-color);
            }

            .logo img {
                margin-right: 0;
            }

            .menu-item span {
                display: none;
            }

            .menu-item {
                justify-content: center;
                padding: 14px 0;
            }

            .menu-item i {
                margin-right: 0;
                font-size: 20px;
            }

            .profile span {
                display: none;
            }

            .profile {
                justify-content: center;
            }

            .profile img {
                margin-right: 0;
            }

            .theme-toggle {
                position: absolute;
                bottom: 80px;
                left: 50%;
                transform: translateX(-50%);
            }

            .main-content {
                margin-left: 70px;
            }

            .logout-button {
                top: auto;
                bottom: -45px;
                left: 50%;
                transform: translateX(-50%) translateY(10px);
                width: 90%;
                text-align: center;
            }

            .profile:hover .logout-button {
                transform: translateX(-50%) translateY(0);
            }

            .menu-title {
                display: none;
            }

            .nav-links::-webkit-scrollbar {
                width: 4px;
            }
        }

        /* Animations */
        @keyframes float {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-15px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .navbar::before {
            animation: float 8s ease-in-out infinite;
        }

        /* Better scrollbar styling for main content */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--inputfield-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-hover);
        }

        /* Add focus styles for better accessibility */
        button:focus,
        a:focus,
        .menu-item:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Add a style for focus-visible to only show focus ring on keyboard navigation */
        :focus:not(:focus-visible) {
            outline: none;
        }

        /* Notification styles */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            transition: all 0.3s ease;
            color: var(--whitefont-color);
        }

        /* Smooth scrolling for the nav-links */
        .nav-links {
            scroll-behavior: smooth;
        }

        /* Add a subtle fade effect at the top and bottom of scrollable area */
        .nav-links::before {
            content: '';
            position: sticky;
            top: 0;
            height: 10px;
            background: linear-gradient(to bottom, var(--nav-color), transparent);
            z-index: 10;
            pointer-events: none;
        }

        .nav-links::after {
            content: '';
            position: sticky;
            bottom: 0;
            height: 10px;
            background: linear-gradient(to top, var(--nav-color), transparent);
            z-index: 10;
            pointer-events: none;
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .profile span {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .profile span {
                display: inline;
                font-size: 12px;
                /* Smaller font size for mobile */
            }

            .profile {
                justify-content: flex-start;
                /* Align items to the left */
                padding-left: 10px;
                /* Adjust padding */
            }
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 27px;
            font-weight: 800;
            color: white;
        }

        .logo svg {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            padding: 5px;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(to top, white, #8b5cf6);
            background-clip: text;
            color: transparent;
        }
    </style>
</head>

<body>
    <header class="navbar">
        <!-- <a href="#" class="logo">
            <img src="logo-image.png" alt="LAB Logo">
            LAB Jewels
        </a> -->
        <div class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
            </svg>
            <h1>LAB Jewels</h1>
        </div>


        <nav class="nav-links">
            <div class="menu-title">MAIN</div>
            <div class="menu-item active" data-page="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item" data-page="orders">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </div>
            <div class="menu-item" data-page="payment-history">
                <i class="fas fa-credit-card"></i>
                <span>Payment History</span>
            </div>

            <div class="menu-title">MANAGEMENT</div>
            <div class="menu-item" data-page="products">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </div>
            <div class="menu-item" data-page="user-management">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </div>
            <div class="menu-item" data-page="stock-management">
                <i class="fas fa-warehouse"></i>
                <span>Stock Management</span>
            </div>
            <div class="menu-item" data-page="categories">
                <i class="fas fa-list"></i>
                <span>Categories</span>
            </div>

            <div class="menu-title">REPORTS</div>
            <div class="menu-item" data-page="reports">
                <i class="fas fa-file-alt"></i>
                <span>Reports & Analytics</span>
            </div>
            <!-- <div class="menu-item" data-page="analytics">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </div> -->

            <div class="menu-title">ACCOUNT</div>
            <div class="menu-item" data-page="profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </div>
            <div class="menu-item" data-page="logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </div>
        </nav>

        <div class="profile">
            <img src="/assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>"
                alt="Profile picture of <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Admin'); ?>"
                class="profile-image" id="profilePreview"
                onerror="this.src='../assets/image/profile/no-icon.png'; console.log('Image failed: <?php echo htmlspecialchars($profileImageUrl); ?>')">
            <span><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Admin'); ?></span>

            <!-- Logout button, initially hidden -->
            <button id="logoutButton" class="logout-button">Logout</button>

            <button type="button" class="theme-toggle" id="themeToggle">
                <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 6 6 0 0 0 21 12.79z"></path>
                </svg>
            </button>
        </div>

    </header>

    <div class="main-content">
        <div class="dashboard-body">
            <iframe id="dashboard-frame" src="pages/dashboard.php" frameborder="0"></iframe>
        </div>
    </div>

    <script>
        function loadPage(pageFile) {
            const iframe = document.getElementById('dashboard-frame');
            iframe.src = pageFile;
        }

        // Function to set active menu item
        function setActiveMenuItem(clickedItem) {
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Add active class to clicked item
            clickedItem.classList.add('active');
        }

        document.querySelectorAll('.menu-item').forEach((menuItem, index) => {
            menuItem.addEventListener('click', () => {
                setActiveMenuItem(menuItem);

                let pageFile;

                switch (index) {
                    case 0: // Dashboard
                        pageFile = './admin/dashboard.php';
                        break;
                    case 1: // Orders
                        pageFile = './admin/orders.php';
                        break;
                    case 2: // Payment History
                        pageFile = './admin/payment-history.php';
                        break;
                    case 3: // Products
                        // pageFile = './admin/prod-fetch-accurate.php';
                        pageFile = './admin/products.php';
                        break;
                    case 4: // User Management
                        pageFile = './admin/user-management.php';
                        break;
                    case 5: // Stock Management
                        pageFile = './admin/stock-management.php';
                        break;
                    case 6: // Categories
                        pageFile = './admin/categories.php';
                        break;
                    case 7: // Reports
                        pageFile = './admin/reports-and-analytics.php';
                        break;
                        // case 8: // Analytics
                        //     pageFile = './admin/analytics.php';
                        //     break;
                    case 8: // Profile
                        pageFile = './admin/profile.php';
                        break;
                    case 9: // Log Out
                        if (confirm('Are you sure you want to log out?')) {
                            showNotification('Logging out...', 'info');
                            window.location.href = '../functions/destroyer.php';
                        }
                        return;
                    default:
                        pageFile = './pages/404.html';
                }
                loadPage(pageFile);
            });
        });

        // Load default page on window load
        window.addEventListener('load', () => {
            loadPage('./admin/dashboard.php');
        });

        // Logout button functionality
        const logoutButton = document.getElementById('logoutButton');
        const profile = document.querySelector('.profile');

        // Show logout button on hover
        profile.addEventListener('mouseenter', () => {
            logoutButton.style.display = 'block';
        });

        profile.addEventListener('mouseleave', () => {
            logoutButton.style.display = 'none';
        });

        // Handle logout click
        document.getElementById('logoutButton').addEventListener('click', function() {
            if (confirm('Are you sure you want to log out?')) {
                showNotification('Logging out...', 'info');
                window.location.href = '../functions/destroyer.php';
            }
        });

        // Function to show notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

            // Style the notification
            Object.assign(notification.style, {
                position: 'fixed',
                bottom: '20px',
                right: '20px',
                padding: '15px 20px',
                borderRadius: '8px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                zIndex: '9999',
                transition: 'all 0.3s ease',
                transform: 'translateX(100%)',
                color: '#ffffff',
                backgroundColor: type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'
            });

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    </script>
</body>

</html>