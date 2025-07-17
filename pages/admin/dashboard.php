<?php
require '../../connection/config.php';
session_start();

// Check if user is logged in and is admin (user_id: 1)
$currentUser = validateSession();

if (!$currentUser || $currentUser['user_id'] != 1) {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    header("Location: ../pages/login.php");
    exit;
}

// Handle AJAX data request
if (isset($_GET['data']) && $_GET['data'] === 'fetch') {
    $ordersXmlFile = __DIR__ . '/../../xml/orders.xml';
    $lastModified = filemtime($ordersXmlFile);

    function loadOrdersXML($file) {
        if (!file_exists($file)) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
            $xml->asXML($file);
        }
        $xml = simplexml_load_file($file);
        if ($xml === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Error loading orders XML file.']);
            exit;
        }
        return $xml;
    }

    // Load orders from XML
    $xml = loadOrdersXML($ordersXmlFile);
    $orders = [];
    $pdo = getDBConnection();

    if ($xml && isset($xml->order)) {
        foreach ($xml->order as $order) {
            $subtotal = 0;
            if (isset($order->items->item)) {
                $items = is_array($order->items->item) ? $order->items->item : [$order->items->item];
                $subtotal = array_sum(array_map(function ($item) {
                    $price = isset($item->price) ? (float)$item->price : 0;
                    $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
                    return $price * $quantity;
                }, $items));
            }
            $shipping_fee = isset($order->shipping_fee) ? (float)$order->shipping_fee : 0;
            $total_amount = $subtotal + $shipping_fee;

            // Fetch customer name from database
            $stmt = $pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) AS full_name FROM users WHERE user_id = ? AND isDeleted = 0");
            $stmt->execute([(int)$order->user_id]);
            $user = $stmt->fetch();

            $orders[] = [
                'id' => (string)$order->order_id,
                'user_id' => (int)$order->user_id,
                'timestamp' => (string)$order->timestamp,
                'payment' => isset($order->payment_method) ? (string)$order->payment_method : 'N/A',
                'status' => isset($order->status) ? (string)$order->status : 'pending',
                'subtotal' => $subtotal,
                'shipping' => $shipping_fee,
                'total' => $total_amount,
                'customer' => $user ? $user['full_name'] : 'Unknown',
                'items' => array_map(function ($item) {
                    return [
                        'name' => (string)$item->product_name,
                        'price' => (float)$item->price,
                        'quantity' => (int)$item->quantity,
                        'total' => (float)$item->price * (int)$item->quantity
                    ];
                }, is_array($order->items->item) ? $order->items->item : [$order->items->item])
            ];
        }
    }

    // Sort orders by timestamp (newest first)
    usort($orders, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Calculate dashboard metrics
    $totalOrders = count($orders);
    $totalSales = array_sum(array_map(function ($order) {
        return in_array(strtolower($order['status']), ['shipped', 'delivered']) ? $order['total'] : 0;
    }, $orders));
    $totalCustomers = count(array_unique(array_map(function ($order) {
        return $order['user_id'];
    }, $orders)));
    $productsSold = array_sum(array_map(function ($order) {
        return array_sum(array_map(function ($item) {
            return $item['quantity'];
        }, $order['items']));
    }, $orders));

    // Prepare data for charts
    $salesData = [
        'day' => [
            'labels' => ['9AM', '10AM', '11AM', '12PM', '1PM', '2PM', '3PM', '4PM', '5PM', '6PM', '7PM', '8PM'],
            'values' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        ],
        'week' => [
            'labels' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'values' => [0, 0, 0, 0, 0, 0, 0]
        ],
        'month' => [
            'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            'values' => [0, 0, 0, 0]
        ]
    ];

    // Distribute sales data
    foreach ($orders as $order) {
        $timestamp = strtotime($order['timestamp']);
        $hour = (int)date('H', $timestamp);
        $dayOfWeek = date('l', $timestamp);
        $weekOfMonth = ceil(date('d', $timestamp) / 7);

        // Map hour to index (9AM = 0, 10AM = 1, etc.)
        if ($hour >= 9 && $hour <= 20) {
            $hourIndex = $hour - 9;
            $salesData['day']['values'][$hourIndex] += in_array(strtolower($order['status']), ['shipped', 'delivered']) ? $order['total'] : 0;
        }

        // Map day to index
        $dayIndex = array_search($dayOfWeek, $salesData['week']['labels']);
        if ($dayIndex !== false) {
            $salesData['week']['values'][$dayIndex] += in_array(strtolower($order['status']), ['shipped', 'delivered']) ? $order['total'] : 0;
        }

        // Map week
        if ($weekOfMonth >= 1 && $weekOfMonth <= 4) {
            $salesData['month']['values'][$weekOfMonth - 1] += in_array(strtolower($order['status']), ['shipped', 'delivered']) ? $order['total'] : 0;
        }
    }

    // Prepare products data
    $productQuantities = [];
    foreach ($orders as $order) {
        foreach ($order['items'] as $item) {
            $productName = $item['name'];
            if (!isset($productQuantities[$productName])) {
                $productQuantities[$productName] = 0;
            }
            $productQuantities[$productName] += $item['quantity'];
        }
    }

    $productsData = [
        'week' => [
            'labels' => array_keys($productQuantities),
            'values' => array_values($productQuantities)
        ],
        'month' => [
            'labels' => array_keys($productQuantities),
            'values' => array_values($productQuantities)
        ]
    ];

    // Prepare response
    $response = [
        'lastModified' => $lastModified,
        'totalSales' => number_format($totalSales, 2),
        'totalOrders' => number_format($totalOrders),
        'totalCustomers' => number_format($totalCustomers),
        'productsSold' => number_format($productsSold),
        'salesData' => $salesData,
        'productsData' => $productsData,
        'orders' => array_map(function ($order) {
            return [
                'id' => substr($order['id'], 6),
                'customer' => $order['customer'],
                'product' => $order['items'][0]['name'],
                'quantity' => $order['items'][0]['quantity'],
                'amount' => number_format($order['total'], 2),
                'date' => date('M j, Y g:i A', strtotime($order['timestamp'])), // Include time in date
                'status' => ucfirst($order['status']),
                'payment' => $order['payment'],
                'items' => $order['items']
            ];
        }, $orders)
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Dashboard</title>
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
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
            --tooltip-bg: #2d3748;
            --tooltip-color: #ffffff;
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
            position: relative;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card[data-tooltip]:hover::after,
        .card[data-tooltip]:focus::after {
            content: attr(data-tooltip);
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--tooltip-bg);
            color: var(--tooltip-color);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
            opacity: 1;
            transition: opacity 0.3s ease, transform 0.3s ease;
            pointer-events: none;
        }

        .card[data-tooltip]:hover::before,
        .card[data-tooltip]:focus::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 6px;
            border-style: solid;
            border-color: var(--tooltip-bg) transparent transparent transparent;
            z-index: 10;
            opacity: 1;
            transition: opacity 0.3s ease;
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

        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
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

        .recent-orders {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-all:hover {
            color: var(--primary-hover);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            font-weight: 600;
            color: var(--grayfont-color);
            font-size: 14px;
        }

        tbody tr {
            transition: var(--transition);
        }

        tbody tr:hover {
            background-color: var(--inputfield-color);
        }

        .status {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .delivered {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .shipped {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .cancelled {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        @media (max-width: 1024px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            th, td {
                padding: 10px;
            }
            
            .card-value {
                font-size: 20px;
            }

            .card[data-tooltip]:hover::after,
            .card[data-tooltip]:focus::after {
                top: auto;
                bottom: -40px;
                transform: translateX(-50%);
            }

            .card[data-tooltip]:hover::before,
            .card[data-tooltip]:focus::before {
                top: auto;
                bottom: -8px;
                border-color: transparent transparent var(--tooltip-bg) transparent;
            }
        }

        @media (max-width: 576px) {
            .table-responsive {
                overflow-x: auto;
            }
            
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
    <h1>Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="dashboard-grid">
        <div class="card" onclick="window.location.href='orders.php'" data-tooltip="Total revenue from shipped and delivered orders">
            <div class="card-header">
                <div>
                    <div class="card-title">Total Sales</div>
                    <div class="card-value" id="total-sales">₱0.00</div>
                </div>
                <div class="card-icon bg-purple">₱</div>
            </div>
        </div>
        
        <div class="card" onclick="window.location.href='orders.php'" data-tooltip="Total number of orders placed">
            <div class="card-header">
                <div>
                    <div class="card-title">Total Orders</div>
                    <div class="card-value" id="total-orders">0</div>
                </div>
                <div class="card-icon bg-pink">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="card" onclick="window.location.href='user-management.php'" data-tooltip="Number of unique customers who placed orders">
            <div class="card-header">
                <div>
                    <div class="card-title">Total Customers</div>
                    <div class="card-value" id="total-customers">0</div>
                </div>
                <div class="card-icon bg-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                        <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="card" data-tooltip="Total quantity of products sold across all orders">
            <div class="card-header">
                <div>
                    <div class="card-title">Products Sold</div>
                    <div class="card-value" id="products-sold">0</div>
                </div>
                <div class="card-icon bg-green">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->       
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">Sales Overview</div>
                <div class="chart-filter">
                    <button class="filter-btn" data-period="day" data-chart="sales" title="Show sales data for the current day">Day</button>
                    <button class="filter-btn active" data-period="week" data-chart="sales" title="Show sales data for the current week">Week</button>
                    <button class="filter-btn" data-period="month" data-chart="sales" title="Show sales data for the current month">Month</button>
                </div>
            </div>
            <div>
                <canvas id="sales-chart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">Top Products</div>
                <div class="chart-filter">
                    <button class="filter-btn" data-period="week" data-chart="products" title="Show top products sold this week">Week</button>
                    <button class="filter-btn active" data-period="month" data-chart="products" title="Show top products sold this month">Month</button>
                </div>
            </div>
            <div>
                <canvas id="products-chart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders Table -->
    <div class="recent-orders">
        <div class="table-header">
            <div class="table-title">Recent Orders</div>
            <a href="orders.php" class="view-all" title="View all orders in detail">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <!-- Populated via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Chart.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Initialize Charts
        const salesChartCtx = document.getElementById('sales-chart').getContext('2d');
        const salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Sales (₱)',
                    data: [],
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: '#6366f1',
                    borderWidth: 2,
                    tension: 0.4,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        const productsChartCtx = document.getElementById('products-chart').getContext('2d');
        const productsChart = new Chart(productsChartCtx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: ['#6366f1', '#f43f5e', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' pcs';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Store last modified timestamp
        let lastModified = null;

        // Function to update dashboard
        function updateDashboard(data) {
            // Update cards
            document.getElementById('total-sales').textContent = '₱' + data.totalSales;
            document.getElementById('total-orders').textContent = data.totalOrders;
            document.getElementById('total-customers').textContent = data.totalCustomers;
            document.getElementById('products-sold').textContent = data.productsSold;

            // Update orders table
            const tbody = document.getElementById('orders-table-body');
            tbody.innerHTML = '';
            data.orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${order.id}</td>
                    <td>${order.customer}</td>
                    <td>${order.product}</td>
                    <td>${order.quantity}</td>
                    <td>₱${order.amount}</td>
                    <td>${order.date}</td>
                    <td><span class="status ${order.status.toLowerCase()}">${order.status}</span></td>
                `;
                tbody.appendChild(row);
            });

            // Update charts
            salesChart.data.labels = data.salesData.week.labels;
            salesChart.data.datasets[0].data = data.salesData.week.values;
            salesChart.update();

            productsChart.data.labels = data.productsData.month.labels;
            productsChart.data.datasets[0].data = data.productsData.month.values;
            productsChart.update();
        }

        // Function to fetch dashboard data
        function fetchDashboardData() {
            fetch('dashboard.php?data=fetch')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    if (lastModified !== data.lastModified) {
                        lastModified = data.lastModified;
                        updateDashboard(data);
                    }
                })
                .catch(error => console.error('Fetch error:', error));
        }

        // Initial fetch
        fetchDashboardData();

        // Poll every 5 seconds
        setInterval(fetchDashboardData, 5000);

        // Handle filter buttons clicks
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons in this filter group
                this.parentNode.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                // Add active class to clicked button
                this.classList.add('active');
                
                // Fetch data and update chart
                fetch('dashboard.php?data=fetch')
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            return;
                        }
                        const period = this.getAttribute('data-period');
                        const chartType = this.getAttribute('data-chart');
                        
                        if (chartType === 'sales') {
                            salesChart.data.labels = data.salesData[period].labels;
                            salesChart.data.datasets[0].data = data.salesData[period].values;
                            salesChart.update();
                        } else if (chartType === 'products') {
                            productsChart.data.labels = data.productsData[period].labels;
                            productsChart.data.datasets[0].data = data.productsData[period].values;
                            productsChart.update();
                        }
                    })
                    .catch(error => console.error('Fetch error:', error));
            });
        });
    </script>
</body>
</html>