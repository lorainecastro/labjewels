<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'default-icon.png';

// Load XML files
$xmlFile = __DIR__ . '/../../xml/products.xml';
$cartXmlFile = __DIR__ . '/../../xml/cart.xml';

function loadXML($file) {
    if (!file_exists($file)) {
        die('XML file does not exist.');
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

// Load and filter featured products
function getFeaturedProducts($xml, $page, $itemsPerPage = 6, $maxPages = 6) {
    $products = [];
    foreach ($xml->products->product as $product) {
        if ((string)$product->featured === 'true') {
            $products[] = [
                'id' => (int)$product->id,
                'name' => (string)$product->name,
                'description' => (string)$product->description,
                'price' => (float)$product->price,
                'currency' => (string)$product->currency,
                'image' => (string)$product->image
            ];
        }
    }

    // Pagination logic
    $totalItems = count($products);
    $totalPages = min(ceil($totalItems / $itemsPerPage), $maxPages);
    $currentPage = min(max(1, $page), $totalPages);
    $start = ($currentPage - 1) * $itemsPerPage;
    $paginatedProducts = array_slice($products, $start, $itemsPerPage);

    return [
        'products' => $paginatedProducts,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage
    ];
}

// Get current page from query parameter
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Load XML and get featured products
$xml = loadXML($xmlFile);
$paginationData = getFeaturedProducts($xml, $page);
$featuredProducts = $paginationData['products'];
$totalPages = $paginationData['totalPages'];
$currentPage = $paginationData['currentPage'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Home</title>
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
            --whitefont-color: #ffffff;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #2c2c2c;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --accent-color: #8b5cf6;
            --accent-hover: #4f46e5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--white);
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
            line-height: 1.7;
            overflow-x: hidden;
        }

        .hero {
            background: linear-gradient(135deg, var(--white) 0%, var(--background-color) 100%);
            padding: 80px 20px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="0.5" fill="%23000000" opacity="0.02"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .hero-content {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-family: 'Poppins', serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 30px 0;
            letter-spacing: -0.02em;
            line-height: 1.3;
        }

        .hero h1 span {
            color: #495057;
        }

        .hero .divider {
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, #6c757d, #adb5bd);
            margin: 0 auto 40px auto;
            border-radius: 1px;
        }

        .hero p {
            font-size: 1.3rem;
            color: #495057;
            line-height: 1.7;
            margin: 0;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto;
        }

        .hero p em {
            color: #343a40;
            font-weight: 400;
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .button {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 20px 40px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            transition: left 0.3s ease;
            z-index: -1;
        }

        .button:hover::before {
            left: 0;
        }

        .button:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .button.secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .button.secondary:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .products-preview {
            padding: 120px 20px;
            background: var(--background-color);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 5rem;
        }

        .section-header h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 30px;
            letter-spacing: -0.5px;
        }

        .section-header p {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .product-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-5px);
        }

        .product-card img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .product-card h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .product-card .description {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .product-card .price {
            font-size: 20px;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 20px;
        }

        .hover-scale {
            transition: var(--transition);
        }

        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }

        .cta-section {
            padding: 120px 20px;
            background: var(--primary-color);
            color: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient);
            opacity: 0.9;
        }

        .cta-content {
            position: relative;
            z-index: 2;
        }

        .cta-content h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .cta-content p {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-button {
            background: var(--white);
            color: var(--primary-color);
            padding: 20px 40px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(255, 255, 255, 0.3);
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light-gray);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in:nth-child(1) { animation-delay: 0.1s; }
        .fade-in:nth-child(2) { animation-delay: 0.2s; }
        .fade-in:nth-child(3) { animation-delay: 0.3s; }
        .fade-in:nth-child(4) { animation-delay: 0.4s; }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 40px;
            gap: 8px;
        }

        .page-btn {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            font-size: 14px;
            color: var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .page-btn.active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            font-weight: 600;
        }

        .page-btn:hover:not(.active) {
            background-color: var(--inputfieldhover-color);
            transform: translateY(-1px);
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .hero {
                padding: 80px 20px 60px;
            }

            .hero h1 {
                font-size: 3rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .products-preview,
            .cta-section {
                padding: 80px 20px;
            }

            .section-header h2 {
                font-size: 32px;
            }

            .products-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .cta-content h2 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .button {
                padding: 16px 32px;
                font-size: 14px;
            }

            .section-header h2 {
                font-size: 26px;
            }

            .product-card {
                padding: 15px;
            }

            .product-card img {
                height: 220px;
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
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div>
                <h1>Elegance Crafted to <span>Perfection</span></h1>
                <div class="divider"></div>
                <p>Discover our exquisite collection of handcrafted jewelry, where timeless beauty meets modern sophistication and every piece tells a unique story of <em>artisanal mastery</em>.</p>
                <br>
                <div class="hero-buttons">
                    <a href="#" class="button" onclick="navigateToShop()">
                        <i class="fas fa-gem"></i>
                        Shop Collection
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Preview -->
    <section class="products-preview" id="products-preview">
        <div class="container">
            <div class="section-header">
                <h2 class="fade-in">Featured Collection</h2>
                <p>Explore our most beloved pieces, each carefully selected for their exceptional beauty, craftsmanship, and timeless appeal.</p>
            </div>
            <div id="products-container">
                <?php if (empty($featuredProducts)): ?>
                    <p style="text-align: center; font-size: 18px; color: var(--gray);">No featured products available.</p>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($featuredProducts as $product): ?>
                            <div class="product-card hover-scale">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="description"><?php echo htmlspecialchars($product['description']); ?></div>
                                <div class="price"><?php echo htmlspecialchars($product['currency']); ?> <?php echo number_format($product['price'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pagination" id="pagination">
                <?php if ($totalPages > 1): ?>
                    <a href="?page=<?php echo max(1, $currentPage - 1); ?>#products-preview" class="page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>#products-preview" class="page-btn <?php echo $currentPage === $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>#products-preview" class="page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 3rem;">
                <a href="#" class="button" onclick="navigateToShop()">
                    <i class="fas fa-arrow-right"></i>
                    View All Collections
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Find Your Perfect Piece?</h2>
                <p>Browse our complete collection and discover jewelry that celebrates your unique style and makes every special moment unforgettable.</p>
                <a href="#" class="cta-button" onclick="navigateToShop()">
                    <i class="fas fa-shopping-bag"></i>
                    Start Shopping Now
                </a>
            </div>
        </div>
    </section>

    <script>
        // Function to navigate to shop
        function navigateToShop() {
            if (window.parent && window.parent.document) {
                const shopMenuItem = window.parent.document.querySelector('[data-page="categories"]');
                if (shopMenuItem) {
                    shopMenuItem.click();
                } else {
                    window.location.href = 'shop.php';
                }
            } else {
                window.location.href = 'shop.php';
            }
        }

        // Initialize page animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.animationPlayState = 'paused';
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animationPlayState = 'running';
                        }
                    });
                }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
                observer.observe(el);
            });

            // Apply loading animation to product cards
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>