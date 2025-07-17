<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Minimalist Jewelry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #6b7280;
            --light-gray: #e5e7eb;
            --dark-gray: #4b5563;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--white);
            color: var(--black);
            font-family: 'Inter', -apple-system, sans-serif;
            line-height: 1.7;
            overflow-x: hidden;
        }

        .button {
            background-color: var(--black);
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
            color: var(--black);
            border: 1px solid var(--black);
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
            background: var(--black);
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
            color: var(--black);
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
            background-color: var(--black);
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
            color: var(--black);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            text-transform: none;
        }

        .dropdown-content a:hover {
            background-color: var(--light-gray);
            color: var(--black);
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
            border-color: var(--black);
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

        section {
            padding: 80px 20px;
        }

        .hero {
            position: relative;
            padding: 150px 20px;
            text-align: center;
            text-decoration: none;
            overflow: hidden;
        }

        .hero video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
            opacity: 1;
        }

        .hero .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .hero h1 {
            font-size: 56px;
            font-weight: 900;
            color: var(--white);
            margin-bottom: 25px;
            letter-spacing: -1px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .hero p {
            font-size: 22px;
            color: var(--white);
            max-width: 700px;
            margin: 0 auto 40px;
            font-weight: 400;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .hero .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            text-decoration: none;
        }

        .category-section {
            max-width: 1280px;
            margin: 0 auto;
            text-align: center;
            margin-bottom: -50px;
        }

        .category-section h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--black);
            margin-bottom: 50px;
            letter-spacing: -0.5px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 30px;
        }

        .category-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .category-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: var(--transition);
        }

        .category-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            transition: var(--transition);
        }

        .category-card:hover .overlay {
            opacity: 1;
        }

        .category-card:hover img {
            transform: scale(1.05);
        }

        .category-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--white);
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 1;
        }

        .about,
        .team {
            max-width: 1280px;
            margin: 0 auto;
        }

        .about h2,
        .team h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--black);
            text-align: center;
            margin-bottom: 50px;
            letter-spacing: -0.5px;
        }

        .about .content {
            display: flex;
            gap: 40px;
            align-items: stretch;
            justify-content: space-between;
            min-height: 400px;
        }

        .about .content>div {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .about p {
            font-size: 18px;
            color: var(--gray);
            margin-bottom: 25px;
            font-weight: 400;
        }

        .about .card {
            background-color: var(--white);
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            padding: 30px;
            position: relative;
            box-shadow: 0 0 10px #e5e7eb;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .about .card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .team .grid {
            width: 1000px;
            display: grid;
            margin: 0 auto;
            grid-template-columns: repeat(4, 1fr);
            justify-content: center;
        }

        .grid {
            justify-content: center;
            align-items: center;
        }

        .team-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            margin: 0 auto;
        }

        .team-card img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: block;
            transition: var(--transition);
        }

        .team-card img:hover {
            transform: scale(1.02);
        }

        .team-card h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--black);
            margin-bottom: 12px;
        }

        .team-card p.position {
            font-size: 18px;
            color: var(--gray);
            margin-bottom: 20px;
        }

        .team-card .social-links {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .team-card .social-link {
            width: 36px;
            height: 36px;
            background-color: var(--light-gray);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--black);
            transition: var(--transition);
            font-size: 16px;
            text-decoration: none;
        }

        .team-card .social-link:hover {
            background-color: var(--black);
            color: var(--white);
            transform: translateY(-2px);
        }

        #about {
            margin-top: -50px;
        }

        #team {
            margin-top: -50px;
        }

        .footer {
            background: var(--black);
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
            fill: var(--black);
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
            color: var(--black);
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

        /* Updated media queries */
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
                /* 2 columns for medium screens */
                gap: 25px;
                max-width: 800px;
                /* Reduced max-width for medium screens */
                margin: 0 auto;
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
                /* 1 column for smaller screens */
                gap: 20px;
                max-width: 500px;
                /* Centered for smaller screens */
                margin: 0 auto;
            }

            .team-card {
                max-width: 280px;
                /* Slightly smaller cards for mobile */
            }

            .about .content {
                flex-direction: column;
            }

            .about .content>div {
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
                color: var(--black);
                text-shadow: none;
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

            .team .grid {
                grid-template-columns: 1fr;
                gap: 15px;
                max-width: 350px;
                /* Adjusted for smaller screens */
            }

            .team-card {
                max-width: 260px;
                /* Smaller cards for very small screens */
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

            .team .grid {
                grid-template-columns: 1fr;
                gap: 15px;
                max-width: 300px;
                /* Even smaller for tiny screens */
            }

            .team-card {
                max-width: 240px;
                /* Adjusted for smallest screens */
            }
        }

        /* Better scrollbar styling for main content */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(99, 102, 241, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: black;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #1c1c1c;
        }
    </style>
</head>

<body>

    <!-- About Section -->
    <section id="about" class="about">
        <h2 class="fade-in">About LAB Jewels</h2>
        <div class="content">
            <div>
                <p>At LAB Jewels, we craft timeless pieces with passion and precision. Each jewel embodies minimalist elegance.</p>
                <p>Our collections are designed to celebrate life's special moments with understated sophistication.</p>
                <h3>Our Craft</h3>
                <p>We source the finest materials to create jewelry that shines with simplicity and durability.</p>
                <a href="#shop" class="button">Explore Collections</a>
            </div>
            <div class="card hover-scale">
                <h3>Our Mission</h3>
                <p>To create jewelry that empowers individuality and celebrates personal style with sustainable, high-quality craftsmanship.</p>
                <h3>Our Vision</h3>
                <p>To redefine minimalist jewelry by blending timeless design with ethical practices, inspiring confidence and elegance worldwide.</p>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="team">
        <h2 class="fade-in">Meet Our Team</h2>
        <div class="grid">
            <div class="team-card">
                <img src="../../assets/image/system/no-icon.png" alt="Loraine Castro">
                <h3>Loraine Castro</h3>
                <p class="position">Founder & CEO</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <img src="../../assets/image/system/no-icon.png" alt="Brian Amador">
                <h3>Brian Amador</h3>
                <p class="position">Head of Design</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <img src="../../assets/image/system/no-icon.png" alt="Anjonette Pedrajas">
                <h3>Anjonette Pedrajas</h3>
                <p class="position">Product Development</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <img src="../../assets/image/system/no-icon.png" alt="Francis Javier">
                <h3>Francis Javier</h3>
                <p class="position">Marketing Director</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>
        </div>
    </section>

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
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
                const menu = document.querySelector('nav ul');
                const toggle = document.querySelector('.menu-toggle');
                menu.classList.remove('active');
                toggle.classList.remove('active');
            });
        });
    </script>
</body>

</html>