<?php
require '../../connection/config.php';
session_start();

// Check if user is logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'default-icon.png';
$ordersXmlFile = __DIR__ . '/../../xml/orders.xml';

// Load XML file
function loadXML($file)
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

// Load orders
$xml = loadXML($ordersXmlFile);
$orders = [];

if (isset($xml->order)) {
    foreach ($xml->order as $order) {
        $items = [];
        foreach ($order->items->item as $item) {
            $items[] = [
                'product_id' => (int)$item->product_id,
                'product_name' => (string)$item->product_name,
                'image' => (string)$item->image,
                'price' => (float)$item->price,
                'color' => (string)$item->color,
                'size' => (string)$item->size,
                'quantity' => (int)$item->quantity
            ];
        }
        $orders[] = [
            'order_id' => (string)$order->order_id,
            'user_id' => (int)$order->user_id,
            'timestamp' => (string)$order->timestamp,
            'payment_method' => (string)$order->payment_method,
            'payment_proof' => isset($order->payment_proof) ? (string)$order->payment_proof : null,
            'status' => (string)$order->status,
            'shipping_fee' => (float)$order->shipping_fee,
            'items' => $items,
            'expected_delivery_date' => isset($order->expected_delivery_date) ? (string)$order->expected_delivery_date : null,
            'delivery_completed_on' => isset($order->delivery_completed_on) ? (string)$order->delivery_completed_on : null
        ];
    }
}

// Filter orders by current user
$userOrders = array_filter($orders, function ($order) use ($currentUser) {
    return $order['user_id'] == $currentUser['user_id'];
});

// Filter by status
$currentStatus = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all';
$filteredOrders = $currentStatus === 'all' ? $userOrders : array_filter($userOrders, function ($order) use ($currentStatus) {
    return $order['status'] === $currentStatus;
});

