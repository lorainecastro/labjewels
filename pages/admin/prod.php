<?php
session_start(); // Start session for notifications

$xmlFile = 'products.xml';

function loadXML($file) {
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><store><metadata><name>Lab Jewels</name><version>1.0</version><currency>PHP</currency><last_updated>2025-06-19</last_updated></metadata><categories><category>Necklace</category><category>Ring</category><category>Bracelet</category><category>Earrings</category><category>Anklet</category></categories><products></products></store>');
        $xml->asXML($file);
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

function saveXML($xml, $file) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($file);
}

function validateInput($data) {
    $errors = [];
    if (empty($data['name'])) $errors[] = 'Product name is required.';
    if (!in_array($data['category'], ['Necklace', 'Ring', 'Bracelet', 'Earrings', 'Anklet'])) $errors[] = 'Invalid category.';
    if (empty($data['material'])) $errors[] = 'Material is required.';
    if (!is_numeric($data['price']) || $data['price'] < 0) $errors[] = 'Price must be a non-negative number.';
    if (empty($data['description'])) $errors[] = 'Description is required.';
    if (!preg_match('/\.(jpg|jpeg|png)$/i', $data['image'])) $errors[] = 'Invalid image format. Use jpg, jpeg, or png.';
    if (!is_numeric($data['stock']) || $data['stock'] < 0) $errors[] = 'Stock must be a non-negative integer.';
    if (empty($data['sizes'])) $errors[] = 'At least one size is required.';
    if (empty($data['colors'])) $errors[] = 'At least one color is required.';
    if (!is_numeric($data['rating']) || $data['rating'] < 0 || $data['rating'] > 5) $errors[] = 'Rating must be between 0 and 5.';
    if (!is_numeric($data['review_count']) || $data['review_count'] < 0) $errors[] = 'Review count must be a non-negative integer.';
    return $errors;
}

function createProduct($xml, $data) {
    $errors = validateInput($data);
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
        return false;
    }
    
    $products = $xml->products;
    $product = $products->addChild('product');
    
    $lastId = 0;
    foreach ($xml->products->product as $p) {
        $id = (int)$p->id;
        if ($id > $lastId) $lastId = $id;
    }
    $product->addChild('id', $lastId + 1);
    $product->addChild('name', htmlspecialchars($data['name']));
    $product->addChild('category', htmlspecialchars($data['category']));
    $product->addChild('material', htmlspecialchars($data['material']));
    $product->addChild('price', (float)$data['price']);
    $product->addChild('currency', 'PHP');
    $product->addChild('description', htmlspecialchars($data['description']));
    $product->addChild('image', htmlspecialchars($data['image']));
    $product->addChild('stock', (int)$data['stock']);
    $sizes = $product->addChild('sizes');
    foreach ($data['sizes'] as $size) {
        $sizes->addChild('size', htmlspecialchars($size));
    }
    $colors = $product->addChild('colors');
    foreach ($data['colors'] as $color) {
        $colors->addChild('color', htmlspecialchars($color));
    }
    $product->addChild('rating', (float)$data['rating']);
    $product->addChild('review_count', (int)$data['review_count']);
    $product->addChild('featured', $data['featured'] ? 'true' : 'false');
    $product->addChild('on_sale', $data['on_sale'] ? 'true' : 'false');
    
    return saveXML($xml, $GLOBALS['xmlFile']);
}

function updateProduct($xml, $id, $data) {
    $errors = validateInput($data);
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
        return false;
    }
    
    foreach ($xml->products->product as $product) {
        if ((int)$product->id == $id) {
            $product->name = htmlspecialchars($data['name']);
            $product->category = htmlspecialchars($data['category']);
            $product->material = htmlspecialchars($data['material']);
            $product->price = (float)$data['price'];
            $product->description = htmlspecialchars($data['description']);
            $product->image = htmlspecialchars($data['image']);
            $product->stock = (int)$data['stock'];
            unset($product->sizes);
            $sizes = $product->addChild('sizes');
            foreach ($data['sizes'] as $size) {
                $sizes->addChild('size', htmlspecialchars($size));
            }
            unset($product->colors);
            $colors = $product->addChild('colors');
            foreach ($data['colors'] as $color) {
                $colors->addChild('color', htmlspecialchars($color));
            }
            $product->rating = (float)$data['rating'];
            $product->review_count = (int)$data['review_count'];
            $product->featured = $data['featured'] ? 'true' : 'false';
            $product->on_sale = $data['on_sale'] ? 'true' : 'false';
            
            return saveXML($xml, $GLOBALS['xmlFile']);
        }
    }
    return false;
}

