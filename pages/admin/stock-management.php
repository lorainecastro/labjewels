<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

// $xmlFile = 'products.xml';
$xmlFile = __DIR__ . '/../../xml/products.xml';
// echo realpath($xmlFile); // Outputs the full resolved path or FALSE if the file doesn't exist

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

// Get categories
$xml = loadXML($xmlFile); // Load XML early to use for categories
$categories = [];
if (isset($xml->categories->category)) {
    foreach ($xml->categories->category as $category) {
        $categories[] = (string)$category->name; // Extract the name from the <name> child element
    }
}

function saveXML($xml, $file) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($file);
}

function updateStock($xml, $id, $newStock) {
    foreach ($xml->products->product as $product) {
        if ((int)$product->id == $id) {
            $product->stock = (int)$newStock;
            return saveXML($xml, $GLOBALS['xmlFile']);
        }
    }
    return false;
}

// Handle POST request for stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $xml = loadXML($xmlFile);
    $id = (int)$_POST['id'];
    $newStock = (int)$_POST['new_stock'];
    
    if ($newStock >= 0) {
        if (updateStock($xml, $id, $newStock)) {
            $_SESSION['message'] = 'Stock updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update stock.';
        }
    } else {
        $_SESSION['error'] = 'Stock quantity cannot be negative.';
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Load XML for display
$xml = loadXML($xmlFile);

// Calculate stock metrics
$totalProducts = count($xml->products->product);
$lowStock = 0;
$outOfStock = 0;
$totalStock = 0;
$totalStockValue = 0;
$highestStockProduct = ['name' => 'N/A', 'stock' => 0];
$lowestStockProduct = ['name' => 'N/A', 'stock' => PHP_INT_MAX];

if ($totalProducts > 0) {
    foreach ($xml->products->product as $product) {
        $stock = (int)$product->stock;
        $price = (float)$product->price;
        $totalStock += $stock;
        $totalStockValue += $stock * $price;
        
        if ($stock == 0) {
            $outOfStock++;
        } elseif ($stock <= 5) {
            $lowStock++;
        }
        
        // Update highest stock product
        if ($stock > $highestStockProduct['stock']) {
            $highestStockProduct = ['name' => (string)$product->name, 'stock' => $stock];
        }
        
        // Update lowest stock product
        if ($stock < $lowestStockProduct['stock']) {
            $lowestStockProduct = ['name' => (string)$product->name, 'stock' => $stock];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
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
            --blackfont-color: #1a1a1a;
            --whitefont-color: #ffffff;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #2c2c2c;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
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
            background-color: var(--card-bg);
            color: var(--blackfont-color);
            padding: 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--blackfont-color);
            position: relative;
            padding-bottom: 10px;
        }

        h1:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 3px;
            width: 60px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .bg-purple {
            background: var(--primary-gradient);
        }

        .bg-pink {
            background: var(--secondary-gradient);
        }

        .bg-red {
            background: linear-gradient(135deg, #ef4444, #f87171);
        }

        .bg-blue {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
        }

        .bg-green {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .card-title {
            font-size: 14px;
            color: var(--grayfont-color);
            margin-bottom: 5px;
        }

        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--blackfont-color);
        }

        .card-subtitle {
            font-size: 12px;
            color: var(--grayfont-color);
            margin-top: 5px;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow-md);
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .search-box::before {
            content: "";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .filter-dropdown {
            position: relative;
            min-width: 180px;
        }

        .filter-dropdown select {
            width: 100%;
            padding: 12px 35px 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-dropdown select:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .filter-dropdown::after {
            content: "";
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            pointer-events: none;
        }

        .products-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .product-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow-x: auto;
            display: block;
        }

        .product-table th {
            background-color: var(--division-color);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--grayfont-color);
            font-size: 14px;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 1;
        }

        .product-table td {
            padding: 12px 28px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .product-table tr:last-child td {
            border-bottom: none;
        }

        .product-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .product-table th:nth-child(1),
        .product-table td:nth-child(1) { width: 5%; } /* ID column */
        .product-table th:nth-child(2),
        .product-table td:nth-child(2) { width: 10%; } /* Image column */
        .product-table th:nth-child(3),
        .product-table td:nth-child(3) { width: 20%; } /* Name column */
        .product-table th:nth-child(4),
        .product-table td:nth-child(4) { width: 15%; } /* Category column */
        .product-table th:nth-child(5),
        .product-table td:nth-child(5) { width: 15%; } /* Stock column */
        .product-table th:nth-child(6),
        .product-table td:nth-child(6) { width: 15%; } /* Status column */
        .product-table th:nth-child(7),
        .product-table td:nth-child(7) { width: 10%; } /* Action column */

        .stock-badge.in-stock {
            background-color: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .stock-badge.low-stock {
            background-color: #f59e0b;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .stock-badge.out-of-stock {
            background-color: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5256e0, #7c4ce7);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-backdrop.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            width: 400px;
            max-width: 90%;
            background-color: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transform: translateY(-20px) scale(0.98);
            transition: var(--transition);
        }

        .modal-backdrop.show .modal {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            padding: 24px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--blackfont-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--grayfont-color);
            transition: var(--transition);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: var(--blackfont-color);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--blackfont-color);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 15px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-focus);
            background-color: var(--inputfieldhover-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background-color: var(--division-color);
        }

        .status-message {
            position: fixed;
            bottom: 25px;
            right: 25px;
            padding: 16px 28px 16px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            z-index: 1100;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .success-message {
            background-color: #10b981;
            border-left: 6px solid #059669;
        }

        .success-message::before {
            content: "";
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .error-message {
            background-color: #ef4444;
            border-left: 6px solid #dc2626;
        }

        .error-message::before {
            content: "";
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }

        .pagination button {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            font-size: 14px;
            color: var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .pagination button:hover {
            background-color: rgba(99, 102, 241, 0.2);
        }

        .pagination button.active {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            border-color: var(--primary-color);
            border: 1px solid var(--primary-focus);
        }

        .pagination button.active:hover {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .pagination button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .product-table {
                display: block;
                overflow-x: auto;
            }

            .filter-section {
                flex-direction: column;
                padding: 12px;
            }

            .product-table th,
            .product-table td {
                padding: 6px 8px;
            }

            .product-table img {
                max-width: 30px;
                max-height: 30px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
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
            background: #8b5cf6;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #8b5cf6;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Stock Management</h1>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Stock Value</div>
                        <div class="card-value">₱<?php echo number_format($totalStockValue, 2); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Products</div>
                        <div class="card-value"><?php echo number_format($totalProducts); ?></div>
                    </div>
                    <div class="card-icon bg-pink">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Low Stock (≤5)</div>
                        <div class="card-value"><?php echo number_format($lowStock); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Out of Stock</div>
                        <div class="card-value"><?php echo number_format($outOfStock); ?></div>
                    </div>
                    <div class="card-icon bg-red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Highest Stock</div>
                        <div class="card-value"><?php echo htmlspecialchars($highestStockProduct['stock']); ?></div>
                        <div class="card-subtitle"><?php echo htmlspecialchars($highestStockProduct['name']); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Lowest Stock</div>
                        <div class="card-value"><?php echo htmlspecialchars($lowestStockProduct['stock']); ?></div>
                        <div class="card-subtitle"><?php echo htmlspecialchars($lowestStockProduct['name']); ?></div>
                    </div>
                    <div class="card-icon bg-red">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="status-message success-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="status-message error-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="search-box" placeholder="Search by name or category..." onkeyup="filterProducts()">
            </div>
            <div class="filter-dropdown">
                <select id="categoryFilter" onchange="filterProducts()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-dropdown">
                <select id="stockStatusFilter" onchange="filterProducts()">
                    <option value="">All Stock Status</option>
                    <option value="in-stock">In Stock</option>
                    <option value="low-stock">Low Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
            </div>
        </div>

        <div class="products-container">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php
                    if (isset($xml->products->product) && count($xml->products->product) > 0) {
                        foreach ($xml->products->product as $product) {
                            $stockClass = '';
                            $stockValue = (int)$product->stock;
                            if ($stockValue == 0) {
                                $stockClass = 'out-of-stock';
                                $stockStatus = 'Out of Stock';
                            } elseif ($stockValue <= 5) {
                                $stockClass = 'low-stock';
                                $stockStatus = 'Low Stock';
                            } else {
                                $stockClass = 'in-stock';
                                $stockStatus = 'In Stock';
                            }
                            
                            echo "<tr class='product-row' data-category='" . htmlspecialchars($product->category) . "' data-status='{$stockClass}'>";
                            echo "<td>" . (int)$product->id . "</td>";
                            
                            $imageSrc = !empty($product->image) ? htmlspecialchars($product->image) : 'https://via.placeholder.com/50';
                            echo "<td><img src='{$imageSrc}' alt='" . htmlspecialchars($product->name) . "' style='max-width: 50px; max-height: 50px; object-fit: cover; border-radius: 4px;'></td>";
                            
                            echo "<td class='product-name'>" . htmlspecialchars($product->name) . "</td>";
                            echo "<td>" . htmlspecialchars($product->category) . "</td>";
                            echo "<td>" . $stockValue . "</td>";
                            echo "<td><span class='stock-badge {$stockClass}'>" . $stockStatus . "</span></td>";
                            echo "<td><button class='btn btn-primary' onclick=\"openStockModal(" . (int)$product->id . ", '" . htmlspecialchars($product->name, ENT_QUOTES) . "', " . $stockValue . ")\"><i class='fas fa-edit'></i> Update</button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- Stock Update Modal -->
        <div class="modal-backdrop" id="stockModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Update Stock</h3>
                    <button class="close-modal" onclick="closeStockModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="stockForm" method="POST">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="id" id="stockProductId">
                        <div class="form-group">
                            <label>Product Name</label>
                            <p id="stockProductName" style="font-weight: 500; margin-bottom: 10px;"></p>
                        </div>
                        <div class="form-group">
                            <label for="currentStock">Current Stock</label>
                            <input type="number" id="currentStock" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="new_stock">New Stock Quantity</label>
                            <input type="number" name="new_stock" id="newStock" class="form-control" min="0" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeStockModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Product data from PHP
        const products = <?php
            $products = [];
            if (isset($xml->products->product)) {
                foreach ($xml->products->product as $p) {
                    $products[] = [
                        'id' => (int)$p->id,
                        'name' => (string)$p->name,
                        'category' => (string)$p->category,
                        'stock' => (int)$p->stock,
                        'image' => (string)$p->image
                    ];
                }
            }
            echo json_encode($products);
        ?>;

        const stockModal = document.getElementById('stockModal');
        const itemsPerPage = 4;
        let currentPage = 1;

        function openStockModal(id, name, currentStock) {
            document.getElementById('stockProductId').value = id;
            document.getElementById('stockProductName').textContent = name;
            document.getElementById('currentStock').value = currentStock;
            document.getElementById('newStock').value = currentStock;
            stockModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeStockModal() {
            stockModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Search and filter functionality
        function filterProducts() {
            const searchTerm = document.getElementById('search-box').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockStatusFilter').value;
            const rows = document.querySelectorAll('.product-row');
            let visibleRows = [];

            rows.forEach(row => {
                const productName = row.querySelector('.product-name').textContent.toLowerCase();
                const productCategory = row.dataset.category.toLowerCase();
                const productStock = row.dataset.status;
                
                let showRow = true;
                
                // Search filter
                if (searchTerm && !productName.includes(searchTerm) && !productCategory.includes(searchTerm)) {
                    showRow = false;
                }
                
                // Category filter
                if (categoryFilter && productCategory !== categoryFilter.toLowerCase()) {
                    showRow = false;
                }
                
                // Stock filter
                if (stockFilter && productStock !== stockFilter) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleRows.push(row);
            });

            updatePagination(visibleRows);
        }

        function updatePagination(rows) {
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) {
                rows.forEach(row => row.style.display = '');
                return;
            }

            // Previous button
            const prevButton = document.createElement('button');
            prevButton.textContent = '←';
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(rows);
                }
            });
            pagination.appendChild(prevButton);

            // Page number buttons
            for (let i = 1; i <= totalPages; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = i === currentPage ? 'active' : '';
                pageButton.addEventListener('click', () => {
                    currentPage = i;
                    displayPage(rows);
                });
                pagination.appendChild(pageButton);
            }

            // Next button
            const nextButton = document.createElement('button');
            nextButton.textContent = '→';
            nextButton.disabled = currentPage === totalPages;
            nextButton.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(rows);
                }
            });
            pagination.appendChild(nextButton);

            displayPage(rows);
        }

        function displayPage(rows) {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const allRows = document.querySelectorAll('.product-row');

            allRows.forEach(row => row.style.display = 'none');
            rows.slice(start, end).forEach(row => row.style.display = '');

            document.querySelectorAll('.pagination button').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent == currentPage && !btn.textContent.includes('←') && !btn.textContent.includes('→')) {
                    btn.classList.add('active');
                }
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === stockModal) {
                closeStockModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && stockModal.classList.contains('show')) {
                closeStockModal();
            }
        });

        // Auto-hide notification messages
        document.addEventListener('DOMContentLoaded', () => {
            const notification = document.getElementById('notification');
            if (notification && notification.classList.contains('show')) {
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 5000);
            }
            filterProducts();
        });

        // Enhanced search with debouncing
        let searchTimeout;
        document.getElementById('search-box').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterProducts, 300);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search-box').focus();
            }
        });

        // Form validation
        document.getElementById('stockForm').addEventListener('submit', (e) => {
            const newStock = parseInt(document.getElementById('newStock').value);
            if (newStock < 0) {
                e.preventDefault();
                alert('Stock quantity cannot be negative');
            }
        });
    </script>
</body>
</html>