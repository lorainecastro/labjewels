<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$ordersXmlFile = __DIR__ . '/../../xml/orders.xml';
$productsXmlFile = __DIR__ . '/../../xml/products.xml';
$cartXmlFile = __DIR__ . '/../../xml/cart.xml';

// Load XML files
function loadXML($file) {
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root></root>');
        $xml->asXML($file);
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

// Load orders, products, and cart
$ordersXml = loadXML($ordersXmlFile);
$productsXml = loadXML($productsXmlFile);
$cartXml = loadXML($cartXmlFile);

// Process orders data
$orders = [];
$totalSales = 0;
$statusCounts = ['pending' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
$paymentMethods = [];
$productSales = [];
$categorySales = [];
$userOrders = [];

foreach ($ordersXml->order as $order) {
    $orderData = [
        'order_id' => (string)$order->order_id,
        'user_id' => (int)$order->user_id,
        'timestamp' => (string)$order->timestamp,
        'payment_method' => (string)$order->payment_method,
        'payment_proof' => isset($order->payment_proof) ? (string)$order->payment_proof : '',
        'status' => (string)$order->status,
        'shipping_fee' => (float)$order->shipping_fee,
        'items' => []
    ];

    $orderTotal = 0;
    foreach ($order->items->item as $item) {
        $itemData = [
            'product_id' => (int)$item->product_id,
            'product_name' => (string)$item->product_name,
            'image' => (string)$item->image,
            'price' => (float)$item->price,
            'color' => (string)$item->color,
            'size' => (string)$item->size,
            'quantity' => (int)$item->quantity
        ];
        $orderData['items'][] = $itemData;
        $orderTotal += $itemData['price'] * $itemData['quantity'];

        // Track product sales
        $productSales[$itemData['product_id']] = ($productSales[$itemData['product_id']] ?? 0) + $itemData['quantity'];

        // Track category sales
        foreach ($productsXml->products->product as $product) {
            if ((int)$product->id === $itemData['product_id']) {
                $category = (string)$product->category;
                $categorySales[$category] = ($categorySales[$category] ?? 0) + ($itemData['price'] * $itemData['quantity']);
                break;
            }
        }
    }

    $orderTotal += $orderData['shipping_fee'];
    $totalSales += $orderTotal;
    $orders[] = $orderData;

    // Track status counts
    $statusCounts[$orderData['status']]++;

    // Track payment methods
    $paymentMethods[$orderData['payment_method']] = ($paymentMethods[$orderData['payment_method']] ?? 0) + 1;

    // Track user orders
    $userOrders[$orderData['user_id']] = ($userOrders[$orderData['user_id']] ?? 0) + 1;
}

// Get top products
arsort($productSales);
$topProducts = array_slice($productSales, 0, 5, true);

// Get category with most sales
arsort($categorySales);
$topCategory = key($categorySales);

// Get category with most stock
$categoryStock = [];
foreach ($productsXml->products->product as $product) {
    $category = (string)$product->category;
    $stock = (int)$product->stock;
    $categoryStock[$category] = ($categoryStock[$category] ?? 0) + $stock;
}
arsort($categoryStock);
$topStockCategory = key($categoryStock);
$topStockValue = $categoryStock[$topStockCategory];

// Get top customer
arsort($userOrders);
$topCustomerId = key($userOrders);

// Get user details
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE user_id = ?");
$stmt->execute([$topCustomerId]);
$topCustomer = $stmt->fetch();

// Get most used payment method
arsort($paymentMethods);
$mostUsedPaymentMethod = key($paymentMethods);

// Process cart data
$cartItems = [];
$totalCartItems = 0;
$totalCartValue = 0;
$cartProductCounts = [];
foreach ($cartXml->item as $item) {
    $itemData = [
        'id' => (string)$item->id,
        'user_id' => (int)$item->user_id,
        'product_id' => (int)$item->product_id,
        'product_name' => (string)$item->product_name,
        'price' => (float)$item->price,
        'quantity' => (int)$item->quantity,
        'timestamp' => (string)$item->timestamp
    ];
    $cartItems[] = $itemData;
    $totalCartItems += $itemData['quantity'];
    $totalCartValue += $itemData['price'] * $itemData['quantity'];
    $cartProductCounts[$itemData['product_id']] = ($cartProductCounts[$itemData['product_id']] ?? 0) + $itemData['quantity'];
}

// Get most popular product in cart
arsort($cartProductCounts);
$mostPopularCartProductId = key($cartProductCounts);
$mostPopularCartProductName = '';
foreach ($productsXml->products->product as $product) {
    if ((int)$product->id === $mostPopularCartProductId) {
        $mostPopularCartProductName = (string)$product->name;
        break;
    }
}

// Handle filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$paymentFilter = isset($_GET['payment']) ? $_GET['payment'] : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$productFilter = isset($_GET['product']) ? $_GET['product'] : '';
$dateRangeFilter = isset($_GET['dateRange']) ? $_GET['dateRange'] : 'all';

$filteredOrders = $orders;
if ($statusFilter && $statusFilter !== 'all') {
    $filteredOrders = array_filter($orders, function($order) use ($statusFilter) {
        return $order['status'] === $statusFilter;
    });
}
if ($paymentFilter && $paymentFilter !== 'all') {
    $filteredOrders = array_filter($filteredOrders, function($order) use ($paymentFilter) {
        return $order['payment_method'] === $paymentFilter;
    });
}
if ($dateRangeFilter !== 'all') {
    $today = new DateTime();
    $filteredOrders = array_filter($filteredOrders, function($order) use ($dateRangeFilter, $today) {
        $orderDate = new DateTime($order['timestamp']);
        switch ($dateRangeFilter) {
            case 'today':
                return $orderDate->format('Y-m-d') === $today->format('Y-m-d');
            case 'week':
                $weekStart = (clone $today)->modify('Monday this week');
                return $orderDate >= $weekStart && $orderDate <= $today;
            case 'month':
                $monthStart = (clone $today)->modify('first day of this month');
                return $orderDate >= $monthStart && $orderDate <= $today;
            case 'quarter':
                $quarterStart = (clone $today)->modify('-3 months');
                return $orderDate >= $quarterStart && $orderDate <= $today;
            case 'year':
                $yearStart = (clone $today)->modify('first day of January this year');
                return $orderDate >= $yearStart && $orderDate <= $today;
            default:
                return true;
        }
    });
}

// Get total orders
$totalOrders = count($orders);

// Export to PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_pdf') {
    require_once('../../TCPDF-main/TCPDF-main/tcpdf.php');
    
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('LAB Jewels');
    $pdf->SetTitle('Analytics Report');
    $pdf->SetHeaderData('', 0, 'Analytics Report', 'Generated on ' . date('Y-m-d H:i:s'));
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetMargins(10, 20, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    
    $html = '<h1>Analytics Report</h1>';
    $html .= '<h2>Summary</h2>';
    $html .= '<p><strong>Total Orders:</strong> ' . number_format($totalOrders) . '</p>';
    $html .= '<p><strong>Total Sales:</strong> PHP ' . number_format($totalSales, 2) . '</p>';
    $html .= '<p><strong>Pending Orders:</strong> ' . $statusCounts['pending'] . '</p>';
    $html .= '<p><strong>Shipped Orders:</strong> ' . $statusCounts['shipped'] . '</p>';
    $html .= '<p><strong>Delivered Orders:</strong> ' . $statusCounts['delivered'] . '</p>';
    $html .= '<p><strong>Cancelled Orders:</strong> ' . $statusCounts['cancelled'] . '</p>';
    $html .= '<p><strong>Most Used Payment Method:</strong> ' . htmlspecialchars($mostUsedPaymentMethod) . '</p>';
    $html .= '<p><strong>Top Category (Sales):</strong> ' . htmlspecialchars($topCategory) . '</p>';
    $html .= '<p><strong>Top Category (Stock):</strong> ' . htmlspecialchars($topStockCategory) . ' (' . number_format($topStockValue) . ' units)</p>';
    $html .= '<p><strong>Top Customer:</strong> ' . htmlspecialchars($topCustomer['firstname'] . ' ' . $topCustomer['lastname']) . '</p>';
    $html .= '<p><strong>Total Cart Items:</strong> ' . number_format($totalCartItems) . '</p>';
    $html .= '<p><strong>Total Cart Value:</strong> PHP ' . number_format($totalCartValue, 2) . '</p>';
    $html .= '<p><strong>Most Popular Cart Product:</strong> ' . htmlspecialchars($mostPopularCartProductName) . '</p>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('analytics_report.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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

        .btn-reset {
            background: var(--secondary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #ec4899, #f43f5e);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-pdf {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-pdf:hover {
            background: linear-gradient(135deg, #5256e0, #7c4ce7);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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

        .bg-blue {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
        }

        .bg-green {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .bg-orange {
            background: linear-gradient(135deg, #f97316, #fb923c);
        }

        .bg-red {
            background: linear-gradient(135deg, #ef4444, #f87171);
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

        .filter-section {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .filter-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--blackfont-color);
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
        }

        .chart-filter {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            border: none;
            background: var(--inputfield-color);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .filter-btn:hover:not(.active) {
            background: var(--inputfieldhover-color);
        }

        .chart-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .dashboard-grid, .analytics-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid, .analytics-grid {
                grid-template-columns: 1fr;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Analytics</h1>
            <div>
                <button class="btn btn-pdf" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export Analytics
                </button>
            </div>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Orders</div>
                        <div class="card-value"><?php echo number_format($totalOrders); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Sales</div>
                        <div class="card-value">PHP <?php echo number_format($totalSales, 2); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-money-bill"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Pending Orders</div>
                        <div class="card-value"><?php echo $statusCounts['pending']; ?></div>
                    </div>
                    <div class="card-icon bg-orange">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Shipped Orders</div>
                        <div class="card-value"><?php echo $statusCounts['shipped']; ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Delivered Orders</div>
                        <div class="card-value"><?php echo $statusCounts['delivered']; ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Cancelled Orders</div>
                        <div class="card-value"><?php echo $statusCounts['cancelled']; ?></div>
                    </div>
                    <div class="card-icon bg-red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Top Category</div>
                        <div class="card-value"><?php echo htmlspecialchars($topCategory); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Top Customer</div>
                        <div class="card-value"><?php echo htmlspecialchars($topCustomer['firstname'] . ' ' . $topCustomer['lastname']); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Most Used Payment Method</div>
                        <div class="card-value"><?php echo htmlspecialchars($mostUsedPaymentMethod); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Cart Items</div>
                        <div class="card-value"><?php echo number_format($totalCartItems); ?></div>
                    </div>
                    <div class="card-icon bg-red">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <h3 class="filter-title">Filter Analytics</h3>
            <div class="filter-grid">
                <div class="form-group">
                    <label class="form-label">Date Range</label>
                    <select class="form-control" id="dateRange">
                        <option value="all" <?php echo $dateRangeFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $dateRangeFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $dateRangeFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $dateRangeFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="quarter" <?php echo $dateRangeFilter === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                        <option value="year" <?php echo $dateRangeFilter === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="statusFilter">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select class="form-control" id="paymentFilter">
                        <option value="all" <?php echo $paymentFilter === 'all' ? 'selected' : ''; ?>>All Methods</option>
                        <option value="GCash" <?php echo $paymentFilter === 'GCash' ? 'selected' : ''; ?>>GCash</option>
                        <option value="Cash On Delivery" <?php echo $paymentFilter === 'Cash On Delivery' ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="PayMaya" <?php echo $paymentFilter === 'PayMaya' ? 'selected' : ''; ?>>PayMaya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-control" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach ($productsXml->categories->category as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $categoryFilter === (string)$category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select class="form-control" id="productFilter">
                        <option value="">All Products</option>
                        <?php foreach ($productsXml->products->product as $product): ?>
                            <option value="<?php echo $product->id; ?>" <?php echo $productFilter === (string)$product->id ? 'selected' : ''; ?>><?php echo htmlspecialchars($product->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
                <div class="form-group">
                    <button class="btn btn-reset" onclick="resetFilters()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="analytics-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Orders by Status</div>
                    <div class="chart-filter">
                        <button class="filter-btn" data-period="day" data-chart="status">Day</button>
                        <button class="filter-btn active" data-period="week" data-chart="status">Week</button>
                        <button class="filter-btn" data-period="month" data-chart="status">Month</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart" style="height: 300px; width: 100%;"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Sales by Category</div>
                    <div class="chart-filter">
                        <button class="filter-btn" data-period="week" data-chart="category">Week</button>
                        <button class="filter-btn active" data-period="month" data-chart="category">Month</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="categorySalesChart" style="height: 300px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data for Orders by Status chart
        const statusData = {
            day: {
                labels: ['Orders'],
                datasets: [
                    { label: 'Pending', data: [<?php echo $statusCounts['pending']; ?>], backgroundColor: 'rgba(245, 158, 11, 0.5)', borderColor: '#f59e0b', borderWidth: 1 },
                    { label: 'Shipped', data: [<?php echo $statusCounts['shipped']; ?>], backgroundColor: 'rgba(59, 130, 246, 0.5)', borderColor: '#3b82f6', borderWidth: 1 },
                    { label: 'Delivered', data: [<?php echo $statusCounts['delivered']; ?>], backgroundColor: 'rgba(16, 185, 129, 0.5)', borderColor: '#10b981', borderWidth: 1 },
                    { label: 'Cancelled', data: [<?php echo $statusCounts['cancelled']; ?>], backgroundColor: 'rgba(239, 68, 68, 0.5)', borderColor: '#ef4444', borderWidth: 1 }
                ]
            },
            week: {
                labels: ['Orders'],
                datasets: [
                    { label: 'Pending', data: [<?php echo $statusCounts['pending']; ?>], backgroundColor: 'rgba(245, 158, 11, 0.5)', borderColor: '#f59e0b', borderWidth: 1 },
                    { label: 'Shipped', data: [<?php echo $statusCounts['shipped']; ?>], backgroundColor: 'rgba(59, 130, 246, 0.5)', borderColor: '#3b82f6', borderWidth: 1 },
                    { label: 'Delivered', data: [<?php echo $statusCounts['delivered']; ?>], backgroundColor: 'rgba(16, 185, 129, 0.5)', borderColor: '#10b981', borderWidth: 1 },
                    { label: 'Cancelled', data: [<?php echo $statusCounts['cancelled']; ?>], backgroundColor: 'rgba(239, 68, 68, 0.5)', borderColor: '#ef4444', borderWidth: 1 }
                ]
            },
            month: {
                labels: ['Orders'],
                datasets: [
                    { label: 'Pending', data: [<?php echo $statusCounts['pending']; ?>], backgroundColor: 'rgba(245, 158, 11, 0.5)', borderColor: '#f59e0b', borderWidth: 1 },
                    { label: 'Shipped', data: [<?php echo $statusCounts['shipped']; ?>], backgroundColor: 'rgba(59, 130, 246, 0.5)', borderColor: '#3b82f6', borderWidth: 1 },
                    { label: 'Delivered', data: [<?php echo $statusCounts['delivered']; ?>], backgroundColor: 'rgba(16, 185, 129, 0.5)', borderColor: '#10b981', borderWidth: 1 },
                    { label: 'Cancelled', data: [<?php echo $statusCounts['cancelled']; ?>], backgroundColor: 'rgba(239, 68, 68, 0.5)', borderColor: '#ef4444', borderWidth: 1 }
                ]
            }
        };

        // Data for Sales by Category chart
        const categoryData = {
            week: {
                labels: <?php echo json_encode(array_keys($categorySales)); ?>,
                datasets: [{
                    label: 'Sales by Category',
                    data: <?php echo json_encode(array_values($categorySales)); ?>,
                    backgroundColor: ['#6366f1', '#f43f5e', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            month: {
                labels: <?php echo json_encode(array_keys($categorySales)); ?>,
                datasets: [{
                    label: 'Sales by Category',
                    data: <?php echo json_encode(array_values($categorySales)); ?>,
                    backgroundColor: ['#6366f1', '#f43f5e', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            }
        };

        function applyFilters() {
            const dateRange = document.getElementById('dateRange').value;
            const status = document.getElementById('statusFilter').value;
            const payment = document.getElementById('paymentFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const product = document.getElementById('productFilter').value;

            const queryParams = new URLSearchParams();
            if (dateRange !== 'all') queryParams.set('dateRange', dateRange);
            if (status !== 'all') queryParams.set('status', status);
            if (payment !== 'all') queryParams.set('payment', payment);
            if (category) queryParams.set('category', category);
            if (product) queryParams.set('product', product);

            window.location.href = `${window.location.pathname}?${queryParams.toString()}`;
        }

        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        function exportPDF() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'export_pdf';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Initialize charts
        document.addEventListener('DOMContentLoaded', () => {
            // Stacked Column Chart: Orders by Status
            const statusChart = new Chart(document.getElementById('statusChart'), {
                type: 'bar',
                data: statusData.week,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } },
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            title: { display: true, text: 'Status' }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            title: { display: true, text: 'Number of Orders' }
                        }
                    }
                }
            });

            // Donut Chart: Sales by Category
            const categorySalesChart = new Chart(document.getElementById('categorySalesChart'), {
                type: 'doughnut',
                data: categoryData.month,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } },
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ₱' + context.parsed.toLocaleString();
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Handle filter button clicks
            document.querySelectorAll('.filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons in this filter group
                    this.parentNode.querySelectorAll('.filter-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    // Add active class to clicked button
                    this.classList.add('active');

                    const period = this.getAttribute('data-period');
                    const chartType = this.getAttribute('data-chart');

                    if (chartType === 'status') {
                        statusChart.data = statusData[period];
                        statusChart.update();
                    } else if (chartType === 'category') {
                        categorySalesChart.data = categoryData[period];
                        categorySalesChart.update();
                    }
                });
            });
        });
    </script>
</body>
</html>