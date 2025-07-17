<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'default-icon.png'; // Fallback to default icon

// $xmlFile = __DIR__ . '/../../xml/products.xml';
$xmlFile = '../../xml/products.xml';

function loadXML($file)
{
    if (!file_exists($file)) {
        $xmlFile = 'xml/products.xml';
        die('XML file does not exist.' . $xmlFile);
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

function saveXML($xml, $file)
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($file);
}

function createProduct($xml, $data)
{
    $products = $xml->products;
    $product = $products->addChild('product');

    // Generate new ID
    $lastId = 0;
    foreach ($xml->products->product as $p) {
        $id = (int)$p->id;
        if ($id > $lastId) $lastId = $id;
    }

    $product->addChild('id', $lastId + 1);
    $product->addChild('name', htmlspecialchars($data['name']));
    $product->addChild('category', htmlspecialchars($data['category']));
    $product->addChild('price', (float)$data['price']);
    $product->addChild('currency', 'PHP');
    $product->addChild('description', htmlspecialchars($data['description']));
    $product->addChild('image', htmlspecialchars($data['image']));
    $product->addChild('stock', (int)$data['stock']);
    
    // Add sizes
    $sizes = $product->addChild('sizes');
    if (!empty($data['sizes']) && is_array($data['sizes'])) {
        foreach ($data['sizes'] as $size) {
            $sizes->addChild('size', htmlspecialchars($size));
        }
    }
    
    // Add colors
    $colors = $product->addChild('colors');
    if (!empty($data['colors']) && is_array($data['colors'])) {
        foreach ($data['colors'] as $color) {
            $colors->addChild('color', htmlspecialchars($color));
        }
    }
    
    $product->addChild('rating', 0);
    $product->addChild('review_count', 0);
    $product->addChild('featured', htmlspecialchars($data['featured']));
    $product->addChild('on_sale', htmlspecialchars($data['on_sale']));

    return saveXML($xml, $GLOBALS['xmlFile']);
}

function updateProduct($xml, $id, $data)
{
    foreach ($xml->products->product as $product) {
        if ((int)$product->id == $id) {
            $product->name = htmlspecialchars($data['name']);
            $product->category = htmlspecialchars($data['category']);
            $product->price = (float)$data['price'];
            $product->description = htmlspecialchars($data['description']);
            $product->image = htmlspecialchars($data['image']);
            $product->stock = (int)$data['stock'];
            
            // Update sizes
            unset($product->sizes); // Remove existing sizes
            $sizes = $product->addChild('sizes');
            if (!empty($data['sizes']) && is_array($data['sizes'])) {
                foreach ($data['sizes'] as $size) {
                    $sizes->addChild('size', htmlspecialchars($size));
                }
            }
            
            // Update colors
            unset($product->colors); // Remove existing colors
            $colors = $product->addChild('colors');
            if (!empty($data['colors']) && is_array($data['colors'])) {
                foreach ($data['colors'] as $color) {
                    $colors->addChild('color', htmlspecialchars($color));
                }
            }
            
            $product->rating = 0;
            $product->review_count = 0;
            $product->featured = htmlspecialchars($data['featured']);
            $product->on_sale = htmlspecialchars($data['on_sale']);

            return saveXML($xml, $GLOBALS['xmlFile']);
        }
    }
    return false;
}

function deleteProduct($xml, $id, $password)
{
    $pdo = getDBConnection();

    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([1]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return false;
        }

        if (!password_verify($password, $admin['password'])) {
            return false;
        }

        $products = $xml->products;
        $index = 0;
        foreach ($products->product as $product) {
            if ((int)$product->id == $id) {
                unset($products->product[$index]);
                return saveXML($xml, $GLOBALS['xmlFile']);
            }
            $index++;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        return false;
    }
}

// function uploadImage($file)
// {
//     $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '../assets/image/products/';

//     if (!file_exists($uploadDir) || !is_dir($uploadDir)) {
//         return ['success' => false, 'error' => 'Products directory does not exist'];
//     }

//     $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
//     if (!in_array($file['type'], $allowedTypes)) {
//         return ['success' => false, 'error' => 'Invalid file type'];
//     }

//     if ($file['size'] > 5 * 1024 * 1024) {
//         return ['success' => false, 'error' => 'File size exceeds 5MB'];
//     }

//     $fileName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', basename($file['name']));
//     $uploadPath = $uploadDir . $fileName;

//     if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
//         return ['success' => true, 'path' => '../assets/image/products/' . $fileName];
//     }

//     return ['success' => false, 'error' => 'Failed to upload file'];
// }

function uploadImage($file)
{
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/image/products/';

    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!is_dir($uploadDir)) {
        return ['success' => false, 'error' => 'Products directory is not a valid directory'];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size exceeds 5MB'];
    }

    $fileName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', basename($file['name']));
    $uploadPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'path' => '/assets/image/products/' . $fileName];
    }

    return ['success' => false, 'error' => 'Failed to upload file'];
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xml = loadXML($xmlFile);

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $imagePath = '';
                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
                    $uploadResult = uploadImage($_FILES['image_file']);
                    if ($uploadResult['success']) {
                        $imagePath = $uploadResult['path'];
                    } else {
                        $_SESSION['error'] = 'Failed to upload image: ' . $uploadResult['error'];
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } elseif (!empty($_POST['image'])) {
                    $imagePath = $_POST['image'];
                }

                $data = [
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'price' => $_POST['price'],
                    'description' => $_POST['description'],
                    'image' => $imagePath,
                    'stock' => $_POST['stock'],
                    'sizes' => $_POST['sizes'] ?? [],
                    'colors' => $_POST['colors'] ?? [],
                    'featured' => $_POST['featured'] ?? 'false',
                    'on_sale' => $_POST['on_sale'] ?? 'false'
                ];

                if (createProduct($xml, $data)) {
                    $_SESSION['message'] = 'Product created successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to create product.';
                }
                break;

            case 'update':
                $imagePath = $_POST['current_image'] ?? '';
                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
                    $uploadResult = uploadImage($_FILES['image_file']);
                    if ($uploadResult['success']) {
                        if (!empty($imagePath) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/' . $imagePath)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/labjewels/' . $imagePath);
                        }
                        $imagePath = $uploadResult['path'];
                    } else {
                        $_SESSION['error'] = 'Failed to upload image: ' . $uploadResult['error'];
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } elseif (!empty($_POST['image'])) {
                    $imagePath = $_POST['image'];
                }

                $data = [
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'price' => $_POST['price'],
                    'description' => $_POST['description'],
                    'image' => $imagePath,
                    'stock' => $_POST['stock'],
                    'sizes' => $_POST['sizes'] ?? [],
                    'colors' => $_POST['colors'] ?? [],
                    'featured' => $_POST['featured'] ?? 'false',
                    'on_sale' => $_POST['on_sale'] ?? 'false'
                ];

                if (updateProduct($xml, $_POST['id'], $data)) {
                    $_SESSION['message'] = 'Product updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update product.';
                }
                break;

            case 'delete':
                if (deleteProduct($xml, $_POST['id'], $_POST['password'])) {
                    $_SESSION['message'] = 'Product deleted successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to delete product. Check password or product ID.';
                }
                break;
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Load XML for display
$xml = loadXML($xmlFile);

