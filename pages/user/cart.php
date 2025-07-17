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
$xmlFile = __DIR__ . '/../../xml/cart.xml';
$ordersXmlFile = __DIR__ . '/../../xml/orders.xml';
$productsXmlFile = __DIR__ . '/../../xml/products.xml';

function loadXML($file)
{
    if (!file_exists($file)) {
        if ($file === __DIR__ . '/../../xml/cart.xml') {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cart></cart>');
            $xml->asXML($file);
        } elseif ($file === __DIR__ . '/../../xml/orders.xml') {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
            $xml->asXML($file);
        } else {
            die('XML file does not exist.');
        }
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

function saveOrderToXML($ordersFile, $userId, $items, $paymentMethod, $paymentProof = null, $shippingFee = 0)
{
    $xml = loadXML($ordersFile);
    
    $order = $xml->addChild('order');
    $orderId = 'order_' . uniqid();
    $order->addChild('order_id', $orderId);
    $order->addChild('user_id', $userId);
    $order->addChild('timestamp', date('Y-m-d H:i:s'));
    $order->addChild('payment_method', htmlspecialchars($paymentMethod));
    if (!empty($paymentProof)) {
        $order->addChild('payment_proof', htmlspecialchars($paymentProof));
    }
    $order->addChild('status', 'pending');
    $order->addChild('shipping_fee', number_format($shippingFee, 2, '.', ''));
    $itemsNode = $order->addChild('items');

    foreach ($items as $item) {
        $itemNode = $itemsNode->addChild('item');
        $itemNode->addChild('product_id', $item['product_id']);
        $itemNode->addChild('product_name', htmlspecialchars($item['product_name']));
        $itemNode->addChild('image', htmlspecialchars($item['image']));
        $itemNode->addChild('price', $item['price']);
        $itemNode->addChild('color', htmlspecialchars($item['color']));
        $itemNode->addChild('size', htmlspecialchars($item['size']));
        $itemNode->addChild('quantity', $item['quantity']);
    }

    if (!$xml->asXML($ordersFile)) {
        die('Error saving order to XML file.');
    }
    return $orderId;
}

// Load products XML to get stock information
$productsXml = loadXML($productsXmlFile);
$products = [];
foreach ($productsXml->products->product as $product) {
    $products[(int)$product->id] = [
        'id' => (int)$product->id,
        'stock' => (int)$product->stock
    ];
}

// Load cart XML
$xml = loadXML($xmlFile);
$cartItems = [];

if (isset($xml->item)) {
    foreach ($xml->item as $item) {
        $productId = (int)$item->product_id;
        $stock = isset($products[$productId]) ? $products[$productId]['stock'] : 0;
        $cartItems[] = [
            'id' => (string)$item->id,
            'user_id' => (int)$item->user_id,
            'product_id' => $productId,
            'product_name' => (string)$item->product_name,
            'image' => (string)$item->image,
            'price' => (float)$item->price,
            'color' => (string)$item->color,
            'size' => (string)$item->size,
            'quantity' => (int)$item->quantity,
            'timestamp' => (string)$item->timestamp,
            'stock' => $stock
        ];
    }
}

// Filter cart items for current user
$userCartItems = array_filter($cartItems, function ($item) use ($currentUser) {
    return $item['user_id'] == $currentUser['user_id'];
});

// Calculate cart total
$cartTotal = 0;
foreach ($userCartItems as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $selectedItems = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];

        if ($action === 'delete' && !empty($selectedItems)) {
            $xml = loadXML($xmlFile);
            foreach ($xml->item as $item) {
                if (in_array((string)$item->id, $selectedItems)) {
                    $dom = dom_import_simplexml($item);
                    $dom->parentNode->removeChild($dom);
                }
            }
            $xml->asXML($xmlFile);
            header("Location: cart.php?message=Items removed successfully");
            exit;
        }

        if ($action === 'update' && !empty($selectedItems)) {
            $xml = loadXML($xmlFile);
            foreach ($xml->item as $item) {
                if (in_array((string)$item->id, $selectedItems)) {
                    $itemId = (string)$item->id;
                    if (isset($_POST['quantity'][$itemId]) && is_numeric($_POST['quantity'][$itemId]) && (int)$_POST['quantity'][$itemId] > 0) {
                        $quantity = (int)$_POST['quantity'][$itemId];
                        $productId = (int)$item->product_id;
                        $stock = isset($products[$productId]) ? $products[$productId]['stock'] : 0;
                        if ($quantity > $stock) {
                            header("Location: cart.php?error=Requested quantity for " . htmlspecialchars((string)$item->product_name) . " exceeds available stock of $stock");
                            exit;
                        }
                        $item->quantity = $quantity;
                    }
                }
            }
            $xml->asXML($xmlFile);
            header("Location: cart.php?message=Cart updated successfully");
            exit;
        }

        if ($action === 'checkout' && !empty($selectedItems)) {
            $selectedCartItems = array_filter($cartItems, function ($item) use ($selectedItems) {
                return in_array($item['id'], $selectedItems);
            });

            // Validate stock for checkout
            foreach ($selectedCartItems as $item) {
                $productId = $item['product_id'];
                $stock = isset($products[$productId]) ? $products[$productId]['stock'] : 0;
                if ($item['quantity'] > $stock) {
                    header("Location: cart.php?error=Requested quantity for " . htmlspecialchars($item['product_name']) . " exceeds available stock of $stock");
                    exit;
                }
            }

            $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;
            if (empty($paymentMethod)) {
                error_log("Checkout error: No payment method selected");
                header("Location: cart.php?error=Please select a payment method");
                exit;
            }

            // Validate QR code existence for PayPal, GCash, PayMaya
            if (in_array($paymentMethod, ['PayPal', 'GCash', 'PayMaya'])) {
                $qrPath = __DIR__ . '/../../assets/image/qr/' . strtolower($paymentMethod) . '.png';
                if (!file_exists($qrPath)) {
                    error_log("QR code not found for $paymentMethod");
                    header("Location: cart.php?error=QR code for $paymentMethod is unavailable");
                    exit;
                }
            }

            $paymentProof = null;
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                error_log("File upload attempt: type=" . $_FILES['payment_proof']['type'] . ", size=" . $_FILES['payment_proof']['size']);
                if (!in_array($_FILES['payment_proof']['type'], $allowedTypes)) {
                    error_log("Checkout error: Invalid file type for payment proof");
                    header("Location: cart.php?error=Invalid file type. Only JPG, PNG, and GIF are allowed");
                    exit;
                }
                if ($_FILES['payment_proof']['size'] > $maxFileSize) {
                    error_log("Checkout error: File size exceeds 5MB limit");
                    header("Location: cart.php?error=File size exceeds 5MB limit");
                    exit;
                }

                $uploadDir = __DIR__ . '/../../assets/image/payment_proof/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileExt = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $paymentProof = 'proof_' . uniqid() . '.' . $fileExt;
                $uploadPath = $uploadDir . $paymentProof;
                if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadPath)) {
                    error_log("Checkout error: Failed to move uploaded file to $uploadPath");
                    header("Location: cart.php?error=Failed to upload payment proof");
                    exit;
                }
                $paymentProof = '/labjewels/assets/image/payment_proof/' . $paymentProof;
                error_log("File uploaded successfully: $paymentProof");
            } elseif (in_array($paymentMethod, ['PayPal', 'GCash', 'PayMaya'])) {
                error_log("Checkout error: No payment proof uploaded for $paymentMethod");
                header("Location: cart.php?error=Please upload payment proof for $paymentMethod");
                exit;
            }

            // Calculate shipping fee
            $subtotal = array_sum(array_map(function ($item) {
                return $item['price'] * $item['quantity'];
            }, $selectedCartItems));
            $shippingFee = $subtotal > 3000 ? 0 : $subtotal * 0.05;

            $orderId = saveOrderToXML($ordersXmlFile, $currentUser['user_id'], $selectedCartItems, $paymentMethod, $paymentProof, $shippingFee);

            $xml = loadXML($xmlFile);
            foreach ($xml->item as $item) {
                if (in_array((string)$item->id, $selectedItems)) {
                    $dom = dom_import_simplexml($item);
                    $dom->parentNode->removeChild($dom);
                }
            }
            $xml->asXML($xmlFile);
            error_log("Order placed successfully: $orderId");
            header("Location: orders.php?order=success");
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
    <title>LAB Jewels - Shopping Cart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8b5cf6;
            --primary-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --primary-hover: #4f46e5;
            --secondary-color: #f43f5e;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #1f2937;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #f1f5f9;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --blackfont-color: #1a1a1a;
            --whitefont-color: #f9fafb;
            --grayfont-color: #1e1e1e;
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

        .button {
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
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .button:hover {
            box-shadow: var(--shadow-lg);
        }

        .button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .button:hover::before {
            left: 100%;
        }

        .button.secondary {
            background: var(--card-bg);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .button.secondary:hover {
            background: var(--inputfield-color);
            box-shadow: var(--shadow-md);
        }

        .button.danger {
            border: 2px solid var(--danger-color);
            color: var(--danger-color);
        }

        .button.danger:hover {
            background: var(--inputfield-color);
            box-shadow: var(--shadow-md);
        }

        .button.success {
            background: var(--primary-gradient);
        }

        .button.success:hover {
            background: var(--primary-hover);
        }

        .button .fa-shopping-bag {
            font-size: 1.2em;
            transform: scale(1);
            transition: var(--transition);
        }

        .button:hover .fa-shopping-bag {
            transform: scale(1.2) rotate(5deg);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .cart-section h2 {
            font-size: 36px;
            font-weight: 800;
            color: var(--blackfont-color);
        }

        .cart-summary {
            background: var(--blackfont-color);
            color: var(--whitefont-color);
            padding: 20px 30px;
            border-radius: 12px;
            text-align: center;
            min-width: 250px;
        }

        .cart-summary h3 {
            font-size: 18px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .cart-total {
            font-size: 28px;
            font-weight: 700;
        }

        .cart-count {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
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

        .table-wrapper {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-top: 2rem;
            overflow: hidden;
        }

        .selection-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: var(--division-color);
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .selection-buttons {
            display: flex;
            gap: 10px;
        }

        .selection-info {
            font-weight: 600;
            color: var(--blackfont-color);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            border: none;
            box-shadow: var(--shadow-md);
        }

        .cart-table th {
            background-color: var(--blackfont-color);
            color: var(--whitefont-color);
            font-weight: 600;
            padding: 1rem;
            text-align: center;
            font-size: 0.9rem;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .cart-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
            text-align: center;
            color: var(--blackfont-color);
            background-color: var(--card-bg);
        }

        .cart-table th,
        .cart-table td {
            border-left: none;
            border-right: none;
            border-top: none;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .cart-table tr:hover td {
            background-color: var(--inputfield-color);
        }

        .cart-table tr.selected td {
            background-color: #ecfdf5;
        }

        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: var(--shadow-sm);
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .quantity-btn {
            background-color: var(--inputfield-color);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background-color: var(--primary-color);
            color: var(--whitefont-color);
        }

        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            background-color: var(--inputfield-color);
        }

        .quantity-input:hover,
        .quantity-input:focus {
            background-color: var(--inputfieldhover-color);
        }

        .item-total {
            font-weight: 700;
            color: var(--success-color);
            font-size: 16px;
        }

        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
            padding: 20px;
            background-color: var(--division-color);
            border-radius: 8px;
            flex-wrap: wrap;
        }

        .action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .selected-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--blackfont-color);
            padding: 10px 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px solid var(--primary-color);
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
            color: var(--blackfont-color);
        }

        .modal-content .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background-color: var(--division-color);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .modal-content .order-item p {
            margin: 0;
            font-size: 16px;
            color: var(--blackfont-color);
        }

        .modal-content .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px;
            font-size: 16px;
            color: var(--blackfont-color);
        }

        .modal-content .total {
            font-size: 20px;
            font-weight: 700;
            text-align: right;
            margin-top: 20px;
            padding: 15px;
            background: var(--primary-color);
            color: var(--whitefont-color);
            border-radius: 8px;
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
            color: var(--grayfont-color);
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        input[type="checkbox"][name="selected_items[]"] {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--grayfont-color);
            border-radius: 4px;
            outline: none;
            cursor: pointer;
            position: relative;
            background-color: var(--card-bg);
            vertical-align: middle;
            transition: var(--transition);
        }

        input[type="checkbox"][name="selected_items[]"]:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        input[type="checkbox"][name="selected_items[]"]:checked::after {
            content: "";
            position: absolute;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid var(--whitefont-color);
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            display: block;
        }

        input[type="checkbox"][name="selected_items[]"]:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border-radius: 12px;
            margin: 2rem auto;
            max-width: 600px;
            transition: var(--transition);
        }

        .empty-state-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .empty-state-icon i {
            font-size: 2.5rem;
            color: var(--whitefont-color);
            transition: var(--transition);
        }

        .empty-state:hover .empty-state-icon {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .empty-state h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--blackfont-color);
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: var(--grayfont-color);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .empty-state .button {
            background: var(--primary-gradient);
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
        }

        .empty-state .button:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .empty-state .button i {
            font-size: 1.3rem;
            transition: var(--transition);
        }

        .empty-state .button:hover i {
            transform: scale(1.2) rotate(5deg);
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
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

        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-option input[type="radio"] {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--grayfont-color);
            border-radius: 50%;
            outline: none;
            cursor: pointer;
            position: relative;
            background-color: var(--card-bg);
            transition: var(--transition);
        }

        .payment-option input[type="radio"]:checked {
            border-color: var(--primary-color);
            background-color: var(--primary-color);
        }

        .payment-option input[type="radio"]:checked::after {
            content: "";
            position: absolute;
            top: 4px;
            left: 4px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--whitefont-color);
            display: block;
        }

        .payment-option:hover {
            border-color: var(--primary-color);
            background-color: var(--inputfield-color);
        }

        .payment-details {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background-color: var(--division-color);
            border-radius: 8px;
        }

        .payment-details.active {
            display: block;
        }

        .payment-details .qr-code-container {
            text-align: center;
        }

        .payment-details img.qr-code {
            width: 150px;
            height: 150px;
            display: block;
            margin: 0 auto 15px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .payment-details input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: var(--inputfield-color);
        }

        .payment-details input[type="file"]:hover,
        .payment-details input[type="file"]:focus {
            background-color: var(--inputfieldhover-color);
            border-color: var(--primary-color);
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            display: none;
        }

        .preview-image.visible {
            display: block;
        }

        .error-message {
            color: var(--danger-color);
            font-size: 14px;
            margin-top: 5px;
            display: none;
            text-align: center;
        }

        .error-message.visible {
            display: block;
        }

        .file-status {
            font-size: 14px;
            margin-top: 5px;
            color: var(--success-color);
            display: none;
            text-align: center;
        }

        .file-status.visible {
            display: block;
        }

        .stock-info {
            font-size: 14px;
            color: var(--grayfont-color);
            margin-top: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .cart-header {
                flex-direction: column;
                align-items: stretch;
            }

            .cart-summary {
                min-width: auto;
            }

            .table-wrapper {
                padding: 1rem;
                overflow-x: auto;
            }

            .cart-table th,
            .cart-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .cart-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .action-group {
                justify-content: center;
            }

            .selection-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .selection-buttons {
                justify-content: center;
            }

            .payment-details img.qr-code {
                width: 120px;
                height: 120px;
            }

            .empty-state {
                padding: 3rem 1.5rem;
                margin: 1.5rem 1rem;
            }

            .empty-state-icon {
                width: 60px;
                height: 60px;
            }

            .empty-state-icon i {
                font-size: 2rem;
            }

            .empty-state h3 {
                font-size: 1.5rem;
            }

            .empty-state p {
                font-size: 1rem;
            }

            .empty-state .button {
                padding: 12px 24px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .empty-state {
                padding: 2rem 1rem;
                margin: 1rem 0.5rem;
            }

            .empty-state-icon {
                width: 50px;
                height: 50px;
            }

            .empty-state-icon i {
                font-size: 1.8rem;
            }

            .empty-state h3 {
                font-size: 1.3rem;
            }

            .empty-state p {
                font-size: 0.9rem;
            }

            .empty-state .button {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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
    <div class="cart-section container">
        <div class="navigation">
            <a href="orders.php" class="nav-link">
                <i class="fas fa-history"></i>
                View My Orders
            </a>
        </div>

        <div class="cart-header">
            <h2>Shopping Cart</h2>
            <div class="cart-summary">
                <h3>Cart Summary</h3>
                <div class="cart-total">₱ <?php echo number_format($cartTotal, 2); ?></div>
                <div class="cart-count"><?php echo count($userCartItems); ?> item(s)</div>
            </div>
        </div>

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

        <div class="table-wrapper">
            <form id="cartForm" method="POST" enctype="multipart/form-data">
                <?php if (empty($userCartItems)): ?>
                    <div class="empty-state fade-in">
                        <div class="empty-state-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>Your Cart is Empty</h3>
                        <p>Explore our collection and add some stunning jewelry to your cart!</p>
                        <a href="shop.php" class="button success">
                            <i class="fas fa-shopping-bag"></i>
                            Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="selection-controls">
                        <div class="selection-info">
                            <span id="selectedCount">0</span> item(s) selected
                        </div>
                        <div class="selection-buttons">
                            <button type="button" id="selectAllBtn" class="button secondary">
                                <i class="fas fa-check-square"></i>
                                Select All
                            </button>
                            <button type="button" id="clearSelectionBtn" class="button secondary">
                                <i class="fas fa-square"></i>
                                Clear Selection
                            </button>
                        </div>
                    </div>

                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userCartItems as $item): ?>
                                <tr data-item-id="<?php echo htmlspecialchars($item['id']); ?>">
                                    <td>
                                        <input type="checkbox"
                                            name="selected_items[]"
                                            value="<?php echo htmlspecialchars($item['id']); ?>"
                                            class="item-checkbox"
                                            data-price="<?php echo $item['price']; ?>"
                                            data-quantity="<?php echo $item['quantity']; ?>"
                                            data-stock="<?php echo $item['stock']; ?>">
                                    </td>
                                    <td>
                                        <img class="product-image"
                                            src="<?php echo htmlspecialchars($item['image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    </td>
                                    <td>₱ <?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['color']); ?></td>
                                    <td><?php echo htmlspecialchars($item['size']); ?></td>
                                    <td>
                                        <div class="quantity-controls">
                                            <button type="button" class="quantity-btn" onclick="changeQuantity('<?php echo $item['id']; ?>', -1, <?php echo $item['stock']; ?>)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number"
                                                name="quantity[<?php echo htmlspecialchars($item['id']); ?>]"
                                                value="<?php echo htmlspecialchars($item['quantity']); ?>"
                                                min="1"
                                                max="<?php echo $item['stock']; ?>"
                                                class="quantity-input"
                                                id="qty-<?php echo $item['id']; ?>"
                                                onchange="updateItemTotal('<?php echo $item['id']; ?>', <?php echo $item['price']; ?>, <?php echo $item['stock']; ?>)">
                                            <button type="button" class="quantity-btn" onclick="changeQuantity('<?php echo $item['id']; ?>', 1, <?php echo $item['stock']; ?>)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="stock-info">Available Stock: <?php echo $item['stock']; ?></div>
                                    </td>
                                    <td>
                                        <span class="item-total" id="total-<?php echo $item['id']; ?>">
                                            ₱ <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="cart-actions">
                        <div class="selected-total">
                            Selected Total: <span id="selectedTotal">₱ 0.00</span>
                        </div>
                        <div class="action-group">
                            <button type="submit" name="action" value="update" class="button secondary" id="updateBtn" disabled>
                                <i class="fas fa-sync-alt"></i>
                                Update Cart
                            </button>
                            <button type="submit" name="action" value="delete" class="button danger" id="deleteBtn" disabled>
                                <i class="fas fa-trash"></i>
                                Remove Selected
                            </button>
                            <button type="button" id="checkoutBtn" class="button success" disabled>
                                <i class="fas fa-credit-card"></i>
                                Checkout
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h2><i class="fas fa-receipt"></i> Checkout</h2>
            <div id="orderSummary"></div>
            <h3 style="margin: 20px 0 10px; font-size: 20px; font-weight: 600;">Select Payment Method</h3>
            <div class="payment-options">
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="PayPal">
                    <span>PayPal</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="GCash">
                    <span>GCash</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="PayMaya">
                    <span>PayMaya</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="Cash On Delivery">
                    <span>Cash On Delivery</span>
                </label>
            </div>
            <div class="payment-details" id="paymentDetails">
                <div class="qr-code-container">
                    <img class="qr-code" id="qrCode" src="" alt="QR Code for Payment">
                    <p id="qrCodeLabel" style="text-align: center; font-size: 14px; color: var(--blackfont-color); margin-bottom: 15px;"></p>
                    <p style="text-align: center; font-size: 14px; color: var(--grayfont-color);">Scan this QR code using your payment app to complete the payment.</p>
                </div>
                <input type="file" id="paymentProof" name="payment_proof" accept="image/*">
                <div class="error-message" id="fileError">Invalid file. Please upload a JPG, PNG, or GIF image under 5MB.</div>
                <div class="file-status" id="fileStatus">File selected successfully.</div>
                <img class="preview-image" id="previewImage" src="" alt="Payment Proof Preview">
            </div>
            <button id="placeOrderBtn" class="button success" disabled>
                <i class="fas fa-check"></i>
                Place Order
            </button>
        </div>
    </div>

    <script>
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const selectedCountSpan = document.getElementById('selectedCount');
        const selectedTotalSpan = document.getElementById('selectedTotal');
        const updateBtn = document.getElementById('updateBtn');
        const deleteBtn = document.getElementById('deleteBtn');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const paymentModal = document.getElementById('paymentModal');
        const closeModals = document.querySelectorAll('.close-modal');
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        const orderSummary = document.getElementById('orderSummary');
        const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
        const paymentDetails = document.getElementById('paymentDetails');
        const qrCode = document.getElementById('qrCode');
        const qrCodeLabel = document.getElementById('qrCodeLabel');
        const paymentProofInput = document.getElementById('paymentProof');
        const previewImage = document.getElementById('previewImage');
        const fileError = document.getElementById('fileError');
        const fileStatus = document.getElementById('fileStatus');

        function updateSelectionInfo() {
            const selectedCheckboxes = Array.from(checkboxes).filter(cb => cb.checked);
            const selectedCount = selectedCheckboxes.length;

            selectedCountSpan.textContent = selectedCount;

            let selectedTotal = 0;
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const itemId = checkbox.value;
                const quantityInput = document.getElementById('qty-' + itemId);
                const price = parseFloat(checkbox.dataset.price);
                const quantity = parseInt(quantityInput.value);
                selectedTotal += price * quantity;

                row.classList.add('selected');
            });

            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.closest('tr').classList.remove('selected');
                }
            });

            selectedTotalSpan.textContent = '₱ ' + selectedTotal.toFixed(2);

            const hasSelection = selectedCount > 0;
            updateBtn.disabled = !hasSelection;
            deleteBtn.disabled = !hasSelection;
            checkoutBtn.disabled = !hasSelection;

            [updateBtn, deleteBtn, checkoutBtn].forEach(btn => {
                btn.style.opacity = btn.disabled ? '0.5' : '1';
                btn.style.cursor = btn.disabled ? 'not-allowed' : 'pointer';
            });
        }

        function changeQuantity(itemId, change, maxStock) {
            const quantityInput = document.getElementById('qty-' + itemId);
            let currentQuantity = parseInt(quantityInput.value);
            let newQuantity = currentQuantity + change;

            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > maxStock) {
                newQuantity = maxStock;
                alert(`Cannot select more than ${maxStock} items. Available stock is ${maxStock}.`);
            }

            quantityInput.value = newQuantity;

            const checkbox = document.querySelector(`input[value="${itemId}"]`);
            const price = parseFloat(checkbox.dataset.price);
            updateItemTotal(itemId, price, maxStock);

            if (checkbox.checked) {
                updateSelectionInfo();
            }
        }

        function updateItemTotal(itemId, price, maxStock) {
            const quantityInput = document.getElementById('qty-' + itemId);
            const totalSpan = document.getElementById('total-' + itemId);
            let quantity = parseInt(quantityInput.value);

            if (quantity > maxStock) {
                quantity = maxStock;
                quantityInput.value = maxStock;
                alert(`Cannot select more than ${maxStock} items. Available stock is ${maxStock}.`);
            } else if (quantity < 1 || isNaN(quantity)) {
                quantity = 1;
                quantityInput.value = 1;
            }

            const total = price * quantity;
            totalSpan.textContent = '₱ ' + total.toFixed(2);

            const checkbox = document.querySelector(`input[value="${itemId}"]`);
            checkbox.dataset.quantity = quantity;
        }

        // Prevent mouse wheel scrolling
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('wheel', (e) => {
                e.preventDefault();
            });
        });

        let selectedItems = [];

        checkoutBtn.addEventListener('click', () => {
            selectedItems = Array.from(checkboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => {
                    const row = checkbox.closest('tr');
                    const itemId = checkbox.value;
                    const quantityInput = document.getElementById('qty-' + itemId);
                    const stock = parseInt(checkbox.dataset.stock);
                    const quantity = parseInt(quantityInput.value);
                    if (quantity > stock) {
                        alert(`Requested quantity for ${row.cells[2].textContent.trim()} exceeds available stock of ${stock}.`);
                        return null;
                    }
                    return {
                        id: itemId,
                        name: row.cells[2].textContent.trim(),
                        price: parseFloat(checkbox.dataset.price),
                        quantity: quantity,
                        color: row.cells[4].textContent,
                        size: row.cells[5].textContent
                    };
                })
                .filter(item => item !== null);

            if (selectedItems.length === 0) {
                alert('Please select at least one item to checkout.');
                return;
            }

            let subtotal = 0;
            orderSummary.innerHTML = selectedItems.map(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                return `
                    <div class="order-item">
                        <div>
                            <p><strong>${item.name}</strong></p>
                            <p style="font-size: 14px; color: var(--grayfont-color);">
                                ${item.color} | ${item.size} | Qty: ${item.quantity}
                            </p>
                        </div>
                        <p><strong>₱ ${itemTotal.toFixed(2)}</strong></p>
                    </div>
                `;
            }).join('');

            const shippingFee = subtotal > 3000 ? 0 : subtotal * 0.03;
            const total = subtotal + shippingFee;

            orderSummary.innerHTML += `
                <div class="summary-row">
                    <div>Subtotal</div>
                    <div>₱ ${subtotal.toFixed(2)}</div>
                </div>
                <div class="summary-row">
                    <div>Shipping Fee <strong>(3%)</strong></div>
                    <div>₱ ${shippingFee.toFixed(2)}</div>
                </div>
                <div class="total">Total: ₱ ${total.toFixed(2)}</div>
            `;

            paymentModal.style.display = 'flex';
            paymentOptions.forEach(option => option.checked = false);
            paymentDetails.classList.remove('active');
            placeOrderBtn.disabled = true;
            placeOrderBtn.style.opacity = '0.5';
            placeOrderBtn.style.cursor = 'not-allowed';
            qrCode.src = '';
            qrCodeLabel.textContent = '';
            previewImage.src = '';
            previewImage.classList.remove('visible');
            fileError.classList.remove('visible');
            fileStatus.classList.remove('visible');
        });

        paymentOptions.forEach(option => {
            option.addEventListener('change', () => {
                paymentDetails.classList.remove('active');
                qrCode.src = '';
                qrCodeLabel.textContent = '';
                fileError.classList.remove('visible');
                paymentProofInput.removeAttribute('required');
                placeOrderBtn.disabled = true;
                placeOrderBtn.style.opacity = '0.5';
                placeOrderBtn.style.cursor = 'not-allowed';

                if (option.checked) {
                    if (option.value === 'Cash On Delivery') {
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.style.opacity = '1';
                        placeOrderBtn.style.cursor = 'pointer';
                    } else if (['PayPal', 'GCash', 'PayMaya'].includes(option.value)) {
                        paymentDetails.classList.add('active');
                        qrCode.src = `../../assets/image/qr/${option.value.toLowerCase()}.PNG`;
                        qrCode.alt = `${option.value} QR Code for Payment`;
                        qrCodeLabel.textContent = `Scan to pay with ${option.value}`;
                        paymentProofInput.setAttribute('required', 'required');
                        placeOrderBtn.disabled = !paymentProofInput.files.length;
                        placeOrderBtn.style.opacity = paymentProofInput.files.length ? '1' : '0.5';
                        placeOrderBtn.style.cursor = paymentProofInput.files.length ? 'pointer' : 'not-allowed';
                        fileStatus.classList.toggle('visible', paymentProofInput.files.length > 0);
                        if (paymentProofInput.files.length) {
                            fileStatus.textContent = `File selected: ${paymentProofInput.files[0].name}`;
                        }
                    }
                }
            });
        });

        qrCode.onerror = function() {
            qrCode.src = '../../assets/image/qr/qr-demo.png';
            qrCode.alt = 'QR Code Unavailable';
            qrCodeLabel.textContent = 'QR code unavailable. Please contact support.';
            fileError.textContent = 'QR code image not found. Please try another payment method.';
            fileError.classList.add('visible');
        };

        paymentProofInput.addEventListener('change', () => {
            const file = paymentProofInput.files[0];
            fileError.classList.remove('visible');
            fileStatus.classList.remove('visible');

            if (file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const maxFileSize = 5 * 1024 * 1024; // 5MB
                if (!allowedTypes.includes(file.type)) {
                    fileError.textContent = 'Invalid file type. Please upload a JPG, PNG, or GIF image.';
                    fileError.classList.add('visible');
                    paymentProofInput.value = '';
                    previewImage.src = '';
                    previewImage.classList.remove('visible');
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.style.opacity = '0.5';
                    placeOrderBtn.style.cursor = 'not-allowed';
                    return;
                }
                if (file.size > maxFileSize) {
                    fileError.textContent = 'File size exceeds 5MB limit.';
                    fileError.classList.add('visible');
                    paymentProofInput.value = '';
                    previewImage.src = '';
                    previewImage.classList.remove('visible');
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.style.opacity = '0.5';
                    placeOrderBtn.style.cursor = 'not-allowed';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImage.classList.add('visible');
                    fileStatus.textContent = `File selected: ${file.name}`;
                    fileStatus.classList.add('visible');
                    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
                    if (selectedPayment && ['PayPal', 'GCash', 'PayMaya'].includes(selectedPayment.value)) {
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.style.opacity = '1';
                        placeOrderBtn.style.cursor = 'pointer';
                    }
                };
                reader.onerror = function() {
                    fileError.textContent = 'Error reading file. Please try again.';
                    fileError.classList.add('visible');
                    paymentProofInput.value = '';
                    previewImage.src = '';
                    previewImage.classList.remove('visible');
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.style.opacity = '0.5';
                    placeOrderBtn.style.cursor = 'not-allowed';
                };
                reader.readAsDataURL(file);
            } else {
                previewImage.src = '';
                previewImage.classList.remove('visible');
                fileStatus.classList.remove('visible');
                const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
                if (selectedPayment && ['PayPal', 'GCash', 'PayMaya'].includes(selectedPayment.value)) {
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.style.opacity = '0.5';
                    placeOrderBtn.style.cursor = 'not-allowed';
                }
            }
        });

        placeOrderBtn.addEventListener('click', () => {
            const form = document.getElementById('cartForm');
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');

            if (!selectedPayment) {
                alert('Please select a payment method.');
                return;
            }

            if (['PayPal', 'GCash', 'PayMaya'].includes(selectedPayment.value) && !paymentProofInput.files.length) {
                alert(`Please upload payment proof for ${selectedPayment.value}.`);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'checkout');
            formData.append('payment_method', selectedPayment.value);
            selectedItems.forEach(item => formData.append('selected_items[]', item.id));
            if (paymentProofInput.files.length) {
                formData.append('payment_proof', paymentProofInput.files[0]);
            }

            const existingInputs = form.querySelectorAll('input[type="hidden"][name="action"], input[type="hidden"][name="payment_method"], input[type="hidden"][name="selected_items[]"]');
            existingInputs.forEach(input => input.remove());

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'checkout';
            form.appendChild(actionInput);

            const paymentMethodInput = document.createElement('input');
            paymentMethodInput.type = 'hidden';
            paymentMethodInput.name = 'payment_method';
            paymentMethodInput.value = selectedPayment.value;
            form.appendChild(paymentMethodInput);

            selectedItems.forEach(item => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = item.id;
                form.appendChild(input);
            });

            const originalParent = paymentProofInput.parentNode;
            form.appendChild(paymentProofInput);

            form.submit();

            originalParent.appendChild(paymentProofInput);
        });

        closeModals.forEach(close => {
            close.addEventListener('click', () => {
                paymentModal.style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target === paymentModal) {
                paymentModal.style.display = 'none';
            }
        });

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionInfo);
        });

        selectAllBtn.addEventListener('click', () => {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectionInfo();
        });

        clearSelectionBtn.addEventListener('click', () => {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectionInfo();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.cart-table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Initialize stock validation
            document.querySelectorAll('.quantity-input').forEach(input => {
                const itemId = input.id.replace('qty-', '');
                const checkbox = document.querySelector(`input[value="${itemId}"]`);
                const stock = parseInt(checkbox.dataset.stock);
                input.setAttribute('max', stock);

                input.addEventListener('input', () => {
                    let value = parseInt(input.value);
                    if (value > stock) {
                        input.value = stock;
                        alert(`Cannot select more than ${stock} items. Available stock is ${stock}.`);
                    } else if (value < 1 || isNaN(value)) {
                        input.value = 1;
                    }
                    updateItemTotal(itemId, parseFloat(checkbox.dataset.price), stock);
                    if (checkbox.checked) {
                        updateSelectionInfo();
                    }
                });
            });
        });
    </script>
</body>
</html>