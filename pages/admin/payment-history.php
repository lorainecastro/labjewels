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

$ordersXmlFile = __DIR__ . '/../../xml/orders.xml';

function loadOrdersXML($file)
{
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
        $xml->asXML($file);
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading orders XML file.');
    }
    return $xml;
}

// Load orders from XML
$xml = loadOrdersXML($ordersXmlFile);
$orders = [];

if ($xml && isset($xml->order)) {
    foreach ($xml->order as $order) {
        $subtotal = 0;
        $items = [];
        if (isset($order->items->item)) {
            $xmlItems = is_array($order->items->item) || $order->items->item instanceof Traversable ? $order->items->item : [$order->items->item];
            foreach ($xmlItems as $item) {
                $price = isset($item->price) ? (float)$item->price : 0;
                $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
                $subtotal += $price * $quantity;
                $items[] = [
                    'product_name' => (string)$item->product_name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $price * $quantity
                ];
            }
        }
        $shipping_fee = isset($order->shipping_fee) ? (float)$order->shipping_fee : 0;
        $total_amount = $subtotal + $shipping_fee;

        $orders[] = [
            'order_id' => (string)$order->order_id,
            'user_id' => (int)$order->user_id,
            'timestamp' => (string)$order->timestamp,
            'payment_method' => isset($order->payment_method) ? (string)$order->payment_method : 'N/A',
            'payment_proof' => isset($order->payment_proof) ? (string)$order->payment_proof : null,
            'status' => isset($order->status) ? (string)$order->status : 'pending',
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping_fee,
            'total_amount' => $total_amount,
            'items' => $items
        ];

        // Debug: Verify data for order_6877b7c23d999
        /*
        if ((string)$order->order_id === 'order_6877b7c23d999') {
            echo '<pre>';
            echo 'Order ID: ' . (string)$order->order_id . "\n";
            echo 'Items: ' . print_r($items, true) . "\n";
            echo 'Subtotal: ' . $subtotal . "\n";
            echo 'Shipping Fee: ' . $shipping_fee . "\n";
            echo 'Total Amount: ' . $total_amount . "\n";
            echo '</pre>';
            //exit; // Uncomment to stop execution and view debug output
        }
        */
    }
}

