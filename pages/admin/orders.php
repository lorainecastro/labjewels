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

// $ordersXmlFile = __DIR__ . '/../../xml/orders.xml';
$ordersXmlFile = '../../xml/orders.xml';
// $productsXmlFile = __DIR__ . '/../../xml/products.xml';
$productsXmlFile = '../../xml/products.xml';

function loadOrdersXML($file)
{
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
        $xml->asXML($file);
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        error_log("Error: Failed to load orders XML file at $file");
        $_SESSION['error'] = 'Error loading orders XML file.';
        exit;
    }
    return $xml;
}

function loadProductsXML($file)
{
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><store><products></products></store>');
        if ($xml->asXML($file) === false) {
            error_log("Error: Failed to create products XML file at $file");
            $_SESSION['error'] = 'Error creating products XML file.';
            exit;
        }
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        error_log("Error: Failed to load products XML file at $file");
        $_SESSION['error'] = 'Error loading products XML file.';
        exit;
    }
    return $xml;
}

function updateProductStock($productsXmlFile, $items, $increment = false)
{
    $productsXml = loadProductsXML($productsXmlFile);
    $updated = false;
    $errors = [];

    // Ensure items is an array
    $items = is_array($items) ? $items : [$items];

    foreach ($items as $item) {
        $productId = (string)$item['product_id'];
        $quantity = (int)$item['quantity'];
        $found = false;

        foreach ($productsXml->products->product as $product) {
            if ((string)$product->id === $productId) {
                $found = true;
                $currentStock = isset($product->stock) ? (int)$product->stock : 0;

                // Ensure stock doesn't go below 0 when decrementing
                $newStock = $increment ? $currentStock + $quantity : max(0, $currentStock - $quantity);

                if (!$increment && $currentStock < $quantity) {
                    $errors[] = "Insufficient stock for product $productId. Current: $currentStock, Required: $quantity";
                    error_log("Error: Insufficient stock for product $productId. Current: $currentStock, Required: $quantity");
                    continue;
                }

                $product->stock = $newStock;
                $updated = true;
                error_log("Stock updated for product $productId: $currentStock -> $newStock");
                break;
            }
        }

        if (!$found) {
            $errors[] = "Product $productId not found in products XML";
            error_log("Warning: Product $productId not found in products XML");
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('; ', $errors);
        return false;
    }

    if ($updated) {
        if ($productsXml->asXML($productsXmlFile) === false) {
            error_log("Error: Failed to save products XML file at $productsXmlFile");
            $_SESSION['error'] = 'Error saving product stock updates.';
            return false;
        }
        error_log("Success: Products XML updated.");
        return true;
    }

    return false;
}

// Load orders from XML
$xml = loadOrdersXML($ordersXmlFile);
$orders = [];
$pdo = getDBConnection();

if ($xml && isset($xml->order)) {
    foreach ($xml->order as $order) {
        $subtotal = 0;
        $itemsArray = [];

        // Log the order ID being processed
        error_log("Processing order: " . (string)$order->order_id);

        // Handle items explicitly to avoid SimpleXMLElement issues
        if (isset($order->items->item)) {
            foreach ($order->items->item as $item) {
                $price = isset($item->price) ? (float)$item->price : 0;
                $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
                $itemTotal = $price * $quantity;
                $subtotal += $itemTotal;

                $itemsArray[] = [
                    'product_id' => (string)$item->product_id,
                    'name' => (string)$item->product_name,
                    'price' => (float)$item->price,
                    'quantity' => (int)$item->quantity,
                    'total' => $itemTotal,
                    'image' => (string)$item->image
                ];

                // Log each item for debugging
                error_log("Item for order " . (string)$order->order_id . ": " . (string)$item->product_name . ", Price: $price, Quantity: $quantity, Total: $itemTotal");
            }
        } else {
            error_log("No items found for order: " . (string)$order->order_id);
        }

        $shipping_fee = isset($order->shipping_fee) ? (float)$order->shipping_fee : 0;
        $total_amount = $subtotal + $shipping_fee;

        // Log the calculated totals
        error_log("Order " . (string)$order->order_id . " - Subtotal: $subtotal, Shipping: $shipping_fee, Total: $total_amount");

        // Fetch user details for customer name and address
        $stmt = $pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) AS full_name, email, address, phone FROM users WHERE user_id = ? AND isDeleted = 0");
        $stmt->execute([(int)$order->user_id]);
        $user = $stmt->fetch();

        $orders[] = [
            'id' => (string)$order->order_id,
            'user_id' => (int)$order->user_id,
            'timestamp' => (string)$order->timestamp,
            'payment' => isset($order->payment_method) ? (string)$order->payment_method : 'N/A',
            'payment_proof' => isset($order->payment_proof) ? (string)$order->payment_proof : null,
            'status' => isset($order->status) ? (string)$order->status : 'pending',
            'subtotal' => $subtotal,
            'shipping' => $shipping_fee,
            'tax' => 0.00,
            'total' => $total_amount,
            'customer' => $user ? $user['full_name'] : 'Unknown',
            'email' => $user ? $user['email'] : 'N/A',
            'phone' => $user ? $user['phone'] : 'N/A',
            'address' => $user ? $user['address'] : 'N/A',
            'expected_delivery_date' => (isset($order->expected_delivery_date) && (string)$order->status === 'shipped') ? (string)$order->expected_delivery_date : '',
            'delivery_completed_on' => isset($order->delivery_completed_on) ? (string)$order->delivery_completed_on : '',
            'items' => $itemsArray
        ];
    }
}