function deleteProduct($xml, $id, $password) {
    $adminPassword = 'admin123'; // Note: Hardcoded for demo; use secure storage in production
    if ($password !== $adminPassword) {
        return false;
    }
    
    $index = 0;
    foreach ($xml->products->product as $product) {
        if ((int)$product->id == $id) {
            unset($xml->products->product[$index]);
            return saveXML($xml, $GLOBALS['xmlFile']);
        }
        $index++;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xml = loadXML($xmlFile);
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'material' => $_POST['material'],
                    'price' => $_POST['price'],
                    'description' => $_POST['description'],
                    'image' => $_POST['image'],
                    'stock' => $_POST['stock'],
                    'sizes' => isset($_POST['sizes']) ? $_POST['sizes'] : ['One Size'],
                    'colors' => isset($_POST['colors']) ? $_POST['colors'] : ['Silver'],
                    'rating' => $_POST['rating'],
                    'review_count' => $_POST['review_count'],
                    'featured' => isset($_POST['featured']) ? true : false,
                    'on_sale' => isset($_POST['on_sale']) ? true : false
                ];
                if (createProduct($xml, $data)) {
                    $_SESSION['message'] = 'Product created successfully!';
                } else {
                    $_SESSION['error'] = $_SESSION['error'] ?? 'Failed to create product.';
                }
                break;
                
            case 'update':
                $data = [
                    'name' => $_POST['name'],
                    'category' => $_POST['category'],
                    'material' => $_POST['material'],
                    'price' => $_POST['price'],
                    'description' => $_POST['description'],
                    'image' => $_POST['image'],
                    'stock' => $_POST['stock'],
                    'sizes' => isset($_POST['sizes']) ? $_POST['sizes'] : ['One Size'],
                    'colors' => isset($_POST['colors']) ? $_POST['colors'] : ['Silver'],
                    'rating' => $_POST['rating'],
                    'review_count' => $_POST['review_count'],
                    'featured' => isset($_POST['featured']) ? true : false,
                    'on_sale' => isset($_POST['on_sale']) ? true : false
                ];
                if (updateProduct($xml, $_POST['id'], $data)) {
                    $_SESSION['message'] = 'Product updated successfully!';
                } else {
                    $_SESSION['error'] = $_SESSION['error'] ?? 'Failed to update product.';
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
    header('Location: products_management.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management</title>
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
            margin-bottom: 15px;
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

        .product-table th:nth-child(1), .product-table td:nth-child(1) { width: 5%; }
        .product-table th:nth-child(2), .product-table td:nth-child(2) { width: 10%; }
        .product-table th:nth-child(3), .product-table td:nth-child(3) { width: 15%; }
        .product-table th:nth-child(4), .product-table td:nth-child(4) { width: 10%; }
        .product-table th:nth-child(5), .product-table td:nth-child(5) { width: 10%; }
        .product-table th:nth-child(6), .product-table td:nth-child(6) { width: 9%; }
        .product-table th:nth-child(7), .product-table td:nth-child(7) { width: 13%; }
        .product-table th:nth-child(8), .product-table td:nth-child(8) { width: 10%; }

        .action-cell {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }

        @media (max-width: 768px) {
            .product-table th, .product-table td {
                padding: 6px 8px;
            }

            .product-table img {
                max-width: 30px;
                max-height: 30px;
            }

            .action-cell .btn {
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

        .image-upload.dragover {
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.1);
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
            opacity: 0.8;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }

        .pagination button {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pagination button:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .pagination button.active {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Products Management</h1>
            <button class="btn btn-primary" id="addProductBtn"><i class="fas fa-plus"></i> Add New Product</button>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Sales</div>
                        <div class="card-value">₱86,582</div>
                    </div>
                    <div class="card-icon bg-purple">₱</div>
                </div>
                <div class="card-footer">
                    <div class="card-trend down">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 12a.5.5 0 0 0 .5-.5V5.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 1 0 .708.708L7.5 5.707V11.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        12.5%
                    </div>
                    <div class="card-period">vs. last month</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Orders</div>
                        <div class="card-value">382</div>
                    </div>
                    <div class="card-icon bg-pink">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 0 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                        </svg>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="card-trend down">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 12a.5.5 0 0 0 .5-.5V5.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 1 0 .708.708L7.5 5.707V11.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        8.2%
                    </div>
                    <div class="card-period">vs. last month</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Customers</div>
                        <div class="card-value">125</div>
                    </div>
                    <div class="card-icon bg-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                            <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                        </svg>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="card-trend down">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 12a.5.5 0 0 0 .5-.5V5.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 1 0 .708.708L7.5 5.707V11.5a.5.5 0 0 0 .5.5z"/>
                        </svg>
                        3.7%
                    </div>
                    <div class="card-period">vs. last month</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Products Sold</div>
                        <div class="card-value">1204</div>
                    </div>
                    <div class="card-icon bg-green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 1 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
                        </svg>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="card-trend up">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5v5.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 10.293V4.5A.5.5 0 0 1 8 4z"/>
                        </svg>
                        2.3%
                    </div>
                    <div class="card-period">vs. last month</div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchProduct" placeholder="Search products...">
            </div>
            <div class="filter-dropdown">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="Necklace">Necklace</option>
                    <option value="Ring">Ring</option>
                    <option value="Bracelet">Bracelet</option>
                    <option value="Earrings">Earrings</option>
                    <option value="Anklet">Anklet</option>
                </select>
            </div>
            <div class="filter-dropdown">
                <select id="stockFilter">
                    <option value="">All Stock</option>
                    <option value="in-stock">In Stock</option>
                    <option value="low-stock">Low Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="status-message success-message" id="notification"><?php echo $_SESSION['message']; ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="status-message error-message" id="notification"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
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
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php
                    $xml = loadXML($xmlFile);
                    foreach ($xml->products->product as $product) {
                        echo "<tr>";
                        echo "<td>{$product->id}</td>";
                        echo "<td><img src='{$product->image}' alt='{$product->name}' style='max-width: 50px; max-height: 50px;'></td>";
                        echo "<td>" . htmlspecialchars($product->name) . "</td>";
                        echo "<td>{$product->category}</td>";
                        echo "<td>PHP {$product->price}</td>";
                        echo "<td>{$product->stock} units</td>";
                        echo "<td>{$product->rating} (Reviews: {$product->review_count})</td>";
                        echo "<td class='action-cell'>";
                        echo "<button class='btn btn-primary' onclick=\"openForm('update', {$product->id})\"><i class='fas fa-edit'></i> Edit</button>";
                        echo "<button class='btn btn-secondary' onclick=\"openDeleteModal({$product->id}, '" . htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') . "')\"><i class='fas fa-trash'></i> Delete</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination">
            <!-- Pagination buttons will be dynamically added here -->
        </div>

        <div class="modal-backdrop" id="productModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Add New Product</h3>
                    <button class="close-modal" id="closeModal">×</button>
                </div>
                <div class="modal-body">
                    <form id="productForm" method="POST">
                        <input type="hidden" name="action" id="formAction">
                        <input type="hidden" name="id" id="productId">
                        <div class="form-group">
                            <label for="name">Product Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required>
                                <?php
                                foreach ($xml->categories->category as $category) {
                                    echo "<option value='$category'>$category</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="material">Material</label>
                            <input type="text" name="material" id="material" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Price (PHP)</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Product Image</label>
                            <div class="image-upload" id="imageUpload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Drag & drop image here or click to browse</p>
                                <small>JPG, PNG or GIF, Max size 5MB</small>
                            </div>
                            <div class="image-preview" id="imagePreview">
                                <img id="previewImg" src="">
                                <button class="remove-image" id="removeImage">×</button>
                            </div>
                            <input type="hidden" name="image" id="image">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock</label>
                            <input type="number" name="stock" id="stock" class="form-control" required>
                        </div>
                        <div class="form-group" id="sizesContainer">
                            <label>Sizes</label>
                            <button type="button" class="btn btn-primary" onclick="addSizeField()">Add Size</button>
                            <div id="sizeInputs"></div>
                        </div>
                        <div class="form-group" id="colorsContainer">
                            <label>Colors</label>
                            <button type="button" class="btn btn-primary" onclick="addColorField()">Add Color</button>
                            <div id="colorInputs"></div>
                        </div>
                        <div class="form-group">
                            <label for="rating">Rating (0-5)</label>
                            <input type="number" name="rating" id="rating" class="form-control" step="0.1" min="0" max="5" required>
                        </div>
                        <div class="form-group">
                            <label for="review_count">Review Count</label>
                            <input type="number" name="review_count" id="review_count" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="featured">Featured</label>
                            <input type="checkbox" name="featured" id="featured">
                        </div>
                        <div class="form-group">
                            <label for="on_sale">On Sale</label>
                            <input type="checkbox" name="on_sale" id="on_sale">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveProductBtn">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal-backdrop" id="deleteModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Delete Product</h3>
                    <button class="close-modal" id="closeDeleteModal">×</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <span id="deleteProductName"></span>?</p>
                    <p>This action cannot be undone.</p>
                    <form id="deleteForm" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteProductId">
                        <div class="form-group">
                            <label for="password">Admin Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                            <button type="submit" class="btn btn-secondary" id="confirmDeleteBtn">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="status-message success-message" id="successMessage"></div>
        <div class="status-message error-message" id="errorMessage"></div>
    </div>

    <script>
        // DOM Elements
        const productModal = document.getElementById('productModal');
        const deleteModal = document.getElementById('deleteModal');
        const closeModal = document.getElementById('closeModal');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const saveProductBtn = document.getElementById('saveProductBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const modalTitle = document.getElementById('modalTitle');
        const deleteProductName = document.getElementById('deleteProductName');
        const imageUpload = document.getElementById('imageUpload');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const removeImage = document.getElementById('removeImage');
        const imageInput = document.getElementById('image');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const productForm = document.getElementById('productForm');
        const sizeInputs = document.getElementById('sizeInputs');
        const colorInputs = document.getElementById('colorInputs');
        const addProductBtn = document.getElementById('addProductBtn');
        const searchProduct = document.getElementById('searchProduct');
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');

        // Open product form (create or update)
        function openForm(action, id = null) {
            modalTitle.textContent = action === 'create' ? 'Add New Product' : 'Edit Product';
            productForm.reset();
            sizeInputs.innerHTML = '';
            colorInputs.innerHTML = '';
            addSizeField();
            addColorField();
            document.getElementById('formAction').value = action;
            document.getElementById('productId').value = id || '';
            imagePreview.style.display = 'none';
            imageUpload.style.display = 'block';
            imageInput.value = '';
            if (action === 'update' && id) {
                fetchProductData(id);
            }
            productModal.classList.add('show');
        }

        // Close product form
        function closeForm() {
            productModal.classList.remove('show');
            productForm.reset();
            sizeInputs.innerHTML = '';
            colorInputs.innerHTML = '';
            addSizeField();
            addColorField();
            imagePreview.style.display = 'none';
            imageUpload.style.display = 'block';
            imageInput.value = '';
        }

        // Open delete confirmation modal
        function openDeleteModal(id, name) {
            deleteProductName.textContent = name;
            document.getElementById('deleteProductId').value = id;
            deleteModal.classList.add('show');
        }

        // Close delete confirmation modal
        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.getElementById('deleteForm').reset();
        }

        // Fetch product data for editing
        function fetchProductData(id) {
            const products = <?php
                $products = [];
                foreach ($xml->products->product as $p) {
                    $sizes = [];
                    foreach ($p->sizes->size as $size) {
                        $sizes[] = (string)$size;
                    }
                    $colors = [];
                    foreach ($p->colors->color as $color) {
                        $colors[] = (string)$color;
                    }
                    $products[] = [
                        'id' => (int)$p->id,
                        'name' => (string)$p->name,
                        'category' => (string)$p->category,
                        'material' => (string)$p->material,
                        'price' => (float)$p->price,
                        'description' => (string)$p->description,
                        'image' => (string)$p->image,
                        'stock' => (int)$p->stock,
                        'sizes' => $sizes,
                        'colors' => $colors,
                        'rating' => (float)$p->rating,
                        'review_count' => (int)$p->review_count,
                        'featured' => (string)$p->featured === 'true',
                        'on_sale' => (string)$p->on_sale === 'true'
                    ];
                }
                echo json_encode($products);
            ?>;
            
            const product = products.find(p => p.id === parseInt(id));
            if (product) {
                document.getElementById('name').value = product.name;
                document.getElementById('category').value = product.category;
                document.getElementById('material').value = product.material;
                document.getElementById('price').value = product.price;
                document.getElementById('description').value = product.description;
                document.getElementById('image').value = product.image;
                document.getElementById('stock').value = product.stock;
                sizeInputs.innerHTML = '';
                product.sizes.forEach(size => {
                    addSizeField(size);
                });
                colorInputs.innerHTML = '';
                product.colors.forEach(color => {
                    addColorField(color);
                });
                document.getElementById('rating').value = product.rating;
                document.getElementById('review_count').value = product.review_count;
                document.getElementById('featured').checked = product.featured;
                document.getElementById('on_sale').checked = product.on_sale;
                if (product.image) {
                    previewImg.src = product.image;
                    imagePreview.style.display = 'block';
                    imageUpload.style.display = 'none';
                }
            }
        }

        // File input for image upload
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', handleFileSelect);
        document.body.appendChild(fileInput);

        // Image upload handling
        imageUpload.addEventListener('click', () => fileInput.click());
        imageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUpload.classList.add('dragover');
        });
        imageUpload.addEventListener('dragleave', (e) => {
            e.preventDefault();
            imageUpload.classList.remove('dragover');
        });
        imageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUpload.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFileSelect(e) {
            handleFiles(e.target.files);
        }

        function handleFiles(files) {
            const file = files[0];
            if (!file) return;
            if (!file.type.match('image.*') || file.size > 5 * 1024 * 1024) {
                showMessage(errorMessage, 'Please select a valid image (JPG, PNG, GIF) under 5MB.');
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                imagePreview.style.display = 'block';
                imageUpload.style.display = 'none';
                imageInput.value = e.target.result; // Note: Use server-side file upload in production
            };
            reader.readAsDataURL(file);
        }

        // Remove image
        removeImage.addEventListener('click', (e) => {
            e.preventDefault();
            previewImg.src = '';
            imagePreview.style.display = 'none';
            imageUpload.style.display = 'block';
            imageInput.value = '';
            fileInput.value = '';
        });

        // Add size field
        function addSizeField(value = '') {
            const div = document.createElement('div');
            div.className = 'form-group';
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'sizes[]';
            input.className = 'form-control';
            input.value = value;
            input.required = true;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-secondary';
            removeBtn.textContent = 'Remove';
            removeBtn.onclick = () => div.remove();
            div.appendChild(input);
            div.appendChild(removeBtn);
            sizeInputs.appendChild(div);
        }

        // Add color field
        function addColorField(value = '') {
            const div = document.createElement('div');
            div.className = 'form-group';
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'colors[]';
            input.className = 'form-control';
            input.value = value;
            input.required = true;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-secondary';
            removeBtn.textContent = 'Remove';
            removeBtn.onclick = () => div.remove();
            div.appendChild(input);
            div.appendChild(removeBtn);
            colorInputs.appendChild(div);
        }

        // Show notification message
        function showMessage(element, message) {
            element.textContent = message;
            element.classList.add('show');
            setTimeout(() => element.classList.remove('show'), 3000);
        }

        // Event listeners
        addProductBtn.addEventListener('click', () => openForm('create'));
        closeModal.addEventListener('click', closeForm);
        cancelBtn.addEventListener('click', closeForm);
        closeDeleteModal.addEventListener('click', closeDeleteModal);
        cancelDeleteBtn.addEventListener('click', closeDeleteModal);

        // Notification handling
        const notification = document.getElementById('notification');
        if (notification) {
            notification.classList.add('show');
            setTimeout(() => notification.classList.remove('show'), 3000);
        }

        // Filter products
        function filterProducts() {
            const search = searchProduct.value.toLowerCase();
            const category = categoryFilter.value;
            const stock = stockFilter.value;
            const rows = document.querySelectorAll('#productTableBody tr');
            
            rows.forEach(row => {
                const name = row.cells[2].textContent.toLowerCase();
                const cat = row.cells[3].textContent;
                const stockVal = parseInt(row.cells[5].textContent);
                
                const matchesSearch = name.includes(search);
                const matchesCategory = !category || cat === category;
                let matchesStock = true;
                if (stock === 'in-stock') matchesStock = stockVal > 10;
                else if (stock === 'low-stock') matchesStock = stockVal > 0 && stockVal <= 10;
                else if (stock === 'out-of-stock') matchesStock = stockVal === 0;
                
                row.style.display = matchesSearch && matchesCategory && matchesStock ? '' : 'none';
            });
        }

        // Add filter event listeners
        searchProduct.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        stockFilter.addEventListener('change', filterProducts);

        // Form submission validation
        productForm.addEventListener('submit', (e) => {
            const sizes = document.querySelectorAll('input[name="sizes[]"]');
            const colors = document.querySelectorAll('input[name="colors[]"]');
            let hasError = false;
            
            sizes.forEach(size => {
                if (!size.value.trim()) {
                    showMessage(errorMessage, 'All size fields must be filled.');
                    hasError = true;
                }
            });
            
            colors.forEach(color => {
                if (!color.value.trim()) {
                    showMessage(errorMessage, 'All color fields must be filled.');
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>