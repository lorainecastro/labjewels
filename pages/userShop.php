<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - User Shop</title>
    <link rel="icon" type="image/png" href="../assets/image/system/logo.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --white: #ffffff;
            --gray: #6b7280;
            --light-gray: #e5e7eb;
            --dark-gray: #4b5563;
            --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);

            --primary-color: #1a1a1a;
            --primary-gradient: linear-gradient(#8b5cf6);
            --primary-focus: #6366f1;
            --primary-hover: #2c2c2c;
            --secondary-color: #f43f5e;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #101010;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #e5e7eb;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --primary-color: #1a1a1a;
            --whitefont-color: #ffffff;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #2c2c2c;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);

            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--white);
            color: var(--primary-color);
            font-family: 'Inter', -apple-system, sans-serif;
            line-height: 1.7;
            overflow-x: hidden;
        }

        .button {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: inline-block;
            text-decoration: none;
        }

        .button:hover {
            background: var(--gradient);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .button.secondary {
            background: var(--white);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            text-decoration: none;
        }

        .button.secondary:hover {
            background: var(--light-gray);
            box-shadow: var(--shadow-md);
        }

        .fade-in {
            animation: fadeIn 1.5s ease-in-out;
        }

        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hover-scale {
            transition: var(--transition);
        }

        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }

        header {
            background-color: var(--white);
            padding: 20px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        header.scrolled {
            padding: 15px 30px;
            box-shadow: var(--shadow-md);
        }

        nav {
            display: flex;
            align-items: center;
            max-width: 1280px;
            margin: 0 auto;
            justify-content: space-between;
        }

        nav h1 {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            background: var(--primary-color);
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            background: var(--white);
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
        }

        nav ul li {
            margin-left: 35px;
        }

        nav ul li a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            position: relative;
            transition: var(--transition);
        }

        nav ul li a:not(.button):hover {
            color: var(--dark-gray);
        }

        nav ul li a:not(.button)::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        nav ul li a:not(.button):hover::before {
            width: 100%;
        }

        .menu-toggle {
            display: none;
            cursor: pointer;
            z-index: 1000;
            position: relative;
        }

        .bar {
            width: 28px;
            height: 3px;
            background-color: var(--dark-gray);
            margin: 6px 0;
            transition: var(--transition);
        }

        .menu-toggle.active .bar:nth-child(1) {
            transform: rotate(-45deg) translate(-6px, 6px);
        }

        .menu-toggle.active .bar:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.active .bar:nth-child(3) {
            transform: rotate(45deg) translate(-6px, -6px);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--white);
            min-width: 120px;
            box-shadow: var(--shadow-md);
            border-radius: 6px;
            top: 100%;
            right: 0;
            z-index: 1000;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            display: block;
            padding: 10px 15px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            text-transform: none;
        }

        .dropdown-content a:hover {
            background-color: var(--light-gray);
            color: var(--primary-color);
        }

        .search-container {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
            max-width: 300px;
        }

        .search-container input {
            width: 100%;
            padding: 12px 40px 12px 20px;
            border: 1px solid var(--light-gray);
            border-radius: 25px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            background-color: var(--light-gray);
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: var(--white);
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
        }

        .search-container i {
            position: absolute;
            right: 15px;
            color: var(--gray);
            font-size: 16px;
            pointer-events: none;
        }

        .secondary-nav {
            background-color: var(--white);
            padding: 10px 40px;
            border-top: 1px solid var(--light-gray);
            display: none;
            justify-content: flex-end;
            max-width: 1280px;
            margin: 0 auto;
        }

        .footer {
            background: var(--primary-color);
            color: var(--white);
            padding: 80px 20px;
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 3fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .brand-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 27px;
            font-weight: 800;
            color: var(--white);
        }

        .logo svg {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background-color: var(--white);
            padding: 10px;
            fill: var(--primary-color);
        }

        .brand-description {
            font-size: 18px;
            color: var(--light-gray);
            max-width: 320px;
            font-weight: 400;
        }

        .footer-column h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 12px;
        }

        .footer-column h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--white);
            border-radius: 2px;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            color: var(--light-gray);
            text-decoration: none;
            font-size: 16px;
            transition: var(--transition);
            font-weight: 400;
        }

        .footer-links a:hover {
            color: var(--white);
            transform: translateX(5px);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .social-link {
            width: 44px;
            height: 44px;
            background-color: var(--dark-gray);
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            transition: var(--transition);
            font-size: 18px;
            text-decoration: none;
        }

        .social-link:hover {
            background-color: var(--white);
            color: var(--primary-color);
            transform: translateY(-4px);
        }

        .footer-bottom {
            border-top: 1px solid var(--light-gray);
            padding-top: 25px;
            text-align: center;
        }

        .copyright {
            font-size: 16px;
            color: var(--light-gray);
            font-weight: 400;
        }

        .copyright a {
            color: var(--white);
            text-decoration: none;
        }

        .copyright a:hover {
            text-decoration: underline;
        }

        iframe {
            width: 100%;
            height: 600px; /* Adjustable height */
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 992px) {
            .hero h1 {
                font-size: 42px;
            }

            .hero p {
                font-size: 18px;
            }

            .category-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .shop .grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .team .grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .contact .grid {
                grid-template-columns: 1fr;
            }

            .contact .form {
                margin-top: 30px;
            }

            .faq .grid {
                grid-template-columns: 1fr;
            }

            .about .content {
                min-height: auto;
            }

            iframe {
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }

            nav ul {
                position: fixed;
                top: 0;
                right: -100%;
                width: 70%;
                height: 100vh;
                background: var(--gradient);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                transition: right 0.5s ease;
                z-index: 999;
            }

            nav ul.active {
                right: 0;
            }

            nav ul li {
                margin: 25px 0;
            }

            nav ul li a {
                color: var(--white);
                font-size: 18px;
            }

            nav ul li.search-container {
                display: none;
            }

            .menu-toggle {
                display: block;
            }

            .secondary-nav {
                display: flex;
                padding: 10px 20px;
            }

            .search-container {
                max-width: 100%;
            }

            .hero {
                padding: 80px 20px;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 16px;
            }

            .hero .buttons {
                flex-direction: column;
                gap: 15px;
            }

            .hero .button {
                padding: 10px 20px;
                font-size: 14px;
            }

            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .shop h2,
            .about h2,
            .team h2,
            .contact h2 {
                font-size: 32px;
            }

            .shop .grid {
                grid-template-columns: 1fr;
            }

            .team .grid {
                grid-template-columns: 1fr;
            }

            .about .content {
                flex-direction: column;
            }

            .about .content > div {
                width: 100%;
            }

            .footer {
                padding: 50px 20px;
            }

            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-column h4::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .footer-links {
                align-items: center;
            }

            .logo {
                flex-direction: column;
                text-align: center;
            }

            .hero video {
                display: none;
            }

            .hero {
                background: var(--light-gray);
            }

            .hero h1,
            .hero p {
                color: var(--primary-color);
                text-shadow: none;
            }

            iframe {
                height: 450px;
            }
        }

        @media (max-width: 576px) {
            section {
                padding: 50px 15px;
            }

            .hero h1 {
                font-size: 30px;
            }

            .hero p {
                font-size: 15px;
            }

            .hero .button {
                padding: 8px 16px;
                font-size: 13px;
            }

            .category-grid {
                grid-template-columns: 1fr;
            }

            .shop h2,
            .about h2,
            .team h2,
            .contact h2 {
                font-size: 26px;
            }

            .contact .info h3,
            .contact .form h3 {
                font-size: 22px;
            }

            .footer {
                padding: 40px 15px;
            }

            .copyright {
                font-size: 14px;
            }

            iframe {
                height: 400px;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 15px 20px;
            }

            nav ul {
                position: fixed;
                top: 0;
                right: -100%;
                width: 70%;
                height: 100vh;
                background: var(--gradient);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                transition: right 0.5s ease;
                z-index: 999;
            }

            nav ul.active {
                right: 0;
            }

            nav ul li {
                margin: 25px 0;
            }

            nav ul li a {
                color: var(--white);
                font-size: 18px;
            }

            nav ul li.search-container {
                display: none;
            }

            .menu-toggle {
                display: block;
            }

            .secondary-nav {
                display: flex;
                padding: 10px 20px;
            }

            .search-container {
                max-width: 100%;
            }

            .hero h1 {
                font-size: 26px;
            }

            .hero p {
                font-size: 14px;
            }

            .hero .button {
                padding: 6px 12px;
                font-size: 12px;
            }

            .shop h2,
            .category-section h2,
            .about h2,
            .team h2,
            .contact h2 {
                font-size: 22px;
            }

            .contact .info h3,
            .contact .form h3 {
                font-size: 20px;
            }

            iframe {
                height: 350px;
            }
        }

        /* Main Content */
        .main-content {
            transition: var(--transition);
            position: relative;
            height: 100%;
        }

        .dashboard-body {
            display: flex;
            flex-grow: 1;
            overflow: hidden;
            padding: 0;
            height: 100%;
        }

        #dashboard-frame {
            flex-grow: 1;
            width: 100%;
            height: 100%;
            border: none;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header>
        <!-- <nav>
            <h1>LAB Jewels</h1>
            <div class="menu-toggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
            <ul>
                <li class="search-container">
                    <input type="text" placeholder="Search jewelry...">
                    <i class="fas fa-search"></i>
                </li>
                <li><a href="#home">Home</a></li>
                <li><a href="#categories">Shop</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#cart">Cart</a></li>
                <li class="dropdown">
                    <a href="#"><i class="fas fa-user"></i></a>
                    <div class="dropdown-content">
                        <a href="profile.php">Profile</a>
                        <a href="orders.php">Orders</a>
                        <a href="logout.php">Log Out</a>
                    </div>
                </li>
            </ul>
        </nav> -->
        <nav class="nav-links">
            <h1>LAB Jewels</h1>
            <div class="menu-toggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
                <ul>
                    <li class="search-container">
                        <input type="text" placeholder="Search jewelry...">
                        <i class="fas fa-search"></i>
                    </li>
                    <li><a href="#" class="menu-item" data-page="home">Home</a></li>
                    <li><a href="#" class="menu-item" data-page="categories">Shop</a></li>
                    <li><a href="#" class="menu-item" data-page="about">About</a></li>
                    <li><a href="#" class="menu-item" data-page="contact">Contact</a></li>
                    <!-- <li><a href="#" class="menu-item" data-page="cart">Cart</a></li> -->
                    <li><a href="#" class="menu-item" data-page="cart"><i class="fas fa-shopping-cart" title="Cart"></i></a></li>
                    <li class="dropdown">
                        <a href="#"><i class="fas fa-user"></i></a>
                        <div class="dropdown-content">
                            <a href="#" class="menu-item" data-page="profile">Profile</a>
                            <a href="#" class="menu-item" data-page="orders">Orders</a>
                            <a href="#" class="menu-item" data-page="logout">Log Out</a>
                        </div>
                    </div>
                </ul>
            </nav>
        <!-- <div class="secondary-nav">
            <div class="search-container">
                <input type="text" placeholder="Search jewelry...">
                <i class="fas fa-search"></i>
            </div>
        </div> -->
    </header>

    <div class="main-content">
        <div class="dashboard-body">
            <iframe id="dashboard-frame" src="./user/home.php" frameborder="0"></iframe>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="brand-section">
                <div class="logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                    <h1>LAB Jewels</h1>
                </div>
                <p class="brand-description">Crafting elegance since 2025. Discover jewelry that celebrates your unique style.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="footer-column">
                <h4>Quick Links</h4>
                <div class="footer-links">
                    <a href="#home">Home</a>
                    <a href="#shop">Shop</a>
                    <a href="#about">About</a>
                    <!-- <a href="#team">Team</a> -->
                    <a href="#contact">Contact</a>
                </div>
            </div>
            <div class="footer-column">
                <h4>Support</h4>
                <div class="footer-links">
                    <a href="#">Help Center</a>
                    <a href="#">Returns</a>
                    <a href="#">Shipping</a>
                    <a href="#">FAQs</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="copyright">
                © 2025 <a href="#">LAB Jewels</a>. All Rights Reserved.
            </div>
        </div>
    </footer>

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
                    case 0: // Home
                        pageFile = './user/home.php';
                        break;
                    case 1: // Shop
                        pageFile = './user/shop.php';
                        break;
                    case 2: // About
                        pageFile = './user/about.php';
                        break;
                    case 3: // Contact
                        pageFile = './user/contact.php';
                        break;
                    case 4: // Cart
                        pageFile = './user/cart.php';
                        break;
                    case 5: // Profile
                        pageFile = './user/profile.php';
                        break;
                    case 6: // Categories
                        pageFile = './user/orders.php';
                        break;
                    case 7: // Log Out
                        if (confirm('Are you sure you want to log out?')) {
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
            loadPage('./user/home.php');
        });

    </script>

    <script>
        // Mobile menu toggle
        const toggleMenu = () => {
            const menu = document.querySelector('nav ul');
            const toggle = document.querySelector('.menu-toggle');
            menu.classList.toggle('active');
            toggle.classList.toggle('active');
        };
        document.querySelector('.menu-toggle').addEventListener('click', toggleMenu);

        // Header Scroll Effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            header.classList.toggle('scrolled', window.scrollY > 50);
        });

        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
                const menu = document.querySelector('nav ul');
                const toggle = document.querySelector('.menu-toggle');
                menu.classList.remove('active');
                toggle.classList.remove('active');
            });
        });

        // Optional: Adjust iframe height dynamically based on content
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            iframe.onload = () => {
                iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
            };
        });
    </script>
</body>

</html>