// Pagination settings
$itemsPerPage = 6;
$maxPages = 6;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalItems = count($filteredOrders);
$totalPages = min(ceil($totalItems / $itemsPerPage), $maxPages);
$currentPage = min($currentPage, $totalPages ?: 1);
$start = ($currentPage - 1) * $itemsPerPage;
$paginatedOrders = array_slice($filteredOrders, $start, $itemsPerPage);
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
            --primary-color: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --primary-hover: #4f46e5;
            --secondary-color: #f43f5e;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #1f2937;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #f1f5f9;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --blackfont-color: #1f2937;
            --whitefont-color: #f9fafb;
            --grayfont-color: #9ca3af;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #4f46e5;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
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

        .navigation {
            margin-bottom: 20px;
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
            transform: translateX(5px);
        }

        .orders-section h2 {
            font-size: 36px;
            font-weight: 800;
            color: var(--blackfont-color);
            margin-bottom: 30px;
            text-align: center;
        }

        .status-filter {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .status-btn {
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

        .status-btn:hover, .status-btn.active {
            background-color: var(--primary-color);
            color: var(--whitefont-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .table-wrapper {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-top: 2rem;
            overflow: hidden;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            border: none;
            box-shadow: var(--shadow-md);
        }

        .orders-table th {
            background-color: var(--primary-color);
            color: var(--whitefont-color);
            font-weight: 600;
            padding: 1rem;
            text-align: center;
            font-size: 0.9rem;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .orders-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
            text-align: center;
            color: var(--blackfont-color);
            background-color: var(--card-bg);
        }

        .orders-table th,
        .orders-table td {
            border-left: none;
            border-right: none;
            border-top: none;
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .orders-table tr:hover td {
            background-color: var(--inputfield-color);
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: var(--shadow-sm);
        }

        .status {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status.shipped {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status.delivered {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status.cancelled {
            background-color: #fee2e2;
            color: #991b1b;
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
            color: var(--blackfont-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .page-btn.active {
            background: var(--primary-color);
            color: var(--whitefont-color);
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

        .empty-state {
            text-align: center;
            padding: 4rem 0;
            color: var(--grayfont-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--grayfont-color);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--blackfont-color);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: var(--grayfont-color);
        }

        .button {
            background-color: var(--primary-color);
            color: var(--whitefont-color);
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }

        .button:hover {
            background: var(--primary-gradient);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
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

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .orders-section h2 {
                font-size: 28px;
            }

            .status-filter {
                flex-direction: column;
                align-items: center;
            }

            .status-btn {
                width: 200px;
            }

            .table-wrapper {
                padding: 1rem;
                overflow-x: auto;
            }

            .orders-table th,
            .orders-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .product-image {
                width: 40px;
                height: 40px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .orders-section h2 {
                font-size: 24px;
            }

            .table-wrapper {
                padding: 0.5rem;
            }

            .orders-table th,
            .orders-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <div class="navigation">
            <a href="shop.php" class="nav-link">
                <i class="fas fa-store"></i>
                Back to Shop
            </a>
            <a href="cart.php" class="nav-link" style="margin-left: 20px;">
                <i class="fas fa-shopping-cart"></i>
                View Cart
            </a>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['order']) && $_GET['order'] === 'success'): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                Order placed successfully!
            </div>
        <?php endif; ?>

        <!-- Orders Section -->
        <div class="orders-section">
            <h2>My Orders</h2>
            <div class="status-filter" id="statusFilter">
                <button class="status-btn <?php echo $currentStatus === 'all' ? 'active' : ''; ?>" data-status="all">All Orders</button>
                <button class="status-btn <?php echo $currentStatus === 'pending' ? 'active' : ''; ?>" data-status="pending">Pending</button>
                <button class="status-btn <?php echo $currentStatus === 'shipped' ? 'active' : ''; ?>" data-status="shipped">Shipped</button>
                <button class="status-btn <?php echo $currentStatus === 'delivered' ? 'active' : ''; ?>" data-status="delivered">Delivered</button>
                <button class="status-btn <?php echo $currentStatus === 'cancelled' ? 'active' : ''; ?>" data-status="cancelled">Cancelled</button>
            </div>

            <div class="table-wrapper">
                <?php if (empty($paginatedOrders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Orders Found</h3>
                        <p>You have no orders in this category. Start shopping now!</p>
                        <a href="shop.php" class="button">
                            <i class="fas fa-shopping-bag"></i>
                            Shop Now
                        </a>
                    </div>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginatedOrders as $order): ?>
                                <?php
                                $subtotal = array_sum(array_map(function ($item) {
                                    return $item['price'] * $item['quantity'];
                                }, $order['items']));
                                $total = $subtotal + $order['shipping_fee'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($order['timestamp']))); ?></td>
                                    <td>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                <img class="product-image" src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($item['color']); ?> | <?php echo htmlspecialchars($item['size']); ?> | Qty: <?php echo $item['quantity']; ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>PHP <?php echo number_format($total, 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                    <td>
                                        <span class="status <?php echo htmlspecialchars($order['status']); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_proof']): ?>
                                            <a href="<?php echo htmlspecialchars($order['payment_proof']); ?>" target="_blank">View Proof</a><br>
                                        <?php endif; ?>
                                        <?php if ($order['expected_delivery_date']): ?>
                                            <small>Expected Delivery: <?php echo htmlspecialchars(date('M d, Y', strtotime($order['expected_delivery_date']))); ?></small><br>
                                        <?php endif; ?>
                                        <?php if ($order['delivery_completed_on']): ?>
                                            <small>Delivered On: <?php echo htmlspecialchars(date('M d, Y', strtotime($order['delivery_completed_on']))); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <a href="?status=<?php echo urlencode($currentStatus); ?>&page=<?php echo max(1, $currentPage - 1); ?>" 
                       class="page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?status=<?php echo urlencode($currentStatus); ?>&page=<?php echo $i; ?>" 
                           class="page-btn <?php echo $currentPage === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <a href="?status=<?php echo urlencode($currentStatus); ?>&page=<?php echo min($totalPages, $currentPage + 1); ?>" 
                       class="page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusButtons = document.querySelectorAll('.status-btn');
            const tableRows = document.querySelectorAll('.orders-table tbody tr');

            // Status filter handling
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    statusButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const status = this.getAttribute('data-status');
                    const url = new URL(window.location);
                    url.searchParams.set('status', status);
                    url.searchParams.delete('page'); // Reset to page 1
                    window.history.pushState({}, '', url);
                    window.location.href = url.toString();
                });
            });

            // Animation for table rows
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Auto-hide success message
            const message = document.querySelector('.message.success');
            if (message) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-20px)';
                    setTimeout(() => message.remove(), 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>