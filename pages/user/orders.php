<?php
require '../../connection/config.php';
session_start();

// Check if user is logged in
$currentUser = validateSession();

if (!$currentUser) {
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

function updateOrderStatus($ordersFile, $orderId, $status, $paymentMethod, $paymentProof = null)
{
    if (!file_exists($ordersFile)) {
        return false;
    }

    $xml = simplexml_load_file($ordersFile);
    if ($xml === false) {
        return false;
    }

    foreach ($xml->order as $order) {
        if ((string)$order->order_id === $orderId) {
            if (!isset($order->status)) {
                $order->addChild('status', $status);
            } else {
                $order->status = $status;
            }

            if (!isset($order->payment_method)) {
                $order->addChild('payment_method', htmlspecialchars($paymentMethod));
            } else {
                $order->payment_method = htmlspecialchars($paymentMethod);
            }

            if ($paymentProof && !isset($order->payment_proof)) {
                $order->addChild('payment_proof', htmlspecialchars($paymentProof));
            } elseif ($paymentProof) {
                $order->payment_proof = htmlspecialchars($paymentProof);
            }

            $xml->asXML($ordersFile);
            return true;
        }
    }

    return false;
}

$xml = loadOrdersXML($ordersXmlFile);
$userOrders = [];

if ($xml && isset($xml->order)) {
    foreach ($xml->order as $order) {
        if ((int)$order->user_id == $currentUser['user_id']) {
            // Calculate subtotal for shipping fee
            $subtotal = 0;
            if (isset($order->items->item)) {
                foreach ($order->items->item as $item) {
                    $subtotal += (float)$item->price * (int)$item->quantity;
                }
            }
            // Calculate shipping fee if not present in XML
            $shippingFee = isset($order->shipping_fee) ? (float)$order->shipping_fee : ($subtotal > 3000 ? 0 : $subtotal * 0.02);

            $orderData = [
                'order_id' => (string)$order->order_id,
                'user_id' => (int)$order->user_id,
                'timestamp' => (string)$order->timestamp,
                'status' => isset($order->status) ? (string)$order->status : 'pending',
                'payment_method' => isset($order->payment_method) ? (string)$order->payment_method : 'N/A',
                'payment_proof' => isset($order->payment_proof) ? (string)$order->payment_proof : null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total_amount' => $subtotal + $shippingFee,
                'expected_delivery_date' => isset($order->expected_delivery_date) ? (string)$order->expected_delivery_date : null,
                'delivery_completed_on' => isset($order->delivery_completed_on) ? (string)$order->delivery_completed_on : null,
                'items' => []
            ];

            if (isset($order->items->item)) {
                foreach ($order->items->item as $item) {
                    $orderData['items'][] = [
                        'product_id' => (int)$item->product_id,
                        'product_name' => (string)$item->product_name,
                        'image' => (string)$item->image,
                        'price' => (float)$item->price,
                        'color' => (string)$item->color,
                        'size' => (string)$item->size,
                        'quantity' => (int)$item->quantity
                    ];
                }
            }

            $userOrders[] = $orderData;
        }
    }
}

// Sort orders by timestamp (newest first)
usort($userOrders, function ($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Calculate order counts for each status
$statusCounts = [
    'all' => count($userOrders),
    'pending' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

foreach ($userOrders as $order) {
    if (isset($statusCounts[$order['status']])) {
        $statusCounts[$order['status']]++;
    }
}

// Convert status counts to JSON for JavaScript
$statusCountsJson = json_encode($statusCounts, JSON_HEX_APOS | JSON_HEX_QUOT);

// Filter and Pagination settings
$ordersPerPage = 2; // Number of orders per page
$currentFilter = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all';
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Filter orders by status
$filteredOrders = $currentFilter === 'all' ? $userOrders : array_filter($userOrders, function ($order) use ($currentFilter) {
    return $order['status'] === $currentFilter;
});

// Calculate pagination
$totalFilteredOrders = count($filteredOrders);
$totalPages = ceil($totalFilteredOrders / $ordersPerPage);
$currentPage = min($currentPage, $totalPages ?: 1);
$offset = ($currentPage - 1) * $ordersPerPage;
$paginatedOrders = array_slice($filteredOrders, $offset, $ordersPerPage);

// Convert orders to JSON for JavaScript
$ordersJson = json_encode($userOrders, JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - My Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8b5cf6;
            --primary-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --primary-hover: #4f46e5;
            --secondary-color: #f43f5e;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #1a1a1a;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #f1f5f9;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --blackfont-color: #1a1a1a;
            --whitefont-color: #f9fafb;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #4f46e5;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            --success-hover: #059669;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--background-color);
            color: var(--blackfont-color);
            font-family: 'Poppins', sans-serif;
            line-height: 1.7;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .orders-section h1 {
            font-size: 36px;
            font-weight: 800;
            color: var(--nav-color);
            text-align: center;
            margin-bottom: 40px;
        }

        .order-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
        }

        .order-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .order-header {
            background: var(--nav-color);
            color: var(--whitefont-color);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-info h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .order-info p {
            font-size: 14px;
            opacity: 0.9;
        }

        .order-status {
            background-color: var(--success-color);
            color: var(--whitefont-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-status.pending {
            background-color: var(--warning-color);
        }

        .order-status.shipped {
            background-color: #3b82f6;
        }

        .order-status.failed,
        .order-status.cancelled {
            background-color: var(--danger-color);
        }

        .order-items {
            padding: 0;
        }

        .item-row {
            display: flex;
            align-items: center;
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-row:hover {
            background-color: #f9fafb;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 20px;
            box-shadow: var(--shadow-sm);
        }

        .item-details {
            flex: 1;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 20px;
            align-items: center;
        }

        .item-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--primary-color);
        }

        .item-specs {
            font-size: 14px;
            color: var(--grayfont-color);
            margin-top: 5px;
        }

        .item-price {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 16px;
        }

        .item-quantity {
            background-color: var(--inputfield-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
        }

        .order-total {
            background-color: var(--background-color);
            padding: 20px 30px;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .total-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .total-amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .subtotal-amount,
        .shipping-fee {
            font-size: 16px;
            color: var(--grayfont-color);
        }

        .payment-method {
            font-size: 14px;
            color: var(--grayfont-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 0;
            color: var(--grayfont-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--border-color);
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--blackfont-color);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .button {
            background-color: var(--primary-color);
            color: var(--whitefont-color);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .button:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .button.success {
            background: var(--primary-gradient);
            background-color: var(--primary-color);
        }

        .button.success:hover {
            background: var(--primary-color);
        }

        .navigation {
            text-align: center;
            margin-bottom: 30px;
        }

        .nav-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--primary-hover);
            transform: translateX(-5px);
        }

        .filter-section {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-btn {
            padding: 10px 20px;
            background-color: var(--card-bg);
            border: 1px solid var(--nav-color);
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            color: var(--nav-color);
            cursor: pointer;
            transition: var(--transition);
            position: relative; /* Allow positioning of badge */
        }

        .filter-btn:hover,
        .filter-btn.active {
            background-color: var(--nav-color);
            color: var(--card-bg);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger-color);
            color: var(--whitefont-color);
            font-size: 12px;
            font-weight: 600;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .notification-badge.hidden {
            display: none;
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
            color: var(--nav-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .page-btn.active {
            background: var(--nav-color);
            color: var(--whitefont-color);
            border-color: var(--nav-color);
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
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            color: var(--blackfont-color);
        }

        .modal-content h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--blackfont-color);
            text-align: center;
        }

        .close-modal {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: var(--grayfont-color);
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        .order-info-section {
            background-color: var(--division-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .order-info-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .order-info-section p {
            font-size: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .order-info-section p strong {
            color: var(--blackfont-color);
            font-weight: 600;
        }

        .order-info-section .status {
            font-weight: 600;
        }

        .order-info-section .status.pending {
            color: var(--warning-color);
        }

        .order-info-section .status.delivered {
            color: var(--success-color);
        }

        .order-info-section .status.shipped {
            color: var(--primary-color);
        }

        .order-info-section .status.failed,
        .order-info-section .status.cancelled {
            color: var(--danger-color);
        }

        .order-summary {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .order-summary h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .summary-subtotal,
        .summary-shipping {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--blackfont-color);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            border-top: 2px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
        }

        .payment-proof-section {
            margin-top: 20px;
            background-color: var(--division-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
        }

        .payment-proof-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .payment-proof-section p {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .payment-proof-img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            display: block;
            margin: 15px auto;
        }

        .payment-proof-img[src=""] {
            display: none;
        }

        .no-proof-message {
            font-size: 14px;
            color: var(--grayfont-color);
            text-align: center;
            margin-top: 15px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .message.success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .message.error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #f87171;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .item-details {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: left;
            }

            .item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .item-image {
                margin-right: 0;
                align-self: center;
            }

            .orders-section h1 {
                font-size: 28px;
            }

            .order-total {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .modal-content {
                padding: 20px;
                margin: 15px;
                max-width: 95%;
            }

            .payment-proof-img {
                max-height: 200px;
            }

            .filter-section {
                gap: 10px;
            }

            .filter-btn {
                padding: 8px 16px;
                font-size: 13px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .item-row {
                padding: 15px 20px;
            }

            .order-header {
                padding: 15px 20px;
            }

            .order-total {
                padding: 15px 20px;
            }

            .modal-content {
                padding: 15px;
                margin: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="orders-section container">
        <div class="navigation">
            <a href="cart.php" class="nav-link">
                <i class="fas fa-arrow-left"></i>
                Back to Cart
            </a>
        </div>

        <h1>My Orders</h1>

        <div class="filter-section" id="filterSection">
            <button class="filter-btn <?php echo $currentFilter === 'all' ? 'active' : ''; ?>" data-status="all">
                All Orders
                <span class="notification-badge <?php echo $statusCounts['all'] == 0 ? 'hidden' : ''; ?>" data-status="all"><?php echo $statusCounts['all']; ?></span>
            </button>
            <button class="filter-btn <?php echo $currentFilter === 'pending' ? 'active' : ''; ?>" data-status="pending">
                Pending
                <span class="notification-badge <?php echo $statusCounts['pending'] == 0 ? 'hidden' : ''; ?>" data-status="pending"><?php echo $statusCounts['pending']; ?></span>
            </button>
            <button class="filter-btn <?php echo $currentFilter === 'shipped' ? 'active' : ''; ?>" data-status="shipped">
                Shipped
                <span class="notification-badge <?php echo $statusCounts['shipped'] == 0 ? 'hidden' : ''; ?>" data-status="shipped"><?php echo $statusCounts['shipped']; ?></span>
            </button>
            <button class="filter-btn <?php echo $currentFilter === 'delivered' ? 'active' : ''; ?>" data-status="delivered">
                Delivered
                <span class="notification-badge <?php echo $statusCounts['delivered'] == 0 ? 'hidden' : ''; ?>" data-status="delivered"><?php echo $statusCounts['delivered']; ?></span>
            </button>
            <button class="filter-btn <?php echo $currentFilter === 'cancelled' ? 'active' : ''; ?>" data-status="cancelled">
                Cancelled
                <span class="notification-badge <?php echo $statusCounts['cancelled'] == 0 ? 'hidden' : ''; ?>" data-status="cancelled"><?php echo $statusCounts['cancelled']; ?></span>
            </button>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div id="orderCards">
            <?php if (empty($paginatedOrders) && $currentFilter === 'all'): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h2>No Orders Yet</h2>
                    <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                    <a href="cart.php" class="button">Go to Cart</a>
                </div>
            <?php else: ?>
                <?php foreach ($paginatedOrders as $order): ?>
                    <div class="order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Order #<?php echo htmlspecialchars(substr($order['order_id'], 6)); ?></h3>
                                <p>Placed on <?php echo date('F j, Y g:i A', strtotime($order['timestamp'])); ?></p>
                                <?php if ($order['status'] === 'shipped' && $order['expected_delivery_date']): ?>
                                    <p class="expected-delivery">Expected Delivery Date: <?php echo date('F j, Y', strtotime($order['expected_delivery_date'])); ?></p>
                                <?php elseif ($order['status'] === 'delivered' && $order['delivery_completed_on']): ?>
                                    <p class="delivery-completed">Delivery Completed On: <?php echo date('F j, Y', strtotime($order['delivery_completed_on'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="order-status <?php echo htmlspecialchars($order['status']); ?>">
                                <i class="fas fa-<?php echo $order['status'] === 'delivered' ? 'check-circle' : ($order['status'] === 'pending' ? 'clock' : ($order['status'] === 'shipped' ? 'truck' : 'times-circle')); ?>"></i>
                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                            </div>
                        </div>

                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="item-row">
                                    <img class="item-image" src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <div class="item-details">
                                        <div>
                                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div class="item-specs">
                                                Color: <?php echo htmlspecialchars($item['color']); ?> |
                                                Size: <?php echo htmlspecialchars($item['size']); ?>
                                            </div>
                                        </div>
                                        <div class="item-price">PHP <?php echo number_format($item['price'], 2); ?></div>
                                        <div class="item-quantity">Qty: <?php echo htmlspecialchars($item['quantity']); ?></div>
                                        <div class="item-price">PHP <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-total">
                            <div class="total-details">
                                <div class="subtotal-amount">Subtotal: PHP <?php echo number_format($order['subtotal'], 2); ?></div>
                                <div class="shipping-fee">Shipping Fee: PHP <?php echo number_format($order['shipping_fee'], 2); ?></div>
                                <div class="total-amount">Total: PHP <?php echo number_format($order['total_amount'], 2); ?></div>
                                <?php if ($order['payment_method'] !== 'N/A'): ?>
                                    <div class="payment-method">
                                        <i class="fas fa-credit-card"></i>
                                        Payment Method: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button class="button success" data-order='<?php echo htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>' onclick="openOrderDetailsModal(this)">
                                <i class="fas fa-eye"></i>
                                View Order
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination" id="pagination">
                <a href="?status=<?php echo urlencode($currentFilter); ?>&page=<?php echo max(1, $currentPage - 1); ?>"
                    class="page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?status=<?php echo urlencode($currentFilter); ?>&page=<?php echo $i; ?>"
                        class="page-btn <?php echo $currentPage === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <a href="?status=<?php echo urlencode($currentFilter); ?>&page=<?php echo min($totalPages, $currentPage + 1); ?>"
                    class="page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h2><i class="fas fa-receipt"></i> Order Details</h2>

            <div class="order-info-section">
                <h3>Order Information</h3>
                <p><strong>Order ID:</strong> <span id="orderIdDisplay"></span></p>
                <p><strong>Placed On:</strong> <span id="timestampDisplay"></span></p>
                <p><strong>Status:</strong> <span id="statusDisplay" class="status"></span></p>
                <p id="deliveryDateDisplay" style="display: none;"><strong>Expected Delivery Date:</strong> <span id="deliveryDate"></span></p>
                <p id="deliveryCompletedDisplay" style="display: none;"><strong>Delivery Completed On:</strong> <span id="deliveryCompleted"></span></p>
            </div>

            <div class="order-summary">
                <h3>Order Summary</h3>
                <div id="summaryItems"></div>
                <div class="summary-subtotal">
                    <span>Subtotal:</span>
                    <span id="subtotalAmount">PHP 0.00</span>
                </div>
                <div class="summary-shipping">
                    <span>Shipping Fee:</span>
                    <span id="shippingFee">PHP 0.00</span>
                </div>
                <div class="summary-total">
                    <span>Total Amount:</span>
                    <span id="totalAmount">PHP 0.00</span>
                </div>
            </div>

            <div class="payment-proof-section">
                <h3>Payment Details</h3>
                <p><strong>Payment Method:</strong> <span id="paymentMethodDisplay">N/A</span></p>
                <img class="payment-proof-img" id="paymentProofImg" src="" alt="Payment Proof">
                <div class="no-proof-message" id="noProofMessage">No payment proof available.</div>
            </div>
        </div>
    </div>

    <script>
        // Store orders data and status counts
        const orders = <?php echo $ordersJson; ?>;
        const ordersPerPage = <?php echo $ordersPerPage; ?>;
        const statusCounts = <?php echo $statusCountsJson; ?>;
        let currentFilter = '<?php echo $currentFilter; ?>';
        let currentPage = <?php echo $currentPage; ?>;

        // Empty state messages
        const emptyStateMessages = {
            all: {
                title: 'No Orders Yet',
                message: 'You haven\'t placed any orders yet. Start shopping to see your orders here!'
            },
            pending: {
                title: 'No Pending Orders',
                message: 'You have no pending orders at the moment. Check back later or start shopping!'
            },
            shipped: {
                title: 'No Shipped Orders',
                message: 'You have no shipped orders currently. Your orders will appear here once they are shipped.'
            },
            delivered: {
                title: 'No Delivered Orders',
                message: 'You have no delivered orders yet. Your orders will appear here once they are delivered.'
            },
            cancelled: {
                title: 'No Cancelled Orders',
                message: 'You have no cancelled orders. Your orders will appear here if any are cancelled.'
            }
        };

        // Modal functionality
        const orderDetailsModal = document.getElementById('orderDetailsModal');
        const closeModal = document.querySelector('.close-modal');

        function openOrderDetailsModal(button) {
            try {
                const order = JSON.parse(button.getAttribute('data-order'));

                document.getElementById('orderIdDisplay').textContent = '#' + order.order_id.substring(6);
                document.getElementById('timestampDisplay').textContent = new Date(order.timestamp).toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
                document.getElementById('statusDisplay').textContent = order.status.charAt(0).toUpperCase() + order.status.slice(1);
                document.getElementById('statusDisplay').className = 'status ' + order.status;
                document.getElementById('subtotalAmount').textContent = 'PHP ' + Number(order.subtotal).toFixed(2);
                document.getElementById('shippingFee').textContent = 'PHP ' + Number(order.shipping_fee).toFixed(2);
                document.getElementById('totalAmount').textContent = 'PHP ' + Number(order.total_amount).toFixed(2);
                document.getElementById('paymentMethodDisplay').textContent = order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                document.getElementById('paymentProofImg').src = order.payment_proof || '';
                document.getElementById('noProofMessage').style.display = order.payment_proof ? 'none' : 'block';

                const deliveryDateDisplay = document.getElementById('deliveryDateDisplay');
                const deliveryDate = document.getElementById('deliveryDate');
                if (order.status === 'shipped' && order.expected_delivery_date) {
                    const formattedDate = new Date(order.expected_delivery_date).toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    deliveryDate.textContent = formattedDate;
                    deliveryDateDisplay.style.display = 'flex';
                } else {
                    deliveryDateDisplay.style.display = 'none';
                }

                const deliveryCompletedDisplay = document.getElementById('deliveryCompletedDisplay');
                const deliveryCompleted = document.getElementById('deliveryCompleted');
                if (order.status === 'delivered' && order.delivery_completed_on) {
                    const formattedCompletedDate = new Date(order.delivery_completed_on).toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    deliveryCompleted.textContent = formattedCompletedDate;
                    deliveryCompletedDisplay.style.display = 'flex';
                } else {
                    deliveryCompletedDisplay.style.display = 'none';
                }

                let summaryHTML = '';
                order.items.forEach(item => {
                    summaryHTML += `
                        <div class="summary-item">
                            <span>${item.product_name} (${item.color}, ${item.size}) x${item.quantity}</span>
                            <span>PHP ${(item.price * item.quantity).toFixed(2)}</span>
                        </div>
                    `;
                });
                document.getElementById('summaryItems').innerHTML = summaryHTML;

                orderDetailsModal.style.display = 'flex';
            } catch (error) {
                console.error('Error opening order details modal:', error);
                alert('An error occurred while opening the order details. Please try again.');
            }
        }

        closeModal.addEventListener('click', () => {
            orderDetailsModal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === orderDetailsModal) {
                orderDetailsModal.style.display = 'none';
            }
        });

        // Filter and Pagination functionality
        document.addEventListener('DOMContentLoaded', () => {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const orderCardsContainer = document.getElementById('orderCards');
            const paginationContainer = document.getElementById('pagination');

            function updatePagination(filteredOrders, page) {
                const totalPages = Math.ceil(filteredOrders.length / ordersPerPage) || 1;
                currentPage = Math.min(page, totalPages);

                // Generate pagination HTML
                let paginationHTML = '';
                if (totalPages > 1) {
                    paginationHTML += `
                        <a href="?status=${encodeURIComponent(currentFilter)}&page=${Math.max(1, currentPage - 1)}" 
                           class="page-btn ${currentPage === 1 ? 'disabled' : ''}">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    `;
                    for (let i = 1; i <= totalPages; i++) {
                        paginationHTML += `
                            <a href="?status=${encodeURIComponent(currentFilter)}&page=${i}" 
                               class="page-btn ${currentPage === i ? 'active' : ''}">
                                ${i}
                            </a>
                        `;
                    }
                    paginationHTML += `
                        <a href="?status=${encodeURIComponent(currentFilter)}&page=${Math.min(totalPages, currentPage + 1)}" 
                           class="page-btn ${currentPage === totalPages ? 'disabled' : ''}">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    `;
                }
                paginationContainer.innerHTML = paginationHTML;

                // Update URL without reloading
                const newUrl = `?status=${encodeURIComponent(currentFilter)}&page=${currentPage}`;
                history.pushState({
                    filter: currentFilter,
                    page: currentPage
                }, '', newUrl);
            }

            function applyFilterAndPagination(filter, page) {
                currentFilter = filter;
                currentPage = page;

                // Filter orders
                const filteredOrders = filter === 'all' ? orders : orders.filter(order => order.status === filter);
                const totalPages = Math.ceil(filteredOrders.length / ordersPerPage) || 1;
                currentPage = Math.min(page, totalPages);

                // Update badge counts
                filterButtons.forEach(button => {
                    const status = button.getAttribute('data-status');
                    const badge = button.querySelector('.notification-badge');
                    badge.textContent = statusCounts[status];
                    badge.classList.toggle('hidden', statusCounts[status] == 0);
                });

                // If no orders for the filter, show empty state
                if (filteredOrders.length === 0) {
                    const emptyState = emptyStateMessages[filter];
                    orderCardsContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h2>${emptyState.title}</h2>
                            <p>${emptyState.message}</p>
                            <a href="cart.php" class="button">Go to Cart</a>
                        </div>
                    `;
                    paginationContainer.innerHTML = '';
                    return;
                }

                // Slice orders for current page
                const start = (currentPage - 1) * ordersPerPage;
                const paginatedOrders = filteredOrders.slice(start, start + ordersPerPage);

                // Generate order cards HTML
                let cardsHTML = '';
                paginatedOrders.forEach(order => {
                    let itemsHTML = '';
                    order.items.forEach(item => {
                        itemsHTML += `
                            <div class="item-row">
                                <img class="item-image" src="${item.image}" alt="${item.product_name}">
                                <div class="item-details">
                                    <div>
                                        <div class="item-name">${item.product_name}</div>
                                        <div class="item-specs">
                                            Color: ${item.color} | Size: ${item.size}
                                        </div>
                                    </div>
                                    <div class="item-price">PHP ${Number(item.price).toFixed(2)}</div>
                                    <div class="item-quantity">Qty: ${item.quantity}</div>
                                    <div class="item-price">PHP ${(item.price * item.quantity).toFixed(2)}</div>
                                </div>
                            </div>
                        `;
                    });

                    let deliveryInfo = '';
                    if (order.status === 'shipped' && order.expected_delivery_date) {
                        deliveryInfo = `<p class="expected-delivery">Expected Delivery Date: ${new Date(order.expected_delivery_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>`;
                    } else if (order.status === 'delivered' && order.delivery_completed_on) {
                        deliveryInfo = `<p class="delivery-completed">Delivery Completed On: ${new Date(order.delivery_completed_on).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>`;
                    }

                    let paymentMethodHTML = order.payment_method !== 'N/A' ? `
                        <div class="payment-method">
                            <i class="fas fa-credit-card"></i>
                            Payment Method: ${order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                        </div>
                    ` : '';

                    cardsHTML += `
                        <div class="order-card" data-status="${order.status}">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>Order #${order.order_id.substring(6)}</h3>
                                    <p>Placed on ${new Date(order.timestamp).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</p>
                                    ${deliveryInfo}
                                </div>
                                <div class="order-status ${order.status}">
                                    <i class="fas fa-${order.status === 'delivered' ? 'check-circle' : (order.status === 'pending' ? 'clock' : (order.status === 'shipped' ? 'truck' : 'times-circle'))}"></i>
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </div>
                            </div>
                            <div class="order-items">
                                ${itemsHTML}
                            </div>
                            <div class="order-total">
                                <div class="total-details">
                                    <div class="subtotal-amount">Subtotal: PHP ${Number(order.subtotal).toFixed(2)}</div>
                                    <div class="shipping-fee">Shipping Fee: PHP ${Number(order.shipping_fee).toFixed(2)}</div>
                                    <div class="total-amount">Total: PHP ${Number(order.total_amount).toFixed(2)}</div>
                                    ${paymentMethodHTML}
                                </div>
                                <button class="button success" data-order='${JSON.stringify(order).replace(/'/g, "\\'")}' onclick="openOrderDetailsModal(this)">
                                    <i class="fas fa-eye"></i>
                                    View Order
                                </button>
                            </div>
                        </div>
                    `;
                });

                orderCardsContainer.innerHTML = cardsHTML;

                // Attach event listeners to View Order buttons
                orderCardsContainer.querySelectorAll('.button.success').forEach(button => {
                    button.addEventListener('click', () => openOrderDetailsModal(button));
                });

                // Update pagination
                updatePagination(filteredOrders, currentPage);

                // Animate cards
                const orderCards = orderCardsContainer.querySelectorAll('.order-card');
                orderCards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }

            // Handle filter button clicks
            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    const filter = button.getAttribute('data-status');
                    applyFilterAndPagination(filter, 1);
                });
            });

            // Handle pagination clicks
            paginationContainer.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.closest('.page-btn');
                if (!target || target.classList.contains('disabled') || target.classList.contains('active')) return;

                const page = target.textContent.trim() === '' ?
                    (target.querySelector('i.fa-chevron-left') ? currentPage - 1 : currentPage + 1) :
                    parseInt(target.textContent);

                applyFilterAndPagination(currentFilter, page);
            });

            // Handle browser back/forward
            window.addEventListener('popstate', (e) => {
                const state = e.state || {
                    filter: 'all',
                    page: 1
                };
                currentFilter = state.filter;
                currentPage = state.page;
                filterButtons.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-status') === currentFilter));
                applyFilterAndPagination(currentFilter, currentPage);
            });

            // Auto-hide messages after 10 seconds
            setTimeout(() => {
                const messages = document.querySelectorAll('.message');
                messages.forEach(message => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-20px)';
                    setTimeout(() => message.remove(), 300);
                });
            }, 10000);

            // Initial render
            applyFilterAndPagination(currentFilter, currentPage);
        });
    </script>
</body>

</html>