// Calculate dashboard metrics from XML
$totalProducts = count($xml->products->product);
$totalStock = 0;
$totalValue = 0;

if ($totalProducts > 0) {
    foreach ($xml->products->product as $product) {
        $stock = (int)$product->stock;
        $price = (float)$product->price;
        $totalStock += $stock;
        $totalValue += $price * $stock;
    }
}

// Calculate total customers from the database
$pdo = getDBConnection();
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as customer_count FROM users WHERE isActive = 1 AND isDeleted = 0");
    $stmt->execute();
    $totalCustomers = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching customer count: " . $e->getMessage());
    $totalCustomers = 0; // Fallback to 0 on error
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="product.css">
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

        .btn-secondary {
            background: var(--secondary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #ec4899, #f43f5e);
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

        .card-footer {
            display: flex;
            align-items: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .card-trend {
            display: flex;
            align-items: center;
            margin-right: 10px;
            font-weight: 600;
        }

        .card-trend.up {
            color: #10b981;
        }

        .card-trend.down {
            color: #ef4444;
        }

        .card-period {
            color: var(--grayfont-color);
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
        .product-table td:nth-child(1) {
            width: 5%;
        }

        /* ID column */
        .product-table th:nth-child(2),
        .product-table td:nth-child(2) {
            width: 10%;
        }

        /* Image column */
        .product-table th:nth-child(3),
        .product-table td:nth-child(3) {
            width: 20%;
        }

        /* Name column */
        .product-table th:nth-child(4),
        .product-table td:nth-child(4) {
            width: 15%;
        }

        /* Category column */
        .product-table th:nth-child(5),
        .product-table td:nth-child(5) {
            width: 15%;
        }

        /* Price column */
        .product-table th:nth-child(6),
        .product-table td:nth-child(6) {
            width: 15%;
        }

        /* Stock column */
        .product-table th:nth-child(7),
        .product-table td:nth-child(7) {
            width: 20%;
        }

        /* Actions column */

        .action-cell {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }

        @media (max-width: 768px) {

            .product-table th,
            .product-table td {
                padding: 6px 8px;
            }

            .product-table img {
                max-width: 30px;
                max-height: 30px;
            }

            .action-cell {
                flex-direction: column;
            }

            .filter-section {
                flex-direction: column;
                padding: 12px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
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
            border-color: var(--primary-color);
            background-color: var(--inputfieldhover-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .image-upload {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 35px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background-color: rgba(99, 102, 241, 0.03);
        }

        .image-upload:hover {
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.06);
        }

        .image-upload i {
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 15px;
            opacity: 0.7;
        }

        .image-upload p {
            font-size: 15px;
            color: var(--grayfont-color);
            margin-bottom: 5px;
        }

        .image-upload small {
            font-size: 12px;
            color: var(--grayfont-color);
            opacity: 0.5;
        }

        .image-preview {
            margin-top: 20px;
            display: none;
            text-align: center;
            padding: 10px;
            background-color: var(--division-color);
            border-radius: 12px;
            position: relative;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 220px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background-color 0.3s;
        }

        .remove-image:hover {
            background-color: rgba(255, 0, 0, 0.7);
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

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: var(--blackfont-color);
        }

        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>Product Management</h1>
            <button class="btn btn-primary" onclick="openForm('create')">
                <i class="fas fa-plus"></i> Add New Product
            </button>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Products</div>
                        <div class="card-value"><?php echo number_format($totalProducts); ?></div>
                    </div>
                    <div class="card-icon bg-pink">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Value</div>
                        <div class="card-value">₱<?php echo number_format($totalValue, 2); ?></div>
                    </div>
                    <div class="card-icon bg-purple">₱</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Stock</div>
                        <div class="card-value"><?php echo number_format($totalStock); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchProduct" placeholder="Search products..." onkeyup="filterProducts()">
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
                <select id="stockFilter" onchange="filterProducts()">
                    <option value="">All Stock</option>
                    <option value="in-stock">In Stock</option>
                    <option value="low-stock">Low Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
            </div>
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

        <div class="products-container">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php
                    if (isset($xml->products->product) && count($xml->products->product) > 0) {
                        foreach ($xml->products->product as $product) {
                            $stockClass = '';
                            $stockValue = (int)$product->stock;
                            if ($stockValue == 0) $stockClass = 'out-of-stock';
                            elseif ($stockValue <= 5) $stockClass = 'low-stock';
                            else $stockClass = 'in-stock';

                            echo "<tr class='product-row' data-category='" . htmlspecialchars($product->category) . "' data-stock='{$stockClass}'>";
                            echo "<td>" . (int)$product->id . "</td>";

                            $imageSrc = !empty($product->image) ? htmlspecialchars($product->image) : 'https://via.placeholder.com/50';
                            echo "<td><img src='../{$imageSrc}' alt='" . htmlspecialchars($product->name) . "' style='max-width: 50px; max-height: 50px; object-fit: cover;'></td>";

                            echo "<td class='product-name'>" . htmlspecialchars($product->name) . "</td>";
                            echo "<td>" . htmlspecialchars($product->category) . "</td>";
                            echo "<td>₱" . number_format((float)$product->price, 2) . "</td>";
                            echo "<td><span class='stock-badge {$stockClass}'>" . $stockValue . " units</span></td>";
                            echo "<td class='action-cell'>";
                            echo "<button class='btn btn-primary' onclick=\"openForm('update', " . (int)$product->id . ")\"><i class='fas fa-edit'></i> Edit</button>";
                            echo "<button class='btn btn-secondary' onclick=\"openDeleteModal(" . (int)$product->id . ", '" . htmlspecialchars($product->name, ENT_QUOTES) . "')\"><i class='fas fa-trash'></i> Delete</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>No products found. Add your first product!</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- Add/Edit Product Modal -->
        <div class="modal-backdrop" id="productModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Add New Product</h3>
                    <button class="close-modal" onclick="closeForm()">×</button>
                </div>
                <div class="modal-body">
                    <form id="productForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="formAction">
                        <input type="hidden" name="id" id="productId">
                        <input type="hidden" name="current_image" id="currentImage">

                        <div class="form-group">
                            <label for="name">Product Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" onchange="updateSizeAndColorOptions()" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Sizes</label>
                            <div class="checkbox-group" id="sizeCheckboxes"></div>
                        </div>

                        <div class="form-group">
                            <label>Colors</label>
                            <div class="checkbox-group" id="colorCheckboxes"></div>
                        </div>

                        <div class="form-group">
                            <label for="price">Price (PHP)</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Product Image</label>
                            <div class="image-upload" id="imageUpload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Drag & drop image here or click to browse</p>
                                <small>JPG, PNG or GIF, Max size 5MB</small>
                            </div>
                            <div class="image-preview" id="imagePreview">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" class="remove-image" onclick="removeImage()">×</button>
                            </div>
                            <input type="file" name="image_file" id="imageFile" accept="image/*" style="display: none;">
                            <input type="hidden" name="image" id="imageUrl">
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" name="stock" id="stock" class="form-control" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="featured">Featured</label>
                            <select name="featured" id="featured" class="form-control" required>
                                <option value="true">True</option>
                                <option value="false">False</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="on_sale">On Sale</label>
                            <select name="on_sale" id="on_sale" class="form-control" required>
                                <option value="true">True</option>
                                <option value="false">False</option>
                            </select>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeForm()">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveProductBtn">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal-backdrop delete-modal" id="deleteModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Delete Product</h3>
                    <button class="close-modal" onclick="closeDeleteModal()">×</button>
                </div>
                <div class="modal-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ff6b6b; margin-bottom: 15px;"></i>
                        <p style="font-size: 18px; margin: 0;">Are you sure you want to delete</p>
                        <p style="font-size: 18px; font-weight: bold; color: #333; margin: 5px 0;"><span id="deleteProductName"></span>?</p>
                        <p style="color: #666; margin: 0;">This action cannot be undone.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteProductId">
                        <div class="form-group">
                            <label for="password">Admin Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const productModal = document.getElementById('productModal');
        const deleteModal = document.getElementById('deleteModal');
        const imageUpload = document.getElementById('imageUpload');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const imageFile = document.getElementById('imageFile');
        const imageUrl = document.getElementById('imageUrl');
        const currentImage = document.getElementById('currentImage');

        // Product data from PHP
        const products = <?php
                            $products = [];
                            if (isset($xml->products->product)) {
                                foreach ($xml->products->product as $p) {
                                    $sizes = [];
                                    if (isset($p->sizes->size)) {
                                        foreach ($p->sizes->size as $size) {
                                            $sizes[] = (string)$size;
                                        }
                                    }
                                    $colors = [];
                                    if (isset($p->colors->color)) {
                                        foreach ($p->colors->color as $color) {
                                            $colors[] = (string)$color;
                                        }
                                    }
                                    $products[] = [
                                        'id' => (int)$p->id,
                                        'name' => (string)$p->name,
                                        'category' => (string)$p->category,
                                        'price' => (float)$p->price,
                                        'description' => (string)$p->description,
                                        // 'image' => (string)$p->image,
                                        'image' => (string)$p->image ? '../' . (string)$p->image : '../../assets/image/products/no-image.png',
                                        'stock' => (int)$p->stock,
                                        'sizes' => $sizes,
                                        'colors' => $colors,
                                        'featured' => (string)$p->featured,
                                        'on_sale' => (string)$p->on_sale
                                    ];
                                }
                            }
                            echo json_encode($products);
                            ?>;

        // Category options for sizes and colors
        const categoryOptions = {
            'Necklace': {
                sizes: ['14in', '16in', '18in', '20in'],
                colors: ['Silver', 'Gold', 'Rose Gold', 'Black']
            },
            'Ring': {
                sizes: ['5', '6', '7', '8', '9'],
                colors: ['Silver', 'Gold', 'Rose Gold', 'White Gold']
            },
            'Bracelet': {
                sizes: ['6in', '7in', '8in', 'One Size'],
                colors: ['Rose Gold', 'Silver', 'Gold', 'Black', 'Brown']
            },
            'Earrings': {
                sizes: ['One Size'],
                colors: ['Silver', 'Gold', 'Rose Gold', 'White Gold']
            },
            'Anklet': {
                sizes: ['9in', '10in', '11in'],
                colors: ['Silver', 'Gold', 'Rose Gold', 'Multicolor']
            }
        };

        const itemsPerPage = 5;
        let currentPage = 1;

        function openForm(action, id = null) {
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Product' : 'Edit Product';
            document.getElementById('saveProductBtn').textContent = action === 'create' ? 'Add Product' : 'Update Product';
            document.getElementById('productForm').reset();
            document.getElementById('formAction').value = action;
            document.getElementById('productId').value = id || '';

            // Reset image preview
            imagePreview.style.display = 'none';
            imageUpload.style.display = 'block';
            imageUrl.value = '';
            currentImage.value = '';

            // Reset size and color checkboxes
            updateSizeAndColorOptions();

            // Set default values for featured and on_sale
            document.getElementById('featured').value = 'false';
            document.getElementById('on_sale').value = 'false';

            if (action === 'update' && id) {
                fillFormData(id);
            }

            productModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeForm() {
            if (formChanged) {
                if (confirm('You have unsaved changes. Are you sure you want to close?')) {
                    productModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                    formChanged = false;
                }
            } else {
                productModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteProductName').textContent = name;
            document.getElementById('deleteProductId').value = id;
            document.getElementById('password').value = '';
            deleteModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function fillFormData(id) {
            const product = products.find(p => p.id === id);
            if (product) {
                document.getElementById('name').value = product.name;
                document.getElementById('category').value = product.category;
                document.getElementById('price').value = product.price;
                document.getElementById('description').value = product.description;
                document.getElementById('stock').value = product.stock;
                document.getElementById('featured').value = product.featured;
                document.getElementById('on_sale').value = product.on_sale;

                // Update size and color checkboxes based on category
                updateSizeAndColorOptions(product.sizes, product.colors);

                if (product.image) {
                    previewImg.src = product.image;
                    imagePreview.style.display = 'block';
                    imageUpload.style.display = 'none';
                    currentImage.value = product.image;
                }
            }
        }

        function updateSizeAndColorOptions(selectedSizes = [], selectedColors = []) {
            const category = document.getElementById('category').value;
            const sizeCheckboxes = document.getElementById('sizeCheckboxes');
            const colorCheckboxes = document.getElementById('colorCheckboxes');

            // Clear existing checkboxes
            sizeCheckboxes.innerHTML = '';
            colorCheckboxes.innerHTML = '';

            if (category && categoryOptions[category]) {
                // Populate size checkboxes
                categoryOptions[category].sizes.forEach(size => {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label>
                            <input type="checkbox" name="sizes[]" value="${size}" ${selectedSizes.includes(size) ? 'checked' : ''}>
                            ${size}
                        </label>
                    `;
                    sizeCheckboxes.appendChild(div);
                });

                // Populate color checkboxes
                categoryOptions[category].colors.forEach(color => {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label>
                            <input type="checkbox" name="colors[]" value="${color}" ${selectedColors.includes(color) ? 'checked' : ''}>
                            ${color}
                        </label>
                    `;
                    colorCheckboxes.appendChild(div);
                });
            }
        }

        // Image upload functionality
        imageUpload.addEventListener('click', () => imageFile.click());

        imageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUpload.classList.add('dragover');
        });

        imageUpload.addEventListener('dragleave', () => {
            imageUpload.classList.remove('dragover');
        });

        imageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUpload.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleImageFile(files[0]);
            }
        });

        imageFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImageFile(e.target.files[0]);
            }
        });

        function handleImageFile(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, or GIF)');
                return;
            }

            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                imagePreview.style.display = 'block';
                imageUpload.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }

        function removeImage() {
            imagePreview.style.display = 'none';
            imageUpload.style.display = 'block';
            imageFile.value = '';
            imageUrl.value = '';
            currentImage.value = '';
        }

        // Search and filter functionality
        function filterProducts() {
            const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const rows = document.querySelectorAll('.product-row');
            let visibleRows = [];

            rows.forEach(row => {
                const productName = row.querySelector('.product-name').textContent.toLowerCase();
                const productCategory = row.dataset.category;
                const productStock = row.dataset.stock;

                let showRow = true;

                // Search filter
                if (searchTerm && !productName.includes(searchTerm)) {
                    showRow = false;
                }

                // Category filter
                if (categoryFilter && productCategory !== categoryFilter) {
                    showRow = false;
                }

                // Stock filter
                if (stockFilter) {
                    if (stockFilter === 'in-stock' && productStock !== 'in-stock') {
                        showRow = false;
                    } else if (stockFilter === 'low-stock' && productStock !== 'low-stock') {
                        showRow = false;
                    } else if (stockFilter === 'out-of-stock' && productStock !== 'out-of-stock') {
                        showRow = false;
                    }
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

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === productModal) {
                closeForm();
            }
            if (e.target === deleteModal) {
                closeDeleteModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (productModal.classList.contains('show')) {
                    closeForm();
                }
                if (deleteModal.classList.contains('show')) {
                    closeDeleteModal();
                }
            }
        });

        // Auto-hide notification messages and initialize pagination
        document.addEventListener('DOMContentLoaded', () => {
            const notification = document.getElementById('notification');
            if (notification && notification.classList.contains('show')) {
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 5000);
            }
            filterProducts();
            // Initialize size and color checkboxes for the default category
            updateSizeAndColorOptions();
        });

        // Form validation
        document.getElementById('productForm').addEventListener('submit', (e) => {
            const name = document.getElementById('name').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock').value);

            if (!name) {
                e.preventDefault();
                alert('Product name is required');
                return;
            }

            if (price < 0) {
                e.preventDefault();
                alert('Price cannot be negative');
                return;
            }

            if (stock < 0) {
                e.preventDefault();
                alert('Stock cannot be negative');
                return;
            }
        });

        // Enhanced search with debouncing
        let searchTimeout;
        document.getElementById('searchProduct').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterProducts, 300);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N for new product
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openForm('create');
            }

            // Ctrl/Cmd + F for search focus
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchProduct').focus();
            }
        });

        // Confirm before leaving page with unsaved changes
        let formChanged = false;
        const formInputs = document.querySelectorAll('#productForm input, #productForm select, #productForm textarea');

        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged && productModal.classList.contains('show')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Reset form change tracking when form is submitted or closed
        document.getElementById('productForm').addEventListener('submit', () => {
            formChanged = false;
        });

        // Add loading state to buttons
        function addLoadingState(button, text = 'Loading...') {
            button.disabled = true;
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
        }

        function removeLoadingState(button, originalText) {
            button.disabled = false;
            button.innerHTML = originalText;
        }

        // Enhanced form submission with loading states
        document.getElementById('productForm').addEventListener('submit', (e) => {
            const submitButton = document.getElementById('saveProductBtn');
            const originalText = submitButton.innerHTML;
            addLoadingState(submitButton, 'Saving...');

            // Re-enable button after a short delay (in case of validation errors)
            setTimeout(() => {
                if (submitButton.disabled) {
                    removeLoadingState(submitButton, originalText);
                }
            }, 3000);
        });

        // Add tooltips for action buttons
        function addTooltips() {
            const editButtons = document.querySelectorAll('.btn-primary');
            const deleteButtons = document.querySelectorAll('.btn-secondary');

            editButtons.forEach(btn => {
                if (btn.innerHTML.includes('Edit')) {
                    btn.title = 'Edit this product';
                }
            });

            deleteButtons.forEach(btn => {
                if (btn.innerHTML.includes('Delete')) {
                    btn.title = 'Delete this product (requires admin password)';
                }
            });
        }

        // Initialize tooltips when page loads
        document.addEventListener('DOMContentLoaded', addTooltips);
    </script>
</body>

</html>