// Sort orders by timestamp (newest first)
usort($orders, function ($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Calculate dashboard metrics
$totalOrders = count($orders);
$totalRevenue = array_sum(array_map(function ($order) {
    return $order['total_amount'];
}, $orders));
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Calculate payment method counts
$paymentMethods = [
    'GCash' => 0,
    'PayMaya' => 0,
    'PayPal' => 0,
    'Cash On Delivery' => 0,
    'N/A' => 0
];
foreach ($orders as $order) {
    $method = $order['payment_method'];
    if (array_key_exists($method, $paymentMethods)) {
        $paymentMethods[$method]++;
    } else {
        $paymentMethods['N/A']++;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'export_pdf') {
        require_once('../../TCPDF-main/TCPDF-main/tcpdf.php');

        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LAB Jewels');
        $pdf->SetTitle('Payment History Report');
        $pdf->SetHeaderData('', 0, 'Payment History Report', 'Generated on ' . date('Y-m-d H:i:s'));
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(10, 20, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $html = '<h1>Payment History Report</h1><table border="1" cellpadding="5">
            <thead>
                <tr style="background-color: #f0f0f0;">
                    <th>Order ID</th>
                    <th>User ID</th>
                    <th>Date</th>
                    <th>Payment Method</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($orders as $order) {
            $html .= "<tr>
                <td>" . htmlspecialchars(substr($order['order_id'], 6)) . "</td>
                <td>" . $order['user_id'] . "</td>
                <td>" . date('M j, Y g:i A', strtotime($order['timestamp'])) . "</td>
                <td>" . htmlspecialchars(str_replace('_', ' ', $order['payment_method'])) . "</td>
                <td>₱ " . number_format($order['total_amount'], 2) . "</td>
                <td>" . ucfirst(htmlspecialchars($order['status'])) . "</td>
            </tr>";
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('payment_history_report.pdf', 'D');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Payment History</title>
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
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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

        .card[data-tooltip]:hover:after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary-color);
            color: var(--whitefont-color);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            z-index: 10;
            box-shadow: var(--shadow-sm);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .card[data-tooltip]:hover:after {
            opacity: 1;
            visibility: visible;
        }

        .card[data-tooltip]:hover:before {
            content: '';
            position: absolute;
            bottom: calc(100% - 6px);
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--primary-color);
            z-index: 10;
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
            color: var(--whitefont-color);
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
            width: 200px;
        }

        .filter-dropdown select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-dropdown select:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .orders-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .orders-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow-x: auto;
            display: table;
        }

        .orders-table th {
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

        .orders-table td {
            padding: 15px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .orders-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .orders-table th:nth-child(1),
        .orders-table td:nth-child(1) {
            width: 12%;
        }

        .orders-table th:nth-child(2),
        .orders-table td:nth-child(2) {
            width: 10%;
        }

        .orders-table th:nth-child(3),
        .orders-table td:nth-child(3) {
            width: 15%;
        }

        .orders-table th:nth-child(4),
        .orders-table td:nth-child(4) {
            width: 15%;
        }

        .orders-table th:nth-child(5),
        .orders-table td:nth-child(5) {
            width: 15%;
        }

        .orders-table th:nth-child(6),
        .orders-table td:nth-child(6) {
            width: 15%;
        }

        .orders-table th:nth-child(7),
        .orders-table td:nth-child(7) {
            width: 15%;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1;
            border-radius: 6px;
            font-weight: 500;
        }

        .status-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
        }

        .status-shipped {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .status-delivered {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
        }

        .status-cancelled {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
        }

        .action-cell {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: flex-start;
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
            white-space: nowrap;
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

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            width: 650px;
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
            text-align: center;
        }

        .payment-proof-img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin: 20px auto;
            display: block;
        }

        .payment-proof-img[src=""] {
            display: none;
        }

        .no-proof-message {
            font-size: 16px;
            color: var(--grayfont-color);
            margin-top: 20px;
        }

        .status-message {
            position: fixed;
            bottom: 25px;
            right: 25px;
            padding: 16px 28px 16px 20px;
            border-radius: 12px;
            color: var(--whitefont-color);
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

        .error-message {
            background-color: var(--danger-color);
            border-left: 6px solid #dc2626;
        }

        .error-message::before {
            content: "";
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'/%3E%3C/svg%3E");
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

            .orders-table {
                display: block;
                overflow-x: auto;
            }

            .action-cell {
                flex-direction: column;
                gap: 8px;
            }

            .filter-section {
                flex-direction: column;
                padding: 12px;
            }

            .filter-dropdown {
                width: 100%;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>Payment History</h1>
            <div>
                <button class="btn btn-pdf" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export Payment History
                </button>
            </div>
        </header>

        <div class="dashboard-grid">
            <div class="card" data-tooltip="Number of orders paid via GCash">
                <div class="card-header">
                    <div>
                        <div class="card-title">GCash</div>
                        <div class="card-value"><?php echo number_format($paymentMethods['GCash']); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>
            <div class="card" data-tooltip="Number of orders paid via PayMaya">
                <div class="card-header">
                    <div>
                        <div class="card-title">PayMaya</div>
                        <div class="card-value"><?php echo number_format($paymentMethods['PayMaya']); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                </div>
            </div>
            <div class="card" data-tooltip="Number of orders paid via PayPal">
                <div class="card-header">
                    <div>
                        <div class="card-title">PayPal</div>
                        <div class="card-value"><?php echo number_format($paymentMethods['PayPal']); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fab fa-paypal"></i>
                    </div>
                </div>
            </div>
            <div class="card" data-tooltip="Number of orders paid via Cash On Delivery">
                <div class="card-header">
                    <div>
                        <div class="card-title">Cash On Delivery</div>
                        <div class="card-value"><?php echo number_format($paymentMethods['Cash On Delivery']); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchOrders" placeholder="Search by Order ID or User ID..." onkeyup="filterOrders()">
            </div>
            <div class="filter-dropdown">
                <select id="paymentMethodFilter" onchange="filterOrders()">
                    <option value="">All Payment Methods</option>
                    <option value="PayMaya">PayMaya</option>
                    <option value="GCash">GCash</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Cash On Delivery">Cash On Delivery</option>
                </select>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="status-message error-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="orders-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User ID</th>
                        <th>Date</th>
                        <th>Payment Method</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php
                    if (!empty($orders)) {
                        foreach ($orders as $index => $order) {
                            echo "<tr class='order-row' data-order-id='" . htmlspecialchars($order['order_id']) . "' data-user-id='" . $order['user_id'] . "' data-payment-method='" . htmlspecialchars($order['payment_method']) . "'>";
                            echo "<td>#" . htmlspecialchars(substr($order['order_id'], 6)) . "</td>";
                            echo "<td>" . $order['user_id'] . "</td>";
                            echo "<td>" . date('M j, Y g:i A', strtotime($order['timestamp'])) . "</td>";
                            echo "<td>" . htmlspecialchars(str_replace('_', ' ', $order['payment_method'])) . "</td>";
                            echo "<td>₱ " . number_format($order['total_amount'], 2) . "</td>";
                            echo "<td><span class='status-badge status-" . htmlspecialchars($order['status']) . "'>" . ucfirst(htmlspecialchars($order['status'])) . "</span></td>";
                            echo "<td class='action-cell'>";
                            echo "<button class='btn btn-primary' onclick=\"openProofModal('" . htmlspecialchars($order['payment_proof'] ?? '') . "')\" " . ($order['payment_proof'] ? '' : 'disabled') . " title='View payment proof'><i class='fas fa-eye'></i> View Proof</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>No payment history found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- Payment Proof Modal -->
        <div class="modal-backdrop" id="proofModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Payment Proof</h3>
                    <button class="close-modal" onclick="closeProofModal()">×</button>
                </div>
                <div class="modal-body">
                    <img class="payment-proof-img" id="paymentProofImg" src="" alt="Payment Proof">
                    <div class="no-proof-message" id="noProofMessage">No payment proof available.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const proofModal = document.getElementById('proofModal');
        const itemsPerPage = 6;
        let currentPage = 1;

        // Orders data from PHP
        const orders = <?php echo json_encode($orders); ?>;

        function openProofModal(paymentProof) {
            const proofImg = document.getElementById('paymentProofImg');
            const noProofMessage = document.getElementById('noProofMessage');
            proofImg.src = paymentProof || '';
            noProofMessage.style.display = paymentProof ? 'none' : 'block';
            proofModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeProofModal() {
            proofModal.classList.remove('show');
            document.body.style.overflow = 'auto';
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

        function filterOrders() {
            const searchTerm = document.getElementById('searchOrders').value.toLowerCase();
            const paymentMethod = document.getElementById('paymentMethodFilter').value;
            const rows = document.querySelectorAll('.order-row');
            let visibleRows = [];

            rows.forEach(row => {
                const orderId = row.dataset.orderId.toLowerCase();
                const userId = row.dataset.userId;
                const rowPaymentMethod = row.dataset.paymentMethod;
                const matchesSearch = !searchTerm || orderId.includes(searchTerm) || userId.includes(searchTerm);
                const matchesPayment = !paymentMethod || rowPaymentMethod === paymentMethod;

                if (matchesSearch && matchesPayment) {
                    row.style.display = '';
                    visibleRows.push(row);
                } else {
                    row.style.display = 'none';
                }
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
            const allRows = document.querySelectorAll('.order-row');

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
            if (e.target === proofModal) {
                closeProofModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && proofModal.classList.contains('show')) {
                closeProofModal();
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
            filterOrders(); // Initialize pagination and filtering
            addTooltips(); // Initialize tooltips
        });

        // Enhanced search with debouncing
        let searchTimeout;
        document.getElementById('searchOrders').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterOrders, 300);
        });

        // Add tooltips for action buttons and cards
        function addTooltips() {
            const viewButtons = document.querySelectorAll('.btn-primary');
            const pdfButton = document.querySelector('.btn-pdf');
            const cards = document.querySelectorAll('.card');

            viewButtons.forEach(btn => {
                if (btn.innerHTML.includes('View Proof')) {
                    btn.title = 'View payment proof';
                }
            });

            if (pdfButton) {
                pdfButton.title = 'Export payment history to PDF';
            }

            cards.forEach(card => {
                const tooltipText = card.getAttribute('data-tooltip');
                if (tooltipText) {
                    card.title = tooltipText; // Fallback for accessibility
                }
            });
        }
    </script>
</body>

</html>