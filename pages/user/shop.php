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
// $xmlFile = __DIR__ . '/../../xml/products.xml';
// $cartXmlFile = __DIR__ . '/../../xml/cart.xml';

$xmlFile = '../../xml/products.xml';
$cartXmlFile = '../../xml/cart.xml';

function loadXML($file)
{
    if (!file_exists($file)) {
        die('XML file does not exist.');
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

// Load XML data
$xml = loadXML($xmlFile);

// Get categories
$categories = [];
if (isset($xml->categories->category)) {
    foreach ($xml->categories->category as $category) {
        $categories[] = (string)$category->name;
    }
}

function loadCartXML($file) {
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cart></cart>');
        $xml->asXML($file);
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading cart XML file.');
    }
    return $xml;
}

function addToCart($cartFile, $userId, $productData) {
    $xml = loadCartXML($cartFile);
    
    // Check if item already exists in cart for this user
    $existingItem = null;
    foreach ($xml->item as $item) {
        if ((int)$item->user_id == $userId && 
            (int)$item->product_id == $productData['id'] &&
            (string)$item->color == $productData['color'] &&
            (string)$item->size == $productData['size']) {
            $existingItem = $item;
            break;
        }
    }
    
    if ($existingItem) {
        // Update quantity if item exists
        $existingItem->quantity = (int)$existingItem->quantity + (int)$productData['quantity'];
    } else {
        // Add new item to cart
        $item = $xml->addChild('item');
        $item->addChild('id', 'cart_' . uniqid());
        $item->addChild('user_id', $userId);
        $item->addChild('product_id', $productData['id']);
        $item->addChild('product_name', htmlspecialchars($productData['name']));
        $item->addChild('image', htmlspecialchars($productData['image']));
        $item->addChild('price', $productData['price']);
        $item->addChild('color', htmlspecialchars($productData['color']));
        $item->addChild('size', htmlspecialchars($productData['size']));
        $item->addChild('quantity', $productData['quantity']);
        $item->addChild('timestamp', date('Y-m-d H:i:s'));
    }
    
    $xml->asXML($cartFile);
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $productId = (int)$_POST['product_id'];
    $color = htmlspecialchars($_POST['color']);
    $size = htmlspecialchars($_POST['size']);
    $quantity = (int)$_POST['quantity'];
    
    // Load products to get product details
    $xml = loadXML($xmlFile);
    $product = null;
    
    foreach ($xml->products->product as $prod) {
        if ((int)$prod->id == $productId) {
            $stock = (int)$prod->stock;
            if ($quantity > $stock) {
                header("Location: shop.php?error=Requested quantity exceeds available stock of $stock");
                exit;
            }
            $product = [
                'id' => (int)$prod->id,
                'name' => (string)$prod->name,
                'price' => (float)$prod->price,
                // 'image' => (string)$prod->image,
                'image' => (string)$prod->image ? '../' . (string)$prod->image : '../../assets/image/products/no-image.png',
                'color' => $color,
                'size' => $size,
                'quantity' => $quantity,
                'stock' => $stock
            ];
            break;
        }
    }
    
    if ($product) {
        addToCart($cartXmlFile, $currentUser['user_id'], $product);
        header("Location: shop.php?message=Item added to cart successfully");
        exit;
    } else {
        header("Location: shop.php?error=Product not found");
        exit;
    }
}

// Pagination settings
$itemsPerPage = 6;
$maxPages = 6;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$currentCategory = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'all';

// Filter products by category and collect color/size options
$products = [];
if (isset($xml->products->product)) {
    foreach ($xml->products->product as $product) {
        $colors = [];
        if (isset($product->colors->color)) {
            foreach ($product->colors->color as $color) {
                $colors[] = (string)$color;
            }
        }
        $sizes = [];
        if (isset($product->sizes->size)) {
            foreach ($product->sizes->size as $size) {
                $sizes[] = (string)$size;
            }
        }
        $products[] = [
            'id' => (int)$product->id,
            'name' => (string)$product->name,
            'category' => (string)$product->category,
            'price' => (float)$product->price,
            'currency' => (string)$product->currency,
            'image' => (string)$product->image,
            'description' => (string)$product->description,
            'colors' => $colors,
            'sizes' => $sizes,
            'stock' => (int)$product->stock
        ];
    }
}

$filteredProducts = $currentCategory === 'all' ? $products : array_filter($products, function($product) use ($currentCategory) {
    return $product['category'] === $currentCategory;
});

// Calculate pagination
$totalItems = count($filteredProducts);
$totalPages = min(ceil($totalItems / $itemsPerPage), $maxPages);
$currentPage = min($currentPage, $totalPages ?: 1);
$start = ($currentPage - 1) * $itemsPerPage;
$paginatedProducts = array_slice($filteredProducts, $start, $itemsPerPage);

// Convert products array to JSON for JavaScript
$productsJson = json_encode($products);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Shop</title>
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
            --primary-hover: #2c2c2c;
            --secondary-color: #f43f5e;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --accent-color: #8b5cf6;
            --accent-hover: #4f46e5;
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--background-color);
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .button:hover {
            background: var(--gradient);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .button.secondary {
            background: var(--card-bg);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .button.secondary:hover {
            background: var(--light-gray);
            box-shadow: var(--shadow-md);
        }

        .button.success {
            background-color: var(--primary-color);
        }

        .button.success:hover {
            background-color: var(--primary-hover);
        }

        .fade-in {
            animation: fadeIn 1.5s ease-in-out;
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .hover-scale {
            transition: var(--transition);
        }

        .hover-scale:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .navigation {
            text-align: center;
            margin-bottom: 30px;
        }

        .nav-link {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--accent-hover);
            transform: translateX(5px);
        }

        .category-section {
            text-align: center;
            margin-bottom: 50px;
        }

        .category-section h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 30px;
            letter-spacing: -0.5px;
        }

        .category-menu {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .category-btn {
            padding: 10px 20px;
            background-color: var(--card-bg);
            border: 1px solid var(--primary-color);
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .category-btn:hover, .category-btn.active {
            background-color: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .shop h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 50px;
            letter-spacing: -0.5px;
        }

        .shop .grid {
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

        .add-to-cart-btn {
            width: 100%;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-to-cart-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

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

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .message.success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 0;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--dark-gray);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: var(--gray);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-content h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .modal-content .product-details {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .modal-content .product-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .modal-content .product-info h3 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .modal-content .product-info .price {
            font-size: 20px;
            color: var(--accent-color);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .modal-content .option-group {
            margin-bottom: 15px;
        }

        .modal-content .option-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .modal-content .option-group select,
        .modal-content .option-group input[type="number"] {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background-color: var(--inputfield-color);
            transition: var(--transition);
        }

        .modal-content .option-group select:focus,
        .modal-content .option-group input[type="number"]:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .modal-content .button {
            width: 100%;
            margin-top: 20px;
            justify-content: center;
        }

        .close-modal {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        .stock-info {
            font-size: 14px;
            color: var(--gray);
            margin-top: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .shop .grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .category-menu {
                flex-direction: column;
                align-items: center;
            }

            .category-btn {
                width: 200px;
            }

            .shop h2,
            .category-section h2 {
                font-size: 32px;
            }

            .product-card {
                padding: 20px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }

            .modal-content .product-details {
                flex-direction: column;
                align-items: center;
            }

            .modal-content .product-image {
                width: 100%;
                height: auto;
                max-height: 200px;
            }
        }

        @media (max-width: 480px) {
            .shop h2,
            .category-section h2 {
                font-size: 26px;
            }

            .product-card {
                padding: 15px;
            }

            .product-card img {
                height: 220px;
            }

            .modal-content {
                padding: 20px;
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
    <div class="container">
        <!-- Navigation -->
        <div class="navigation">
            <a href="cart.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                View Cart
            </a>
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['message'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Shop by Category Section -->
        <div id="categories" class="category-section">
            <h2 class="fade-in">Shop by Category</h2>
            <div class="category-menu" id="categoryMenu">
                <a href="?category=all" class="category-btn <?php echo $currentCategory === 'all' ? 'active' : ''; ?>">All Products</a>
                <?php foreach ($categories as $category): ?>
                    <a href="?category=<?php echo urlencode($category); ?>" class="category-btn <?php echo $currentCategory === $category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Product Showcase -->
        <div id="shop" class="shop">
            <h2 id="categoryTitle" class="fade-in"><?php echo $currentCategory === 'all' ? 'All Products' : htmlspecialchars($currentCategory); ?></h2>
            <div class="grid" id="productGrid">
                <?php if (empty($paginatedProducts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Products Found</h3>
                        <p>No products found in this category. Please try another category.</p>
                        <a href="?category=all" class="button">View All Products</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($paginatedProducts as $product): ?>
                        <div class="product-card hover-scale">
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="description"><?php echo htmlspecialchars($product['description']); ?></div>
                            <div class="price"><?php echo htmlspecialchars($product['currency']); ?> <?php echo number_format($product['price'], 2); ?></div>
                            <button class="add-to-cart-btn" 
                                    data-product-id="<?php echo $product['id']; ?>" 
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-product-image="<?php echo htmlspecialchars($product['image']); ?>"
                                    data-product-price="<?php echo $product['price']; ?>"
                                    data-product-colors='<?php echo json_encode($product['colors']); ?>'
                                    data-product-sizes='<?php echo json_encode($product['sizes']); ?>'
                                    data-product-stock="<?php echo $product['stock']; ?>">
                                <i class="fas fa-cart-plus"></i>
                                Add to Cart
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" id="pagination">
                    <!-- Previous Button -->
                    <a href="?category=<?php echo urlencode($currentCategory); ?>&page=<?php echo max(1, $currentPage - 1); ?>" 
                       class="page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?category=<?php echo urlencode($currentCategory); ?>&page=<?php echo $i; ?>" 
                           class="page-btn <?php echo $currentPage === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next Button -->
                    <a href="?category=<?php echo urlencode($currentCategory); ?>&page=<?php echo min($totalPages, $currentPage + 1); ?>" 
                       class="page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add to Cart Modal -->
    <div class="modal" id="addToCartModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h2><i class="fas fa-cart-plus"></i> Add to Cart</h2>
            <div class="product-details">
                <img id="modalProductImage" class="product-image" src="" alt="">
                <div class="product-info">
                    <h3 id="modalProductName"></h3>
                    <div id="modalProductPrice" class="price"></div>
                </div>
            </div>
            <form id="addToCartForm" method="POST">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="product_id" id="modalProductId">
                <div class="option-group">
                    <label for="color">Color</label>
                    <select name="color" id="color" required>
                        <option value="">Select Color</option>
                    </select>
                </div>
                <div class="option-group">
                    <label for="size">Size</label>
                    <select name="size" id="size" required>
                        <option value="">Select Size</option>
                    </select>
                </div>
                <div class="option-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity" min="1" value="1" required>
                    <div class="stock-info" id="stockInfo">Available Stock: <span id="stockCount"></span></div>
                </div>
                <button type="submit" class="button success">
                    <i class="fas fa-cart-plus"></i>
                    Confirm Add to Cart
                </button>
            </form>
        </div>
    </div>

    <script>
        // Store products data
        const products = <?php echo $productsJson; ?>;

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
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

            // Modal handling
            const modal = document.getElementById('addToCartModal');
            const closeModal = document.querySelector('.close-modal');
            const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
            const modalProductImage = document.getElementById('modalProductImage');
            const modalProductName = document.getElementById('modalProductName');
            const modalProductPrice = document.getElementById('modalProductPrice');
            const modalProductId = document.getElementById('modalProductId');
            const colorSelect = document.getElementById('color');
            const sizeSelect = document.getElementById('size');
            const quantityInput = document.getElementById('quantity');
            const stockInfo = document.getElementById('stockCount');
            const addToCartForm = document.getElementById('addToCartForm');

            addToCartButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const productId = parseInt(button.dataset.productId);
                    const product = products.find(p => p.id === productId);

                    if (product) {
                        modalProductId.value = productId;
                        modalProductName.textContent = product.name;
                        // modalProductImage.src = product.image;
                        modalProductImage.src = product.image ? `../${product.image}` : '../../assets/image/products/no-image.png';
                        modalProductImage.alt = product.name;
                        modalProductPrice.textContent = `${product.currency} ${parseFloat(product.price).toFixed(2)}`;
                        stockInfo.textContent = product.stock;

                        // Set max attribute for quantity input
                        quantityInput.setAttribute('max', product.stock);

                        // Populate color options
                        colorSelect.innerHTML = '<option value="">Select Color</option>';
                        product.colors.forEach(color => {
                            const option = document.createElement('option');
                            option.value = color;
                            option.textContent = color;
                            colorSelect.appendChild(option);
                        });

                        // Populate size options
                        sizeSelect.innerHTML = '<option value="">Select Size</option>';
                        product.sizes.forEach(size => {
                            const option = document.createElement('option');
                            option.value = size;
                            option.textContent = size;
                            sizeSelect.appendChild(option);
                        });

                        modal.style.display = 'flex';
                    }
                });
            });

            // Prevent scrolling beyond max stock
            quantityInput.addEventListener('input', () => {
                const maxStock = parseInt(quantityInput.getAttribute('max')) || 1;
                const value = parseInt(quantityInput.value);
                if (value > maxStock) {
                    quantityInput.value = maxStock;
                    alert(`Cannot select more than ${maxStock} items. Available stock is ${maxStock}.`);
                } else if (value < 1 || isNaN(value)) {
                    quantityInput.value = 1;
                }
            });

            // Prevent mouse wheel scrolling
            quantityInput.addEventListener('wheel', (e) => {
                e.preventDefault();
            });

            closeModal.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            addToCartForm.addEventListener('submit', (e) => {
                const color = colorSelect.value;
                const size = sizeSelect.value;
                const quantity = parseInt(quantityInput.value);
                const maxStock = parseInt(quantityInput.getAttribute('max')) || 1;

                if (!color || !size || !quantity || quantity < 1) {
                    e.preventDefault();
                    alert('Please select color, size, and a valid quantity.');
                    return false;
                }

                if (quantity > maxStock) {
                    e.preventDefault();
                    quantityInput.value = maxStock;
                    alert(`Cannot add more than ${maxStock} items. Available stock is ${maxStock}.`);
                    return false;
                }

                // Show loading state
                const submitButton = addToCartForm.querySelector('button[type="submit"]');
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                submitButton.disabled = true;
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(-20px)';
                setTimeout(() => message.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>