<?php
session_start();

$xmlFile = 'products.xml';

function loadXML($file) {
    if (!file_exists($file)) {
        die('XML file not found.');
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

function createProduct($xml, $data) {
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
    foreach (explode(',', $data['sizes']) as $size) {
        $sizes->addChild('size', trim($size));
    }
    
    $colors = $product->addChild('colors');
    foreach (explode(',', $data['colors']) as $color) {
        $colors->addChild('color', trim($color));
    }
    
    $product->addChild('rating', (float)$data['rating']);
    $product->addChild('review_count', (int)$data['review_count']);
    $product->addChild('featured', filter_var($data['featured'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
    $product->addChild('on_sale', filter_var($data['on_sale'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
    
    return saveXML($xml, $GLOBALS['xmlFile']);
}

function updateProduct($xml, $id, $data) {
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
            foreach (explode(',', $data['sizes']) as $size) {
                $sizes->addChild('size', trim($size));
            }
            
            unset($product->colors);
            $colors = $product->addChild('colors');
            foreach (explode(',', $data['colors']) as $color) {
                $colors->addChild('color', trim($color));
            }
            
            $product->rating = (float)$data['rating'];
            $product->review_count = (int)$data['review_count'];
            $product->featured = filter_var($data['featured'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            $product->on_sale = filter_var($data['on_sale'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            
            return saveXML($xml, $GLOBALS['xmlFile']);
        }
    }
    return false;
}

function deleteProduct($xml, $id, $password) {
    $adminPassword = 'admin123';
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
                    'sizes' => $_POST['sizes'],
                    'colors' => $_POST['colors'],
                    'rating' => $_POST['rating'],
                    'review_count' => $_POST['review_count'],
                    'featured' => isset($_POST['featured']) ? true : false,
                    'on_sale' => isset($_POST['on_sale']) ? true : false
                ];
                if (preg_match('/\.(jpg|jpeg|png)$/i', $data['image'])) {
                    if (createProduct($xml, $data)) {
                        $_SESSION['message'] = 'Product created successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to create product.';
                    }
                } else {
                    $_SESSION['error'] = 'Invalid image format. Use jpg, jpeg, or png.';
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
                    'sizes' => $_POST['sizes'],
                    'colors' => $_POST['colors'],
                    'rating' => $_POST['rating'],
                    'review_count' => $_POST['review_count'],
                    'featured' => isset($_POST['featured']) ? true : false,
                    'on_sale' => isset($_POST['on_sale']) ? true : false
                ];
                if (preg_match('/\.(jpg|jpeg|png)$/i', $data['image'])) {
                    if (updateProduct($xml, $_POST['id'], $data)) {
                        $_SESSION['message'] = 'Product updated successfully!';
                    } else {
                        $_SESSION['error'] = 'Failed to update product.';
                    }
                } else {
                    $_SESSION['error'] = 'Invalid image format. Use jpg, jpeg, or png.';
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
    header('Location: products.php');
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
            --primary-color: #6366f1;
            --primary-gradient: linear-gradient(#8b5cf6);
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--blackfont-color);
            padding: 0;
            min-height: 100vh;
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
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--division-color);
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

        .form-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 24px;
            margin-bottom: 24px;
            display: none;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
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
        }

        .product-table th {
            background-color: var(--division-color);
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--blackfont-color);
        }

        .product-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--division-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .product-table tr:last-child td {
            border-bottom: none;
        }

        .product-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .action-cell {
            display: flex;
            gap: 12px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            z-index: 1000;
            display: none;
        }

        .notification.success {
            background-color: var(--success-color);
            color: var(--whitefont-color);
        }

        .notification.error {
            background-color: var(--danger-color);
            color: var(--whitefont-color);
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(3px);
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
            padding: 24px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--grayfont-color);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .form-container {
                padding: 16px;
            }

            .products-container {
                overflow-x: auto;
            }

            .action-cell {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Products Management</h1>
            <button class="btn btn-primary" onclick="openForm('create')"><i class="fas fa-plus"></i> Add Product</button>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="notification success" id="notification"><?php echo $_SESSION['message']; ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="notification error" id="notification"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="form-container" id="productForm">
            <form id="productFormElement" method="POST">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="productId">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category" required>
                        <?php
                        $xml = loadXML($xmlFile);
                        foreach ($xml->categories->category as $category) {
                            echo "<option value='$category'>$category</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="material">Material</label>
                    <input type="text" name="material" id="material" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (PHP)</label>
                    <input type="number" name="price" id="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Image (jpg, jpeg, png)</label>
                    <input type="text" name="image" id="image" placeholder="e.g., images/product.jpg" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input type="number" name="stock" id="stock" required>
                </div>
                <div class="form-group">
                    <label for="rating">Rating (0-5)</label>
                    <input type="number" name="rating" id="rating" step="0.1" min="0" max="5" required>
                </div>
                <div class="form-group">
                    <label for="review_count">Review Count</label>
                    <input type="number" name="review_count" id="review_count" required>
                </div>
                <div class="form-group">
                    <label for="sizes">Sizes (comma-separated)</label>
                    <input type="text" name="sizes" id="sizes" required>
                </div>
                <div class="form-group">
                    <label for="colors">Colors (comma-separated)</label>
                    <input type="text" name="colors" id="colors" required>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="featured" id="featured"> Featured</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="on_sale" id="on_sale"> On Sale</label>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <button type="button" class="btn btn-outline" onclick="closeForm()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="modal-backdrop" id="deleteModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Delete Product</h3>
                    <button class="modal-close" onclick="closeDeleteModal()">×</button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteProductId">
                    <div class="form-group">
                        <label for="password">Admin Password</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Delete</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="products-container">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock & Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $xml = loadXML($xmlFile);
                    foreach ($xml->products->product as $product) {
                        echo "<tr>";
                        echo "<td>{$product->id}</td>";
                        echo "<td>" . htmlspecialchars($product->name) . "</td>";
                        echo "<td>{$product->category}</td>";
                        echo "<td>PHP {$product->price}</td>";
                        echo "<td>";
                        echo "Stock: {$product->stock} units<br>";
                        echo "Rating: {$product->rating} (Reviews: {$product->review_count})";
                        echo "</td>";
                        echo "<td class='action-cell'>";
                        echo "<button class='btn btn-primary' onclick=\"openForm('update', {$product->id})\"><i class='fas fa-edit'></i> Edit</button>";
                        echo "<button class='btn btn-secondary' onclick=\"openDeleteModal({$product->id})\"><i class='fas fa-trash'></i> Delete</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openForm(action, id = null) {
            const form = document.getElementById('productForm');
            const formElement = document.getElementById('productFormElement');
            const formAction = document.getElementById('formAction');
            form.style.display = 'block';
            formAction.value = action;

            if (action === 'create') {
                formElement.reset();
                document.getElementById('productId').value = '';
            } else if (action === 'update' && id) {
                fetchProductData(id);
                document.getElementById('productId').value = id;
            }
        }

        function closeForm() {
            document.getElementById('productForm').style.display = 'none';
        }

        function openDeleteModal(id) {
            document.getElementById('deleteProductId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function fetchProductData(id) {
            const products = <?php
                $products = [];
                foreach ($xml->products->product as $p) {
                    $products[] = [
                        'id' => (int)$p->id,
                        'name' => (string)$p->name,
                        'category' => (string)$p->category,
                        'material' => (string)$p->material,
                        'price' => (float)$p->price,
                        'description' => (string)$p->description,
                        'image' => (string)$p->image,
                        'stock' => (int)$p->stock,
                        'sizes' => array_map('strval', (array)$p->sizes->size),
                        'colors' => array_map('strval', (array)$p->colors->color),
                        'rating' => (float)$p->rating,
                        'review_count' => (int)$p->review_count,
                        'featured' => (string)$p->featured === 'true',
                        'on_sale' => (string)$p->on_sale === 'true'
                    ];
                }
                echo json_encode($products);
            ?>;
            
            const product = products.find(p => p.id === id);
            if (product) {
                document.getElementById('name').value = product.name;
                document.getElementById('category').value = product.category;
                document.getElementById('material').value = product.material;
                document.getElementById('price').value = product.price;
                document.getElementById('description').value = product.description;
                document.getElementById('image').value = product.image;
                document.getElementById('stock').value = product.stock;
                document.getElementById('rating').value = product.rating;
                document.getElementById('review_count').value = product.review_count;
                document.getElementById('sizes').value = product.sizes.join(', ');
                document.getElementById('colors').value = product.colors.join(', ');
                document.getElementById('featured').checked = product.featured;
                document.getElementById('on_sale').checked = product.on_sale;
            }
        }

        const notification = document.getElementById('notification');
        if (notification) {
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>