// Sort orders by timestamp (newest first)
usort($orders, function ($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Calculate dashboard metrics
$totalOrders = count($orders);
$shippedOrders = count(array_filter($orders, function ($order) {
    return $order['status'] === 'shipped';
}));
$pendingOrders = count(array_filter($orders, function ($order) {
    return $order['status'] === 'pending';
}));
$deliveredOrders = count(array_filter($orders, function ($order) {
    return $order['status'] === 'delivered';
}));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'export_pdf') {
        require_once('../../TCPDF-main/TCPDF-main/tcpdf.php');

        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LAB Jewels');
        $pdf->SetTitle('Orders Report');
        $pdf->SetHeaderData('', 0, 'Orders Report', 'Generated on ' . date('Y-m-d H:i:s'));
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(10, 20, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $html = '<h1>Orders Report</h1><table border="1" cellpadding="5">
            <thead>
                <tr style="background-color: #f0f0f0;">
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Products</th>
                    <th>Quantities</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Expected Delivery</th>
                    <th>Delivery Completed On</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($orders as $order) {
            $productList = array_map(function ($item) {
                return htmlspecialchars($item['name']);
            }, $order['items']);
            $quantityList = array_map(function ($item) {
                return $item['quantity'];
            }, $order['items']);
            $productDisplay = implode(', ', $productList);
            $quantityDisplay = implode(', ', $quantityList);
            if (strlen($productDisplay) > 50) {
                $productDisplay = substr($productDisplay, 0, 47) . '...';
            }
            $expectedDelivery = $order['status'] === 'shipped' && $order['expected_delivery_date'] ?
                date('M j, Y', strtotime($order['expected_delivery_date'])) : '';
            $deliveryCompleted = $order['status'] === 'delivered' && $order['delivery_completed_on'] ?
                date('M j, Y', strtotime($order['delivery_completed_on'])) : '';

            $html .= "<tr>
                <td>" . htmlspecialchars(substr($order['id'], 6)) . "</td>
                <td>" . date('M j, Y g:i A', strtotime($order['timestamp'])) . "</td>
                <td>" . $productDisplay . "</td>
                <td>" . $quantityDisplay . "</td>
                <td>PHP " . number_format($order['total'], 2) . "</td>
                <td>" . htmlspecialchars(str_replace('_', ' ', $order['payment'])) . "</td>
                <td>" . ucfirst(htmlspecialchars($order['status'])) . "</td>
                <td>" . htmlspecialchars($expectedDelivery) . "</td>
                <td>" . htmlspecialchars($deliveryCompleted) . "</td>
            </tr>";
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('orders_report.pdf', 'D');
        exit;
    } elseif ($_POST['action'] === 'print_order') {
        $order_id = $_POST['order_id'] ?? '';
        $order = array_filter($orders, function ($o) use ($order_id) {
            return $o['id'] === $order_id;
        });
        $order = reset($order);

        if ($order) {
            require_once('../../TCPDF-main/TCPDF-main/tcpdf.php');

            $pdf = new TCPDF();
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('LAB Jewels');
            $pdf->SetTitle('Order #' . substr($order['id'], 6));
            $pdf->SetHeaderData('', 0, 'Order #' . substr($order['id'], 6), 'Generated on ' . date('Y-m-d H:i:s'));
            $pdf->setHeaderFont(['helvetica', '', 10]);
            $pdf->setFooterFont(['helvetica', '', 8]);
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->AddPage();

            $expectedDelivery = $order['status'] === 'shipped' && $order['expected_delivery_date'] ?
                date('M j, Y', strtotime($order['expected_delivery_date'])) : '';
            $deliveryCompleted = $order['status'] === 'delivered' && $order['delivery_completed_on'] ?
                date('M j, Y', strtotime($order['delivery_completed_on'])) : '';

            $html = '<h1>Order #' . htmlspecialchars(substr($order['id'], 6)) . '</h1>';
            $html .= '<h3>Order Details</h3>';
            $html .= '<table cellpadding="5">
                <tr><td><strong>Customer:</strong></td><td>' . htmlspecialchars($order['customer']) . '</td></tr>
                <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($order['email']) . '</td></tr>
                <tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($order['phone']) . '</td></tr>
                <tr><td><strong>Shipping Address:</strong></td><td>' . htmlspecialchars($order['address']) . '</td></tr>
                <tr><td><strong>Order Date:</strong></td><td>' . date('M j, Y g:i A', strtotime($order['timestamp'])) . '</td></tr>
                <tr><td><strong>Payment Method:</strong></td><td>' . htmlspecialchars(str_replace('_', ' ', $order['payment'])) . '</td></tr>
                <tr><td><strong>Status:</strong></td><td>' . ucfirst(htmlspecialchars($order['status'])) . '</td></tr>';

            if ($order['status'] === 'shipped') {
                $html .= '<tr><td><strong>Expected Delivery:</strong></td><td>' . htmlspecialchars($expectedDelivery) . '</td></tr>';
            }
            if ($order['status'] === 'delivered' && $deliveryCompleted) {
                $html .= '<tr><td><strong>Delivery Completed On:</strong></td><td>' . htmlspecialchars($deliveryCompleted) . '</td></tr>';
            }

            $html .= '</table>';

            $html .= '<h3 style="margin-top: 20px;">Order Items</h3>';
            $html .= '<table border="1" cellpadding="5">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($order['items'] as $item) {
                $html .= "<tr>
                    <td>" . htmlspecialchars($item['name']) . "</td>
                    <td>" . $item['quantity'] . "</td>
                    <td>PHP " . number_format($item['price'], 2) . "</td>
                    <td>PHP " . number_format($item['total'], 2) . "</td>
                </tr>";
            }

            $html .= '</tbody></table>';

            $html .= '<h3 style="margin-top: 20px;">Order Summary</h3>';
            $html .= '<table cellpadding="5">
                <tr><td><strong>Subtotal:</strong></td><td>PHP ' . number_format($order['subtotal'], 2) . '</td></tr>
                <tr><td><strong>Shipping Fee:</strong></td><td>PHP ' . number_format($order['shipping'], 2) . '</td></tr>
                <tr><td><strong>Tax:</strong></td><td>PHP ' . number_format($order['tax'], 2) . '</td></tr>
                <tr><td><strong>Total:</strong></td><td><strong>PHP ' . number_format($order['total'], 2) . '</strong></td></tr>
            </table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('order_' . substr($order['id'], 6) . '.pdf', 'D');
            exit;
        } else {
            $_SESSION['error'] = 'Order not found.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($_POST['action'] === 'update_status') {
        $order_id = $_POST['order_id'] ?? '';
        $new_status = $_POST['status'] ?? '';
        if ($order_id && in_array($new_status, ['pending', 'shipped', 'delivered', 'cancelled'])) {
            $xml = loadOrdersXML($ordersXmlFile);
            $success = false;

            foreach ($xml->order as $order) {
                if ((string)$order->order_id === $order_id) {
                    $previous_status = (string)$order->status;

                    // Collect all items in the order
                    $items = [];
                    if (isset($order->items->item)) {
                        foreach ($order->items->item as $item) {
                            $items[] = [
                                'product_id' => (string)$item->product_id,
                                'quantity' => (int)$item->quantity
                            ];
                        }
                    }

                    if (empty($items)) {
                        error_log("Error: No items found for order $order_id");
                        $_SESSION['error'] = "No items found for order $order_id.";
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }

                    // Update stock only when transitioning to "shipped" from a non-shipped state
                    if ($new_status === 'shipped' && $previous_status !== 'shipped') {
                        if (!updateProductStock($productsXmlFile, $items)) {
                            error_log("Error: Failed to decrement stock for order $order_id");
                            header('Location: ' . $_SERVER['PHP_SELF']);
                            exit;
                        }
                    } elseif ($new_status === 'cancelled' && $previous_status === 'shipped') {
                        // Increment stock back if cancelling a shipped order
                        if (!updateProductStock($productsXmlFile, $items, true)) {
                            error_log("Error: Failed to increment stock for cancelled order $order_id");
                            header('Location: ' . $_SERVER['PHP_SELF']);
                            exit;
                        }
                    }

                    // Update order status and related fields
                    $order->status = $new_status;
                    if ($new_status === 'shipped') {
                        $order->expected_delivery_date = date('Y-m-d', strtotime('+3 days'));
                        unset($order->delivery_completed_on);
                    } elseif ($new_status === 'delivered') {
                        $order->delivery_completed_on = date('Y-m-d');
                        unset($order->expected_delivery_date);
                    } else {
                        unset($order->expected_delivery_date);
                        unset($order->delivery_completed_on);
                    }

                    if ($xml->asXML($ordersXmlFile) === false) {
                        error_log("Error: Failed to save orders XML file at $ordersXmlFile");
                        $_SESSION['error'] = 'Error saving order status update.';
                    } else {
                        error_log("Success: Order $order_id status updated to $new_status");
                        $_SESSION['message'] = "Order $order_id status updated to $new_status";
                        $success = true;
                    }
                    break;
                }
            }

            if (!$success) {
                error_log("Error: Order $order_id not found");
                $_SESSION['error'] = "Order $order_id not found.";
            }

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            error_log("Error: Invalid order ID ($order_id) or status ($new_status)");
            $_SESSION['error'] = 'Invalid order ID or status.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Order Management</title>
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

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--blackfont-color);
        }

        .btn-outline:hover {
            background-color: var(--inputfieldhover-color);
            transform: translateY(-1px);
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

        .bg-orange {
            background: linear-gradient(135deg, #f59e0b, #f97316);
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
            flex: 2;
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

        .filter-box {
            flex: 1;
        }

        .filter-box select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-box select:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .status-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .status-btn {
            padding: 10px 20px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            font-weight: 500;
            color: var(--blackfont-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .status-btn.active {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            border-color: var(--primary-gradient);
            box-shadow: var(--shadow-sm);
        }

        .status-btn:hover:not(.active) {
            background-color: var(--inputfieldhover-color);
            transform: translateY(-1px);
        }

        .orders-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .order-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow-x: auto;
            display: block;
        }

        .order-table th {
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

        .order-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .order-table tr:last-child td {
            border-bottom: none;
        }

        .order-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .order-table th:nth-child(1),
        .order-table td:nth-child(1) {
            width: 10%;
        }

        .order-table th:nth-child(2),
        .order-table td:nth-child(2) {
            width: 18%;
        }

        .order-table th:nth-child(3),
        .order-table td:nth-child(3) {
            width: 20%;
        }

        .order-table th:nth-child(4),
        .order-table td:nth-child(4) {
            width: 5%;
        }

        .order-table th:nth-child(5),
        .order-table td:nth-child(5) {
            width: 12%;
        }

        .order-table th:nth-child(6),
        .order-table td:nth-child(6) {
            width: 10%;
        }

        .order-table th:nth-child(7),
        .order-table td:nth-child(7) {
            width: 10%;
        }

        .order-table th:nth-child(8),
        .order-table td:nth-child(8) {
            width: 18%;
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

        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .modal-backdrop.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--division-color);
            background: linear-gradient(to right, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            background: var(--inputfield-color);
            border: none;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            color: var(--grayfont-color);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover {
            background-color: var(--inputfieldhover-color);
            color: var(--blackfont-color);
        }

        .modal-body {
            padding: 24px;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .detail-group {
            margin-bottom: 20px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--grayfont-color);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 500;
        }

        .order-items {
            margin: 24px 0;
            border: 1px solid var(--division-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--division-color);
            background-color: var(--card-bg);
        }

        .item:last-child {
            border-bottom: none;
        }

        .item:hover {
            background-color: var(--inputfield-color);
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background-color: var(--division-color);
            object-fit: cover;
            border: 1px solid var(--border-color);
        }

        .item-details h4 {
            font-size: 16px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .item-price {
            font-size: 14px;
            color: var(--blackfont-color);
        }

        .item-total {
            font-size: 16px;
            font-weight: 600;
        }

        .order-summary {
            background-color: var(--inputfield-color);
            border-radius: 12px;
            padding: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .summary-row:last-child {
            margin-bottom: 0;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 18px;
        }

        .update-status {
            margin-top: 28px;
        }

        .update-status h4 {
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 16px;
        }

        .update-status select {
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 15px;
            margin-bottom: 16px;
            cursor: pointer;
        }

        .update-status select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 20px 24px;
            border-top: 1px solid var(--division-color);
            gap: 16px;
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
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .success-message {
            background-color: var(--success-color);
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
            background-color: var(--danger-color);
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

            .order-table {
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

            .modal {
                width: 95%;
                border-radius: 12px;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .item-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .item-total {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid {
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
            <h1>Order Management</h1>
            <button class="btn btn-pdf" onclick="exportPDF()">
                <i class="fas fa-file-export"></i> Export Orders
            </button>
        </header>

        <div class="dashboard-grid">
            <div class="card" data-tooltip="Total number of orders placed">
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
            <div class="card" data-tooltip="Orders awaiting processing">
                <div class="card-header">
                    <div>
                        <div class="card-title">Pending Orders</div>
                        <div class="card-value"><?php echo number_format($pendingOrders); ?></div>
                    </div>
                    <div class="card-icon bg-orange">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="card" data-tooltip="Orders that have been shipped">
                <div class="card-header">
                    <div>
                        <div class="card-title">Shipped Orders</div>
                        <div class="card-value"><?php echo number_format($shippedOrders); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                </div>
            </div>
            <div class="card" data-tooltip="Orders successfully delivered">
                <div class="card-header">
                    <div>
                        <div class="card-title">Delivered Orders</div>
                        <div class="card-value"><?php echo number_format($deliveredOrders); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="orderSearch" placeholder="Search by order ID, customer name or email..." oninput="filterOrders()">
            </div>
            <div class="filter-box">
                <select id="dateFilter" onchange="filterOrders()">
                    <option value="">Date Range</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last7days">Last 7 Days</option>
                    <option value="thisMonth">This Month</option>
                </select>
            </div>
            <div class="filter-box">
                <select id="paymentFilter" onchange="filterOrders()">
                    <option value="">Payment Method</option>
                    <option value="GCash">GCash</option>
                    <option value="PayMaya">PayMaya</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Cash On Delivery">Cash on Delivery</option>
                </select>
            </div>
        </div>

        <div class="status-filter">
            <button class="status-btn active" data-status="all">All Orders</button>
            <button class="status-btn" data-status="pending">Pending</button>
            <button class="status-btn" data-status="shipped">Shipped</button>
            <button class="status-btn" data-status="delivered">Delivered</button>
            <button class="status-btn" data-status="cancelled">Cancelled</button>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="status-message success-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['message']);
                unset($_SESSION['message']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="status-message error-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="orders-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="orderTableBody">
                    <!-- Order rows will be populated dynamically -->
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- Order Detail Modal -->
        <div class="modal-backdrop" id="orderDetailModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Order #<span id="modalOrderId"></span></h3>
                    <button class="modal-close" onclick="closeOrderModal()">×</button>
                </div>
                <div class="modal-body">
                    <div class="order-details">
                        <div>
                            <div class="detail-group">
                                <div class="detail-label">Customer</div>
                                <div class="detail-value" id="modalCustomer"></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email</div>
                                <div class="detail-value" id="modalEmail"></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value" id="modalPhone"></div>
                            </div>
                        </div>
                        <div>
                            <div class="detail-group">
                                <div class="detail-label">Order Date</div>
                                <div class="detail-value" id="modalDate"></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Payment Method</div>
                                <div class="detail-value" id="modalPayment"></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="status-badge" id="modalStatus"></span>
                                </div>
                            </div>
                            <div class="detail-group" id="modalExpectedDeliveryGroup" style="display: none;">
                                <div class="detail-label">Expected Delivery</div>
                                <div class="detail-value" id="modalExpectedDelivery"></div>
                            </div>
                            <div class="detail-group" id="modalDeliveryCompletedGroup" style="display: none;">
                                <div class="detail-label">Delivery Completed On</div>
                                <div class="detail-value" id="modalDeliveryCompleted"></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="detail-label">Shipping Address</div>
                        <div class="detail-value" id="modalAddress"></div>
                    </div>

                    <h4 class="section-title">Order Items</h4>
                    <div class="order-items" id="modalItems"></div>

                    <div class="order-summary">
                        <div class="summary-row">
                            <div>Subtotal</div>
                            <div id="modalSubtotal"></div>
                        </div>
                        <div class="summary-row">
                            <div>Shipping Fee</div>
                            <div id="modalShipping"></div>
                        </div>
                        <div class="summary-row">
                            <div>Tax</div>
                            <div id="modalTax">PHP 0.00</div>
                        </div>
                        <div class="summary-row">
                            <div>Total</div>
                            <div id="modalTotal"></div>
                        </div>
                    </div>

                    <div class="update-status">
                        <h4>Update Order Status</h4>
                        <select id="statusUpdate">
                            <option value="pending">Pending</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeOrderModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveStatus()">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const orders = <?php echo json_encode($orders); ?>;
        const itemsPerPage = 5;
        let currentPage = 1;

        function populateOrderTable(filteredOrders) {
            const tableBody = document.getElementById('orderTableBody');
            tableBody.innerHTML = '';

            if (filteredOrders.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8">
                            <div class="no-orders">
                                <i class="fas fa-box-open"></i>
                                <h3>No orders found</h3>
                                <p>There are no orders matching your current filters.</p>
                            </div>
                        </td>
                    </tr>`;
                updatePagination([]);
                return;
            }

            const visibleRows = [];
            filteredOrders.forEach(order => {
                const row = document.createElement('tr');
                row.className = 'order-row';
                row.dataset.orderId = order.id;
                row.dataset.customer = order.customer.toLowerCase();
                row.dataset.email = order.email.toLowerCase();
                row.dataset.payment = order.payment.toLowerCase();
                row.dataset.date = order.timestamp;

                const statusClass = `status-${order.status}`;
                const productDisplay = order.items.length > 0 ? order.items[0].name + (order.items.length > 1 ? '...' : '') : '';
                const truncatedProduct = productDisplay.length > 30 ? productDisplay.substring(0, 27) + '...' : productDisplay;
                const totalQuantity = order.items.reduce((sum, item) => sum + item.quantity, 0);

                row.innerHTML = `
                    <td>#${order.id.substring(6)}</td>
                    <td>${new Date(order.timestamp).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}</td>
                    <td>${truncatedProduct}</td>
                    <td>${totalQuantity}</td>
                    <td>PHP ${Number(order.total).toFixed(2)}</td>
                    <td>${order.payment.replace('_', ' ')}</td>
                    <td><span class="status-badge ${statusClass}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
                    <td class="action-cell">
                        <button class="btn btn-primary view-order" data-id="${order.id}">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-outline print-order" data-id="${order.id}">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
                visibleRows.push(row);
            });

            // Add event listeners to view buttons
            document.querySelectorAll('.view-order').forEach(button => {
                button.addEventListener('click', (e) => {
                    const orderId = e.target.closest('button').dataset.id;
                    openOrderModal(orderId);
                });
            });

            // Add event listeners to print buttons
            document.querySelectorAll('.print-order').forEach(button => {
                button.addEventListener('click', (e) => {
                    const orderId = e.target.closest('button').dataset.id;
                    printOrder(orderId);
                });
            });

            updatePagination(visibleRows);
        }

        function updateStatusOptions(order) {
            const statusSelect = document.getElementById('statusUpdate');
            const options = statusSelect.querySelectorAll('option');
            options.forEach(opt => opt.disabled = false); // Reset all options

            if (order.status === 'shipped') {
                statusSelect.querySelector('option[value="pending"]').disabled = true;
            } else if (order.status === 'delivered') {
                statusSelect.querySelector('option[value="pending"]').disabled = true;
                statusSelect.querySelector('option[value="shipped"]').disabled = true;
            } else if (order.status === 'cancelled') {
                statusSelect.querySelector('option[value="pending"]').disabled = true;
                statusSelect.querySelector('option[value="shipped"]').disabled = true;
                statusSelect.querySelector('option[value="delivered"]').disabled = true;
            }
        }

        function openOrderModal(orderId) {
            const order = orders.find(order => order.id === orderId);
            if (!order) return;

            document.getElementById('modalOrderId').textContent = order.id.substring(6);
            document.getElementById('modalCustomer').textContent = order.customer;
            document.getElementById('modalEmail').textContent = order.email;
            document.getElementById('modalPhone').textContent = order.phone;
            document.getElementById('modalDate').textContent = new Date(order.timestamp).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('modalPayment').textContent = order.payment.replace('_', ' ');
            document.getElementById('modalAddress').textContent = order.address;
            document.getElementById('modalSubtotal').textContent = `PHP ${Number(order.subtotal).toFixed(2)}`;
            document.getElementById('modalShipping').textContent = `PHP ${Number(order.shipping).toFixed(2)}`;
            document.getElementById('modalTax').textContent = `PHP ${Number(order.tax).toFixed(2)}`;
            document.getElementById('modalTotal').textContent = `PHP ${Number(order.total).toFixed(2)}`;

            const expectedDeliveryGroup = document.getElementById('modalExpectedDeliveryGroup');
            const expectedDelivery = document.getElementById('modalExpectedDelivery');
            if (order.status === 'shipped' && order.expected_delivery_date) {
                expectedDelivery.textContent = new Date(order.expected_delivery_date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                expectedDeliveryGroup.style.display = 'block';
            } else {
                expectedDeliveryGroup.style.display = 'none';
            }

            const deliveryCompletedGroup = document.getElementById('modalDeliveryCompletedGroup');
            const deliveryCompleted = document.getElementById('modalDeliveryCompleted');
            if (order.status === 'delivered' && order.delivery_completed_on) {
                deliveryCompleted.textContent = new Date(order.delivery_completed_on).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                deliveryCompletedGroup.style.display = 'block';
            } else {
                deliveryCompletedGroup.style.display = 'none';
            }

            const statusBadge = document.getElementById('modalStatus');
            statusBadge.className = `status-badge status-${order.status}`;
            statusBadge.textContent = order.status.charAt(0).toUpperCase() + order.status.slice(1);

            document.getElementById('statusUpdate').value = order.status;

            const itemsContainer = document.getElementById('modalItems');
            itemsContainer.innerHTML = '';
            order.items.forEach(item => {
                const itemElement = document.createElement('div');
                itemElement.className = 'item';
                itemElement.innerHTML = `
                    <div class="item-info">
                        <img src="../../../../${item.image}" alt="${item.name}" class="item-image">
                        <div class="item-details">
                            <h4>${item.name}</h4>
                            <div class="item-price">PHP ${Number(item.price).toFixed(2)} × ${item.quantity}</div>
                        </div>
                    </div>
                    <div class="item-total">PHP ${Number(item.total).toFixed(2)}</div>
                `;
                itemsContainer.appendChild(itemElement);
            });

            updateStatusOptions(order);

            document.getElementById('orderDetailModal').classList.add('active');
        }

        function closeOrderModal() {
            document.getElementById('orderDetailModal').classList.remove('active');
        }

        function saveStatus() {
            const orderId = document.getElementById('modalOrderId').textContent;
            const newStatus = document.getElementById('statusUpdate').value;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="order_${orderId}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function printOrder(orderId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            form.innerHTML = `
                <input type="hidden" name="action" value="print_order">
                <input type="hidden" name="order_id" value="${orderId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function exportPDF() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            form.innerHTML = `
                <input type="hidden" name="action" value="export_pdf">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function filterOrders() {
            const searchQuery = document.getElementById('orderSearch').value.toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const paymentFilter = document.getElementById('paymentFilter').value.toLowerCase();
            const statusFilter = document.querySelector('.status-btn.active').dataset.status;

            let filteredOrders = orders;

            // Apply search filter
            if (searchQuery) {
                filteredOrders = filteredOrders.filter(order =>
                    order.id.toLowerCase().includes(searchQuery) ||
                    order.customer.toLowerCase().includes(searchQuery) ||
                    order.email.toLowerCase().includes(searchQuery)
                );
            }

            // Apply date filter
            if (dateFilter) {
                const now = new Date();
                filteredOrders = filteredOrders.filter(order => {
                    const orderDate = new Date(order.timestamp);
                    if (dateFilter === 'today') {
                        return orderDate.toDateString() === now.toDateString();
                    } else if (dateFilter === 'yesterday') {
                        const yesterday = new Date(now);
                        yesterday.setDate(now.getDate() - 1);
                        return orderDate.toDateString() === yesterday.toDateString();
                    } else if (dateFilter === 'last7days') {
                        const last7Days = new Date(now);
                        last7Days.setDate(now.getDate() - 7);
                        return orderDate >= last7Days;
                    } else if (dateFilter === 'thisMonth') {
                        return orderDate.getMonth() === now.getMonth() &&
                               orderDate.getFullYear() === now.getFullYear();
                    }
                    return true;
                });
            }

            // Apply payment filter
            if (paymentFilter) {
                filteredOrders = filteredOrders.filter(order =>
                    order.payment.toLowerCase() === paymentFilter
                );
            }

            // Apply status filter
            if (statusFilter !== 'all') {
                filteredOrders = filteredOrders.filter(order => order.status === statusFilter);
            }

            // Update table with filtered orders
            populateOrderTable(filteredOrders);
        }

        function updatePagination(visibleRows) {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            const pageCount = Math.ceil(visibleRows.length / itemsPerPage);

            if (pageCount <= 1) {
                return;
            }

            const prevButton = document.createElement('button');
            prevButton.textContent = '←';
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    showPage(visibleRows);
                }
            });
            pagination.appendChild(prevButton);

            for (let i = 1; i <= pageCount; i++) {
                const button = document.createElement('button');
                button.textContent = i;
                button.className = i === currentPage ? 'active' : '';
                button.addEventListener('click', () => {
                    currentPage = i;
                    showPage(visibleRows);
                });
                pagination.appendChild(button);
            }

            const nextButton = document.createElement('button');
            nextButton.textContent = '→';
            nextButton.disabled = currentPage === pageCount;
            nextButton.addEventListener('click', () => {
                if (currentPage < pageCount) {
                    currentPage++;
                    showPage(visibleRows);
                }
            });
            pagination.appendChild(nextButton);
        }

        function showPage(visibleRows) {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;

            document.querySelectorAll('.order-row').forEach(row => {
                row.style.display = 'none';
            });

            visibleRows.slice(start, end).forEach(row => {
                row.style.display = '';
            });

            document.querySelectorAll('.pagination button').forEach(button => {
                button.classList.remove('active');
                if (parseInt(button.textContent) === currentPage) {
                    button.classList.add('active');
                }
            });

            const prevButton = document.querySelector('.pagination button:first-child');
            const nextButton = document.querySelector('.pagination button:last-child');
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === Math.ceil(visibleRows.length / itemsPerPage);
        }

        // Initialize status filter buttons
        document.querySelectorAll('.status-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.status-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                currentPage = 1; // Reset to first page
                filterOrders();
            });
        });

        // Initial table population
        populateOrderTable(orders);

        // Auto-hide notification after 3 seconds
        const notification = document.getElementById('notification');
        if (notification) {
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>