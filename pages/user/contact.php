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






        .contact {
            max-width: 1280px;
            margin: 0 auto;
        }

        .contact h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--black);
            text-align: center;
            margin-bottom: 50px;
            letter-spacing: -0.5px;
        }

        .shop .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
        }



        .contact .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: stretch;
        }

        #contact {
            margin-top: -50px;
        }

        .contact .info h3,
        .contact .form h3 {
            font-size: 26px;
            font-weight: 700;
            color: var(--black);
            margin-bottom: 20px;
        }

        .contact .info p {
            font-size: 18px;
            color: var(--gray);
            margin-bottom: 15px;
            font-weight: 400;
        }

        .contact .form {
            background-color: var(--white);
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            padding: 30px;
            transition: var(--transition);
            box-shadow: 0 0 10px #e5e7eb;
        }

        .contact .form input,
        .contact .form textarea {
            width: 100%;
            padding: 14px;
            margin-bottom: 25px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            background-color: var(--white);
        }

        .contact .form input:focus,
        .contact .form textarea:focus {
            outline: none;
            border-color: var(--black);
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
        }

        .contact .form .button {
            width: 100%;
            padding: 14px;
            font-size: 15px;
        }

        .faq {
            background-color: #f9f9f9;
            color: var(--black);
            padding: 60px 20px;
        }

        .faq h2 {
            font-size: 42px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .faq p {
            font-size: 18px;
            color: var(--gray);
            text-align: center;
            margin-bottom: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .faq .grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .faq-item {
            background-color: #f9f9f9;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: var(--transition);
        }

        .faq-item:hover {
            background-color: var(--light-gray);
        }

        .faq-item h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faqQuestion .arrow {
            float: right;
            transition: transform 0.3s ease-in-out;
        }

        .faq-item .content {
            display: none;
            margin-top: 10px;
            font-size: 16px;
            color: var(--gray);
        }

        .faq-item.active .content {
            display: block;
        }

        .arrow {
            transform: rotate(90deg);
        }

        .faq-item.active .arrow {
            transform: rotate(180deg);
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

            .info {
                margin-top: -30px;
                padding: 30px;
                margin-bottom: -50px;
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

            .contact h2 {
                margin-top: 30px;
                font-size: 30px;
            }

            .info {
                margin-top: -30px;
                padding: 30px;
                margin-bottom: -50px;
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

            .contact h2 {
                margin-top: 30px;
                font-size: 30px;
            }

            .info {
                margin-top: -30px;
                padding: 30px;
                margin-bottom: -50px;
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
                /* transition: right 0.5s ease; */
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

            .contact h2 {
                margin-top: 30px;
                font-size: 30px;
            }

            .info {
                margin-top: -30px;
                padding: 30px;
                margin-bottom: -50px;
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

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <h2 class="fade-in">Get in Touch</h2>
        <div class="grid">
            <div class="info">
                <h3>We're Here to Help</h3>
                <p>Have a question or need assistance? Reach out to our team.</p>
                <p><strong>Email:</strong> support@labjewels.com</p>
                <p><strong>Phone:</strong> +1 (555) 987-6543</p>
                <p><strong>Address:</strong> 456 Jewel St, Elegance City, EC 67890</p>
            </div>
            <form class="form">
                <h3>Send a Message</h3>
                <input type="text" name="name" placeholder="Your Name" required>
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
                <button type="submit" class="button">Send Message</button>
            </form>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq">
        <h2 class="fade-in">Frequently Asked Questions</h2>
        <p class="fade-in">Here are answers to common questions about shopping with LAB Jewels. If you need further assistance, feel free to reach out on our Contact Page.</p>
        <div class="grid">
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <h3>What materials are used in LAB Jewels' products? <span class="arrow"> ❯ </span></h3>
                <div class="content">Our jewelry is crafted using high-quality materials such as 925 sterling silver, 14k gold, and ethically sourced gemstones to ensure durability and elegance.</div>
            </div>
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <h3>What is your shipping policy? <span class="arrow"> ❯ </span></h3>
                <div class="content">We offer worldwide shipping with standard delivery in 5-7 business days and express options available. Free shipping applies to orders over $100.</div>
            </div>
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <h3>How can I return or exchange an item? <span class="arrow"> ❯ </span></h3>
                <div class="content">You can return or exchange items within 30 days of delivery. Items must be unworn and in original packaging. Visit our Returns page for instructions.</div>
            </div>
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <h3>Do you offer custom jewelry designs? <span class="arrow"> ❯ </span></h3>
                <div class="content">Yes, we provide custom design services. Contact our team via the Contact Page to discuss your vision and create a unique piece.</div>
            </div>
            <div class="faq-item" onclick="this.classList.toggle('active')">
                <h3>How do I care for my jewelry? <span class="arrow"> ❯ </span></h3>
                <div class="content">To maintain your jewelry’s shine, store it in a dry place, avoid exposure to chemicals, and clean it gently with a soft cloth. Check our Care Guide for more tips.</div>
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